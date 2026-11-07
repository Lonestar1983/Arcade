<?php
/**
 * Return legacy game-hash data for an authenticated Arcade run.
 *
 * Uses the requested game ID, falling back to the current session game.
 * The run must belong to the current user and have started within two hours.
 * Invalid requests receive an HTTP 403 response.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

require_once __DIR__ . '/includes/functions.php';

arcade_session();

header( 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8' );

$gid = (int) arcade_request(
    'gid',
    (string) ( $_SESSION['arcade_current_game'] ?? 0 )
);

$user_id = arcade_uid();
$token   = $_SESSION['arcade_runs'][ $gid ] ?? '';

if ( $gid <= 0 || ! $user_id || ! is_string( $token ) || $token === '' ) {
    http_response_code( 403 );
    exit;
}

// Only accept runs started within the last two hours.
$minimum_started = time() - 7200;

$sql = 'SELECT legacy_hash
        FROM ' . arcade_table( 'runs' ) . '
        WHERE token = ' . arcade_quote( $token ) . '
            AND user_id = ' . $user_id . '
            AND game_id = ' . $gid . '
            AND started >= ' . $minimum_started . '
        LIMIT 1';

$run = arcade_one( $sql );

if ( ! $run ) {
    http_response_code( 403 );
    exit;
}

// Both hash fields support legacy games expecting either parameter name.
echo http_build_query(
    array(
        'gamehash'     => $run['legacy_hash'],
        'gamehash_id'  => $run['legacy_hash'],
        'gid'          => $gid,
        'arcade_token' => $token,
    )
);