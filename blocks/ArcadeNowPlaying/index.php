<?php
/**
 * Arcade currently playing folder block: avatars, games and personal bests.
 *
 * Uses the four-table Arcade module and its category access checks. One user
 * is shown per panel, with up to twenty players ordered by their latest CMS session activity.
 * CSS is loaded by the CMS folder-block loader; JavaScript uses the footer queue.
 *
 * @package Arcade
 * @version 1.0.0
 */

defined( 'BLOCK_FILE' ) || exit;

if ( ! defined( 'EVO_ARCADE_NOW_PLAYING_VERSION' ) ) {
    define( 'EVO_ARCADE_NOW_PLAYING_VERSION', '1.0.0' );
}
if ( ! defined( 'EVO_ARCADE_NOW_PLAYING_TIMEOUT' ) ) {
    define( 'EVO_ARCADE_NOW_PLAYING_TIMEOUT', 900 );
}

global $db, $prefix, $currentlang;

// Keep repeated block instances isolated from the surrounding CMS loader.
( static function ( $block_id ) use ( $db, $prefix, $currentlang ) {

    $arcade_now_playing_language = array();

    require __DIR__ . '/language/lang-english.php';

    if ( is_string( $currentlang ) && $currentlang !== 'english'
        && preg_match( '/\A[A-Za-z0-9_-]+\z/', $currentlang ) ) {
        $translation = __DIR__ . '/language/lang-' . $currentlang . '.php';
        if ( is_file( $translation ) ) {
            require $translation;
        }
    }

    $labels = $arcade_now_playing_language;
    $escape = static function ( $value ) {
        return escape_html( (string) $value );
    };

    $players = array();
    $status  = $labels['empty'];
    $helpers = dirname( __DIR__, 2 ) . '/modules/Arcade/includes/functions.php';

    if ( is_file( $helpers ) ) {
        try {

            require_once $helpers;

            if ( arcade_installed() ) {
                $allowed = array();
                foreach ( arcade_rows( 'SELECT * FROM ' . arcade_table( 'content' ) .
                    " WHERE record_type='category'" ) as $category ) {
                    if ( arcade_can_play( $category ) ) {
                        $allowed[] = (int) $category['id'];
                    }
                }
                if ( $allowed ) {
                    // Sessions identify members by uname; decode only the stored URL.
                    // The newest session wins so an older play URL cannot survive navigation.
                    $sessions = arcade_rows(
                        'SELECT u.*,sess.url AS arcade_session_url,sess.time AS arcade_last_seen' .
                        ' FROM `' . $prefix . '_session` sess' .
                        ' INNER JOIN `' . $prefix . '_users` u ON u.username=sess.uname' .
                        " WHERE sess.guest=0 AND sess.module='Arcade'" .
                        ' AND sess.time >= ' . ( time() - EVO_ARCADE_NOW_PLAYING_TIMEOUT ) .
                        ' AND u.user_id<>' . (int) ANONYMOUS .
                        ' AND NOT EXISTS (SELECT 1 FROM `' . $prefix . '_session` newer' .
                        ' WHERE newer.uname=sess.uname AND newer.guest=0 AND newer.time>sess.time)' .
                        ' ORDER BY sess.time DESC,u.user_id ASC'
                    );
                    $active = array();
                    $seen = array();
                    foreach ( $sessions as $member ) {
                        $member_id = (int) $member['user_id'];
                        if ( isset( $seen[ $member_id ] ) ) {
                            continue;
                        }
                        $seen[ $member_id ] = true;
                        $session_url = html_entity_decode( (string) $member['arcade_session_url'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
                        $query = parse_url( $session_url, PHP_URL_QUERY );
                        if ( ! is_string( $query ) ) {
                            continue;
                        }
                        $params = array();
                        parse_str( $query, $params );
                        if ( ( $params['name'] ?? null ) !== 'Arcade'
                            || ( $params['action'] ?? null ) !== 'play'
                            || ! isset( $params['gid'] ) || ! is_string( $params['gid'] )
                            || ! ctype_digit( $params['gid'] ) || (int) $params['gid'] < 1 ) {
                            continue;
                        }
                        $active[ $member_id ] = $member;
                        $active[ $member_id ]['playing_game_id'] = (int) $params['gid'];
                    }
                    if ( $active ) {
                        // Batch the parsed session IDs into one games/scores join; no per-player queries.
                        $pairs = array();
                        foreach ( $active as $member_id => $member ) {
                            $pairs[] = 'SELECT ' . (int) $member_id . ' AS user_id,' .
                                (int) $member['playing_game_id'] . ' AS game_id';
                        }
                        $games = arcade_rows(
                            'SELECT playing.user_id,g.id AS playing_game_id,g.name AS playing_game_name,s.score AS personal_best' .
                            ' FROM (' . implode( ' UNION ALL ', $pairs ) . ') playing' .
                            ' INNER JOIN ' . arcade_table( 'content' ) . " g ON g.record_type='game' AND g.id=playing.game_id" .
                            ' LEFT JOIN ' . arcade_table( 'scores' ) . ' s ON s.user_id=playing.user_id AND s.game_id=playing.game_id' .
                            ' WHERE g.active=1 AND g.category_id IN (' . implode( ',', $allowed ) . ')'
                        );
                        $details = array();
                        foreach ( $games as $game ) {
                            $details[ (int) $game['user_id'] ] = $game;
                        }
                        foreach ( $active as $member_id => $member ) {
                            if ( isset( $details[ $member_id ] ) ) {
                                $players[] = array_merge( $member, $details[ $member_id ] );
                                if ( count( $players ) >= 20 ) {
                                    break;
                                }
                            }
                        }
                    }
                }
            } else {
                $status = $labels['unavailable'];
            }
        } catch ( Throwable $e ) {
            error_log( 'Arcade now playing block: ' . $e->getMessage() );
            $status = $labels['unavailable'];
        }
    } else {
        $status = $labels['unavailable'];
    }
    evo_include_script(
        'evo-arcade-now-playing',
        'blocks/ArcadeNowPlaying/assets/arcade-now-playing.js',
        EVO_ARCADE_NOW_PLAYING_VERSION,
        true
    );
    $panels = array_chunk( $players, 1 );
    ?>
    <section class="arcade-now-playing" data-arcade-now-playing>
        <header class="arcade-now-playing-header">
            <!-- <h3><?php echo $escape( $labels['title'] ); ?></h3> -->
            <?php if ( count( $panels ) > 1 ) : ?>
                <nav class="arcade-now-playing-nav" aria-label="<?php echo $escape( $labels['navigation'] ); ?>" hidden>
                    <button type="button" data-arcade-prev aria-label="<?php echo $escape( $labels['previous'] ); ?>">&#8249;</button>
                    <button type="button" data-arcade-next aria-label="<?php echo $escape( $labels['next'] ); ?>">&#8250;</button>
                </nav>
            <?php endif; ?>
        </header>
        <?php if ( ! $players ) : ?>
            <p class="arcade-now-playing-empty"><?php echo $escape( $status ); ?></p>
        <?php endif; ?>
        <div class="arcade-now-playing-panels" aria-live="polite">
            <?php foreach ( $panels as $panel ) : ?>
                <div class="arcade-now-playing-grid" data-arcade-panel>
                    <?php foreach ( $panel as $player ) :
                        $avatar = get_user_avatar( (int) $player['user_id'], $player );
                        $profile = 'modules.php?name=Profile&mode=viewprofile&u=' . (int) $player['user_id'];
                        ?>
                        <article class="arcade-now-playing-player">
                            <a class="arcade-now-playing-profile" href="<?php echo $escape( $profile ); ?>">
                                <?php if ( $avatar ) : ?>
                                    <img src="<?php echo escape_url( $avatar ); ?>" alt="" loading="lazy">
                                <?php else : ?>
                                    <span class="arcade-now-playing-avatar" aria-hidden="true">&#9733;</span>
                                <?php endif; ?>
                                <span class="arcade-now-playing-name"><?php echo $escape( $player['username'] ); ?></span>
                            </a>
                            <a class="arcade-now-playing-game" href="<?php echo $escape( arcade_url( array( 'action' => 'play', 'gid' => (int) $player['playing_game_id'] ) ) ); ?>"><?php echo $escape( arcade_game_title( $player['playing_game_name'] ) ); ?></a>
                            <strong class="arcade-now-playing-total"><?php echo $escape( $player['personal_best'] === null ? $labels['no_score'] : rtrim( rtrim( number_format( (float) $player['personal_best'], 8, '.', ',' ), '0' ), '.' ) ); ?></strong>
                            <span class="arcade-now-playing-caption"><?php echo $escape( $labels['played'] ); ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
} )( (string) ( $bid ?? 'default' ) );
