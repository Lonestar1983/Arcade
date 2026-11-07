<?php
/**
 * pnFlashGames LoadVars score adapter.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

/**
 * Reply in the URL-encoded format expected by storeScore_Result().
 *
 * Save/load game data is not implemented and reports failure explicitly.
 * Scores use the shared authenticated, expiring, single-use run transaction.
 *
 * @return void
 */
function arcade_pnflash() {
    header( 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8' );
    header( 'Cache-Control: no-store' );

    $saved = false;

    // LoadVars receives its normal reply; the player checks this receipt separately.
    if ( arcade_request( 'arcade_status' ) === '1' ) {
        $gid = (int) arcade_request( 'arcade_gid' );
        $receipt = $_SESSION['arcade_score_receipts'][ $gid ] ?? array();
        $token = arcade_request( 'arcade_token' );

        $saved = ! empty( $receipt['token'] )
            && $token !== ''
            && hash_equals( $receipt['token'], $token )
            && (int) $receipt['user_id'] === arcade_uid()
            && (int) $receipt['saved'] >= time() - 7200;

        echo 'opSuccess=' . ( $saved ? 'true' : 'false' );
        exit;
    }

    if ( arcade_request( 'func' ) === 'storeScore' ) {
        require_once __DIR__ . '/score.php';

        try {
            // The adapter URL binds the game independently of legacy POST data.
            $gid = $_GET['arcade_gid'] ?? '';
            $saved = is_scalar( $gid ) && ctype_digit( (string) $gid )
                ? arcade_submit_score( 'pnflash', (int) $gid )
                : false;

            if ( $saved ) {
                $_SESSION['arcade_score_receipts'][ (int) $gid ] = array(
                    'token'   => arcade_request( 'arcade_token' ),
                    'user_id' => arcade_uid(),
                    'saved'   => time(),
                );
            }
        } catch ( Throwable $e ) {
            error_log( 'Arcade pnFlashGames: ' . $e->getMessage() );
        }
    }

    echo 'opSuccess=' . ( $saved ? 'true' : 'false' );
    exit;
}
