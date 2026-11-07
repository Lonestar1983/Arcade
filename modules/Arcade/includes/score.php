<?php
/**
 * Arcade score submission and game-run validation.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

/**
 * Validate a submitted score and consume its server-issued game run.
 *
 * Supports legacy hash, handshake, IBPro and pnFlashGames submissions. Accepted runs
 * must belong to the authenticated user and have started within two hours.
 *
 * Locks and deletes the run within the score transaction to prevent reuse.
 * Higher scores replace the personal best; every accepted run increments
 * the player's recorded play count.
 *
 * Client-submitted scores cannot be made cheat-proof through these checks.
 *
 * @param string $protocol Internal adapter name; pnflash requires a token-bound POST.
 * @param int    $bound_gid Game ID supplied by the adapter URL.
 * @return int|false Game ID on success, or false when validation fails.
 * @throws RuntimeException If session startup or a database operation fails.
 * @throws Throwable If an error occurs during the score transaction.
 */
function arcade_submit_score( $protocol = '', $bound_gid = 0 ) {
    arcade_session();

    $uid = arcade_uid();
    $gid = $protocol === 'pnflash' ? (int) $bound_gid : (int) arcade_request( 'gid' );

    if ( $gid === 0 ) {
        $gid = (int) ( $_SESSION['arcade_current_game'] ?? 0 );
    }

    if ( ! $uid || $gid <= 0 ) {
        return false;
    }

    $game = arcade_game( $gid );

    if ( ! $game || ! arcade_can_play( arcade_category( $game['category_id'] ) ) ) {
        return false;
    }

    $type      = (int) $game['type'];
    $uses_pnflash = $protocol === 'pnflash';

    if ( ( $type === 8 ) !== $uses_pnflash ) {
        return false;
    }
    $token     = arcade_request( 'arcade_token' );
    $run_token = $_SESSION['arcade_runs'][ $gid ] ?? '';

    if ( ! is_string( $run_token ) || $run_token === '' ) {
        return false;
    }

    if ( $token !== '' && ! hash_equals( $run_token, $token ) ) {
        return false;
    }

    // A validated handshake can select the protocol independently of game type.
    $uses_ibpro = ! $uses_pnflash && arcade_request( 'arcade_protocol' ) === 'ibpro';

    if ( $uses_pnflash && (
        ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST'
        || ! hash_equals( $run_token, $token )
        || arcade_request( 'module' ) !== 'pnFlashGames'
        || arcade_request( 'func' ) !== 'storeScore'
        || arcade_request( 'gid' ) !== (string) $gid
        || isset( $_SESSION['arcade_handshake'][ $gid ] )
    ) ) {
        return false;
    }

    if ( $type === 7 && ! $uses_ibpro ) {
        return false;
    }

    $uses_handshake = ! $uses_pnflash && ! $uses_ibpro && (
        $type === 6
        || isset( $_SESSION['arcade_handshake'][ $gid ] )
    );

    if ( $uses_ibpro ) {
        $game_name = pathinfo( $game['swf'], PATHINFO_FILENAME );

        // Bind the callback to the user's current run and expected SWF name.
        if (
            ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST'
            || ! hash_equals( $run_token, $token )
            || strcasecmp( arcade_request( 'gname' ), $game_name ) !== 0
            || isset( $_SESSION['arcade_handshake'][ $gid ] )
        ) {
            return false;
        }
    }

    $hash = '';

    if ( $type === 3 && ! $uses_handshake && ! $uses_ibpro ) {
        $new_hash = arcade_request( 'newhash' );
        $offset   = filter_var(
            arcade_request( 'hashoffset' ),
            FILTER_VALIDATE_INT
        );

        if (
            strlen( $new_hash ) !== 32
            || $offset === false
            || $offset < 0
            || $offset > 31
            || arcade_request( 'gpaver' ) !== 'GFARV2'
        ) {
            return false;
        }

        $hash = substr( $new_hash, $offset ) . substr( $new_hash, 0, $offset );
    }

    $score_key = $uses_pnflash ? 'score' : ( $type === 3 ? $game['scorevar'] : 'vscore' );
    $score     = arcade_request( $uses_ibpro ? 'gscore' : $score_key );

    if ( $uses_handshake ) {
        $pending = $_SESSION['arcade_pending'][ $gid ] ?? array();

        if (
            ! is_array( $pending )
            || ! isset(
                $pending['microone'],
                $pending['id'],
                $pending['run'],
                $pending['score']
            )
            || ! is_string( $pending['microone'] )
            || ! is_string( $pending['run'] )
            || ! hash_equals( $pending['microone'], arcade_request( 'microone' ) )
            || arcade_request( 'id' ) !== $pending['id']
            || ! hash_equals( $pending['run'], $run_token )
        ) {
            return false;
        }

        $score = $pending['score'];
    }

    if ( ! arcade_valid_score( $score ) ) {
        return false;
    }

    // ALTER TABLE commits implicitly, so any schema upgrade must happen first.
    if ( strpos( $score, '.' ) !== false ) {
    }

    arcade_db( 'START TRANSACTION' );

    try {
        $sql = 'SELECT *
                FROM ' . arcade_table( 'runs' ) . '
                WHERE token = ' . arcade_quote( $run_token ) . '
                    AND game_id = ' . $gid . '
                    AND user_id = ' . $uid . '
                FOR UPDATE';

        $run = arcade_one( $sql );

        if (
            ! $run
            || (int) $run['started'] < time() - 7200
            || ( $hash !== '' && ! hash_equals( $run['legacy_hash'], $hash ) )
        ) {
            arcade_db( 'ROLLBACK' );

            return false;
        }

        arcade_db(
            'DELETE FROM ' . arcade_table( 'runs' ) . '
             WHERE token = ' . arcade_quote( $run_token )
        );

        $now = time();

        // Update achieved before score so the comparison uses the previous best.
        $sql = 'INSERT INTO ' . arcade_table( 'scores' ) . '
                    (game_id, user_id, score, achieved)
                VALUES (
                    ' . $gid . ',
                    ' . $uid . ',
                    ' . $score . ',
                    ' . $now . '
                )
                ON DUPLICATE KEY UPDATE
                    achieved = IF(
                        VALUES(score) > score,
                        VALUES(achieved),
                        achieved
                    ),
                    score = GREATEST(score, VALUES(score)),
                    plays = plays + 1';

        arcade_db( $sql );
        arcade_db( 'COMMIT' );

        unset(
            $_SESSION['arcade_runs'][ $gid ],
            $_SESSION['arcade_pending'][ $gid ],
            $_SESSION['arcade_handshake'][ $gid ]
        );

        return $gid;
    } catch ( Throwable $e ) {
        arcade_db( 'ROLLBACK' );

        throw $e;
    }
}