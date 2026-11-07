<?php
/**
 * Handle the legacy Arcade sessionstart, permrequest and burn protocol.
 *
 * Implements the handshake observed in the supplied Airport SWF.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

/**
 * Validate a game run and process its legacy score handshake.
 *
 * Supports three modes:
 * - sessionstart: Generate session identifiers for the game.
 * - permrequest: Validate and retain a pending score.
 * - burn: Validate the pending handshake and submit the score.
 *
 * Runs must belong to the authenticated user, refer to an accessible active
 * game, and have started within the last two hours.
 *
 * Sends a protocol response or redirects, then stops execution.
 *
 * @return void
 * @throws RuntimeException If a database operation or session startup fails.
 * @throws Exception If secure random value generation fails.
 */
function arcade_handshake() {
    arcade_session();

    $uid = arcade_uid();
    $gid = (int) arcade_request( 'gid' );

    if ( $gid === 0 ) {
        $gid = (int) ( $_SESSION['arcade_current_game'] ?? 0 );
    }

    $token = $_SESSION['arcade_runs'][ $gid ] ?? '';

    if ( ! $uid || $gid <= 0 || ! is_string( $token ) || $token === '' ) {
        http_response_code( 403 );
        exit;
    }

    $game = arcade_game( $gid );

    if ( ! $game || ! arcade_can_play( arcade_category( $game['category_id'] ) ) ) {
        http_response_code( 403 );
        exit;
    }

    $sql = 'SELECT *
            FROM ' . arcade_table( 'runs' ) . '
            WHERE token = ' . arcade_quote( $token ) . '
                AND game_id = ' . $gid . '
                AND user_id = ' . $uid . '
                AND started >= ' . ( time() - 7200 ) . '
            LIMIT 1';

    $run = arcade_one( $sql );

    if ( ! $run ) {
        http_response_code( 403 );
        exit;
    }

    $mode = arcade_request( 'sessdo' );

    if ( $mode === 'burn' ) {
        $pending = $_SESSION['arcade_pending'][ $gid ] ?? array();

        if (
            ! is_array( $pending )
            || ! isset( $pending['microone'], $pending['id'], $pending['run'] )
            || ! is_string( $pending['microone'] )
            || ! is_string( $pending['run'] )
            || ! hash_equals( $pending['microone'], arcade_request( 'microone' ) )
            || ! hash_equals( $pending['run'], $token )
            || arcade_request( 'id' ) !== $pending['id']
            || arcade_request( 'gametime' ) !== (string) $run['started']
        ) {
            http_response_code( 403 );
            exit;
        }

        require_once __DIR__ . '/score.php';

        $saved = arcade_submit_score();

        arcade_redirect(
            array(
                'action' => $saved ? 'scores' : 'browse',
                'gid'    => $saved ?: 0,
                'notice' => $saved ? 'score_saved' : 'score_invalid',
            )
        );
    }

    header( 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8' );

    if ( $mode === 'sessionstart' ) {
        $key = random_int( 100000, 999999 );
        $id  = (string) random_int( 100000, 999999 );

        $_SESSION['arcade_handshake'][ $gid ] = array(
            'id'  => $id,
            'key' => (string) $key,
        );

        echo http_build_query(
            array(
                'sessiontype' => 1,
                'connStatus'  => 1,
                'gametime'    => $run['started'],
                'initbar'     => $key,
                'lastid'      => $id,
            )
        );

        exit;
    }

    if ( $mode === 'permrequest' ) {
        $session = $_SESSION['arcade_handshake'][ $gid ] ?? array();
        $score   = arcade_request( 'score' );

        if (
            ! is_array( $session )
            || ! isset( $session['id'], $session['key'] )
            || arcade_request( 'id' ) !== $session['id']
            || arcade_request( 'fakekey' ) !== $session['key']
            || arcade_request( 'gametime' ) !== (string) $run['started']
            || ! arcade_valid_score( $score )
        ) {
            echo 'validate=0';
            exit;
        }

        $microone = bin2hex( random_bytes( 16 ) );

        $_SESSION['arcade_pending'][ $gid ] = array(
            'id'       => $session['id'],
            'score'    => $score,
            'microone' => $microone,
            'run'      => $token,
        );

        echo http_build_query(
            array(
                'validate' => 1,
                'microone' => $microone,
            )
        );

        exit;
    }

    http_response_code( 400 );

    echo 'validate=0';
    exit;
}