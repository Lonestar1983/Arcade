<?php
/**
 * Arcade frontend controller and template data preparation.
 *
 * Handles game browsing, player setup, score callbacks, favourites,
 * comments, category filtering and pagination.
 *
 * @package Arcade
 * @version 1.0.0
 */

defined( 'NUKE_EVO' ) || exit;

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/language-loader.php';

arcade_session();

evo_include_style(
    'evo-arcade',
    'modules/Arcade/assets/css/arcade.css',
    EVO_ARCADE_VERSION
);

$action = arcade_request( 'action', 'browse' );

try {
    if ( ! arcade_installed() ) {
        $arcade_buffer_level = ob_get_level();
        ob_start();
        get_header();

        get_template_part( 'arcade-unavailable' );

        get_footer();
        ob_end_flush();

        return;
    }

    $uid = arcade_uid();
    $gid = (int) arcade_request( 'gid' );

    // Handle legacy protocol callbacks before producing page output.
    if ( $action === 'pnflash' ) {
        require_once __DIR__ . '/includes/pnflash.php';

        arcade_pnflash();
    }

    if ( $action === 'handshake' ) {
        require_once __DIR__ . '/includes/handshake.php';

        arcade_handshake();
    }

    if ( $action === 'score' ) {
        require_once __DIR__ . '/includes/score.php';

        $saved = arcade_submit_score();

        arcade_redirect(
            array(
                'action' => $saved ? 'scores' : 'browse',
                'gid'    => $saved ?: 0,
                'notice' => $saved ? 'score_saved' : 'score_invalid',
            )
        );
    }

    // Process authenticated favourite and comment submissions.
    if ( in_array( $action, array( 'favourite', 'comment' ), true ) ) {
        arcade_check_csrf();

        $game = arcade_game( $gid );

        if (
            ! $uid
            || ! $game
            || ! arcade_can_play( arcade_category( $game['category_id'] ) )
        ) {
            $error_page = new Evo_Error_Page( 403 );
            $error_page->render();
            exit;
        }

        if ( $action === 'favourite' ) {
            $exists = arcade_one(
                'SELECT *
                 FROM ' . arcade_table( 'favourites' ) . '
                 WHERE user_id = ' . $uid . '
                     AND game_id = ' . $gid
            );

            if ( $exists ) {
                $sql = 'DELETE FROM ' . arcade_table( 'favourites' ) . '
                        WHERE user_id = ' . $uid . '
                            AND game_id = ' . $gid;
            } else {
                $sql = 'INSERT INTO ' . arcade_table( 'favourites' ) . '
                            (user_id, game_id)
                        VALUES (' . $uid . ', ' . $gid . ')';
            }

            arcade_db( $sql );
        } else {
            $message = trim( arcade_comment_text( arcade_request( 'message' ) ) );

            if (
                $message !== ''
                && strlen( $message ) <= 2000
                && time() - (int) ( $_SESSION['arcade_comment_time'] ?? 0 ) >= 15
            ) {
                $sql = 'INSERT INTO ' . arcade_table( 'content' ) . '
                            (record_type, game_id, user_id, message, created)
                        VALUES (
                            \'comment\', ' . $gid . ',
                            ' . $uid . ',
                            ' . arcade_quote( $message ) . ',
                            ' . time() . '
                        )';

                arcade_db( $sql );

                $_SESSION['arcade_comment_time'] = time();
            }
        }

        arcade_redirect(
            array(
                'action' => 'scores',
                'gid'    => $gid,
            )
        );
    }

    $settings = arcade_settings();
    $game     = array();
    $payload  = array();

    // Validate the selected game and prepare a fresh player session.
    if ( in_array( $action, array( 'play', 'scores' ), true ) ) {
        $game = arcade_game( $gid );

        if ( ! $game ) {
            $error_page = new Evo_Error_Page( 404 );
            $error_page->render();
            exit;
        } elseif ( ! arcade_can_play( arcade_category( $game['category_id'] ) ) ) {
            $error_page = new Evo_Error_Page( 403 );
            $error_page->render();
            exit;
        } elseif ( $action === 'play' ) {
            $url = arcade_file( $game['swf'] );

            if ( ! $url || ! is_file( __DIR__ . '/games/' . $game['swf'] ) ) {
                $error_page = new Evo_Error_Page( 404 );
                $error_page->render();
                exit;
            } else {
                $token = bin2hex( random_bytes( 32 ) );
                $hash  = bin2hex( random_bytes( 16 ) );

                if ( $uid ) {
                    // Expire old runs and replace this user's run for the game.
                    arcade_db(
                        'DELETE FROM ' . arcade_table( 'runs' ) . '
                         WHERE started < ' . ( time() - 7200 ) . '
                             OR (
                                 user_id = ' . $uid . '
                                 AND game_id = ' . $gid . '
                             )'
                    );

                    arcade_db(
                        'INSERT INTO ' . arcade_table( 'runs' ) . '
                            (token, legacy_hash, game_id, user_id, started)
                         VALUES (
                             ' . arcade_quote( $token ) . ',
                             ' . arcade_quote( $hash ) . ',
                             ' . $gid . ',
                             ' . $uid . ',
                             ' . time() . '
                         )'
                    );

                    unset(
                        $_SESSION['arcade_score_receipts'][ $gid ],
                        $_SESSION['arcade_pending'][ $gid ],
                        $_SESSION['arcade_handshake'][ $gid ]
                    );

                    $_SESSION['arcade_runs'][ $gid ] = $token;
                    $_SESSION['arcade_current_game'] = $gid;
                }

                arcade_db(
                    'UPDATE ' . arcade_table( 'content' ) . "
                     SET plays = plays + 1
                     WHERE record_type='game' AND id = " . $gid
                );

                $payload = array(
                    'url'      => $url,
                    'width'    => (int) $game['width'],
                    'height'   => (int) $game['height'],
                    'type'     => (int) $game['type'],
                    'uid'      => $uid,
                    'gid'      => $gid,
                    'gamehash' => $hash,
                    'token'    => $token,
                    'missing'  => arcade_t( 'ruffle_missing' ),
                    'error'    => arcade_t( 'ruffle_error' ),
                );

                evo_include_script(
                    'evo-arcade-player',
                    'modules/Arcade/assets/js/player.js',
                    EVO_ARCADE_VERSION,
                    true
                );
            }
        }
    }

    // Fetch all page data before rendering; theme templates contain no database queries.
    $view = array(
        'settings' => $settings,
        'action'   => $action,
        'uid'      => $uid,
        'gid'      => $gid,
        'game'     => $game,
        'notice'   => arcade_request( 'notice' ),
    );

    if ( $game ) {
        global $prefix;

        $view['play_url'] = arcade_url( array( 'action' => 'play', 'gid' => $gid ) );
        $view['player_json'] = json_encode(
            $payload,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS
        );
        $view['favourite'] = $uid ? (bool) arcade_one(
            'SELECT * FROM ' . arcade_table( 'favourites' ) .
            ' WHERE user_id=' . $uid . ' AND game_id=' . $gid
        ) : false;
        $view['favourite_url'] = arcade_url( array( 'action' => 'favourite', 'gid' => $gid ) );
        $view['comment_url'] = arcade_url( array( 'action' => 'comment', 'gid' => $gid ) );
        $view['scores'] = arcade_scores( $gid );
        $view['comments'] = arcade_rows(
            'SELECT c.*, u.username FROM ' . arcade_table( 'content' ) . ' AS c' .
            ' LEFT JOIN `' . $prefix . '_users` AS u ON u.user_id=c.user_id' .
            " WHERE c.record_type='comment' AND c.game_id=" . $gid . ' ORDER BY c.created DESC LIMIT 50'
        );
    } else {
        $categories = arcade_rows(
            'SELECT *
             FROM ' . arcade_table( 'content' ) . "
             WHERE record_type='category'
             ORDER BY position, name"
        );

        $allowed = array();

        foreach ( $categories as $category ) {
            if ( arcade_can_play( $category ) ) {
                $allowed[] = (int) $category['id'];
            }
        }

        $cid = max( 0, (int) arcade_request( 'cid' ) );
        $q   = trim( substr( arcade_request( 'q' ), 0, 100 ) );

        // Only these fixed SQL expressions may be selected by a request.
        $sort_orders = array(
            'default'    => 'g.position ASC, g.name ASC, g.id ASC',
            'plays_asc'  => 'g.plays ASC, g.name ASC, g.id ASC',
            'plays_desc' => 'g.plays DESC, g.name ASC, g.id ASC',
            'score_asc'  => 'highest_score ASC, g.name ASC, g.id ASC',
            'score_desc' => 'highest_score DESC, g.name ASC, g.id ASC',
        );
        $sort = arcade_request( 'sort', 'default' );

        if ( ! isset( $sort_orders[ $sort ] ) ) {
            $sort = 'default';
        }

        $where = "g.record_type='game' AND g.active = 1 AND g.category_id IN ("
            . implode( ',', $allowed ?: array( 0 ) ) . ')';

        if ( $cid ) {
            $where .= ' AND g.category_id = ' . $cid;
        }

        if ( $q !== '' ) {
            $search = str_replace(
                array(
                    '\\',
                    '%',
                    '_',
                ),
                array(
                    '\\\\',
                    '\\%',
                    '\\_',
                ),
                $q
            );

            $where .= ' AND g.name LIKE ' . arcade_quote( '%' . $search . '%' );
        }

        if ( $action === 'favourites' ) {
            $where .= ' AND EXISTS (
                SELECT 1
                FROM ' . arcade_table( 'favourites' ) . ' AS f
                WHERE f.game_id = g.id
                    AND f.user_id = ' . $uid . '
            )';
        }

        $limit  = max( 1, min( 60, (int) $settings['per_page'] ) );
        $page   = max( 1, min( 100000, (int) arcade_request( 'page', '1' ) ) );
        $offset = ( $page - 1 ) * $limit;

        $count = arcade_one(
            'SELECT COUNT(*) AS total
             FROM ' . arcade_table( 'content' ) . ' AS g
             WHERE ' . $where
        );

        // Match the shared paginator's page bounds before selecting database rows.
        $pages  = max( 1, (int) ceil( (int) $count['total'] / $limit ) );
        $page   = min( $page, $pages );
        $offset = ( $page - 1 ) * $limit;

        // Aggregate before pagination so score sorting covers every matching game.
        // Unplayed games use zero; ties always resolve to a stable game ID.
        $score_select = ', COALESCE(best.highest_score, 0) AS highest_score';
        $score_join = ' LEFT JOIN (
            SELECT game_id, MAX(score) AS highest_score
            FROM ' . arcade_table( 'scores' ) . '
            GROUP BY game_id
        ) AS best ON best.game_id = g.id';

        $games = arcade_rows(
            'SELECT g.*' . $score_select . ', EXISTS (
                 SELECT 1 FROM ' . arcade_table( 'favourites' ) . ' AS fav
                 WHERE fav.game_id = g.id AND fav.user_id = ' . $uid . '
             ) AS is_favourite

             FROM ' . arcade_table( 'content' ) . ' AS g' . $score_join . '
             WHERE ' . $where . '
             ORDER BY ' . $sort_orders[ $sort ] . '
             LIMIT ' . $offset . ', ' . $limit
        );


        foreach ( $games as &$listed_game ) {
            $listed_game['picture_url'] = arcade_file( $listed_game['picture'], true );
            $listed_game['play_url']    = arcade_url( array( 'action' => 'play', 'gid' => $listed_game['id'] ) );
            $listed_game['scores_url']  = arcade_url( array( 'action' => 'scores', 'gid' => $listed_game['id'] ) );
            $listed_game['scores']      = $action === 'hall' ? arcade_scores( $listed_game['id'] ) : array();
        }
        unset( $listed_game );

        $view = array_merge( $view, array(
            'categories' => $categories,
            'allowed'    => $allowed,
            'cid'        => $cid,
            'q'          => $q,
            'sort'       => $sort,
            'games'      => $games,
            'count'      => $count,
            'limit'      => $limit,
            'page'       => $page,
        ) );
    }

    // Buffer the complete HTML response so a template error can use the CMS error page.
    $arcade_buffer_level = ob_get_level();
    ob_start();
    get_header();
    opentable();
    get_template_part( 'arcade-page', null, $view );
    closetable();
    get_footer();
    ob_end_flush();
} catch ( Throwable $e ) {
    if ( isset( $arcade_buffer_level ) ) {
        while ( ob_get_level() > $arcade_buffer_level ) {
            ob_end_clean();
        }
    }
    error_log( 'Arcade: ' . $e->getMessage() );
    $error_page = new Evo_Error_Page( 500 );
    $error_page->render();
    exit;
}
