<?php
/**
 * Arcade top players folder block: avatars and recorded player play totals.
 *
 * Uses the four-table Arcade module and its category access checks. One user
 * is shown per panel, with the top five players ranked by submitted plays.
 * CSS is loaded by the CMS folder-block loader; JavaScript uses the footer queue.
 *
 * @package Arcade
 * @version 1.0.0
 */

defined( 'BLOCK_FILE' ) || exit;

if ( ! defined( 'EVO_ARCADE_TOP_USERS_VERSION' ) ) {
    define( 'EVO_ARCADE_TOP_USERS_VERSION', '1.0.0' );
}

global $db, $prefix, $currentlang;

// Keep repeated block instances isolated from the surrounding CMS loader.
( static function ( $block_id ) use ( $db, $prefix, $currentlang ) {

    $arcade_top_users_language = array();

    require __DIR__ . '/language/lang-english.php';

    if ( is_string( $currentlang ) && $currentlang !== 'english'
        && preg_match( '/\A[A-Za-z0-9_-]+\z/', $currentlang ) ) {
        $translation = __DIR__ . '/language/lang-' . $currentlang . '.php';
        if ( is_file( $translation ) ) {
            require $translation;
        }
    }

    $labels = $arcade_top_users_language;
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
                    $players = arcade_rows(
                        'SELECT u.*, totals.total_plays FROM `' . $prefix . '_users` u INNER JOIN (' .
                        ' SELECT s.user_id,SUM(s.plays) AS total_plays FROM ' . arcade_table( 'scores' ) . ' s' .
                        ' INNER JOIN ' . arcade_table( 'content' ) . " g ON g.record_type='game' AND g.id=s.game_id" .
                        ' WHERE g.active=1 AND g.category_id IN (' . implode( ',', $allowed ) . ')' .
                        ' GROUP BY s.user_id HAVING SUM(s.plays)>0' .
                        ') totals ON totals.user_id=u.user_id WHERE u.user_id<>' . (int) ANONYMOUS .
                        ' ORDER BY totals.total_plays DESC,u.user_id ASC LIMIT 5'
                    );
                }
            } else {
                $status = $labels['unavailable'];
            }
        } catch ( Throwable $e ) {
            error_log( 'Arcade top users block: ' . $e->getMessage() );
            $status = $labels['unavailable'];
        }
    } else {
        $status = $labels['unavailable'];
    }
    evo_include_script(
        'evo-arcade-top-users',
        'blocks/ArcadeTopUsers/assets/arcade-top-users.js',
        EVO_ARCADE_TOP_USERS_VERSION,
        true
    );
    $panels = array_chunk( $players, 1 );
    ?>
    <section class="arcade-top-users" data-arcade-top-users>
        <header class="arcade-top-users-header">
            <!-- <h3><?php echo $escape( $labels['title'] ); ?></h3> -->
            <?php if ( count( $panels ) > 1 ) : ?>
                <nav class="arcade-top-users-nav" aria-label="<?php echo $escape( $labels['navigation'] ); ?>" hidden>
                    <button type="button" data-arcade-prev aria-label="<?php echo $escape( $labels['previous'] ); ?>">&#8249;</button>
                    <button type="button" data-arcade-next aria-label="<?php echo $escape( $labels['next'] ); ?>">&#8250;</button>
                </nav>
            <?php endif; ?>
        </header>
        <?php if ( ! $players ) : ?>
            <p class="arcade-top-users-empty"><?php echo $escape( $status ); ?></p>
        <?php endif; ?>
        <div class="arcade-top-users-panels" aria-live="polite">
            <?php foreach ( $panels as $panel ) : ?>
                <div class="arcade-top-users-grid" data-arcade-panel>
                    <?php foreach ( $panel as $player ) :
                        $avatar = get_user_avatar( (int) $player['user_id'], $player );
                        $profile = 'modules.php?name=Profile&mode=viewprofile&u=' . (int) $player['user_id'];
                        ?>
                        <article class="arcade-top-users-player">
                            <a class="arcade-top-users-profile" href="<?php echo $escape( $profile ); ?>">
                                <?php if ( $avatar ) : ?>
                                    <img src="<?php echo escape_url( $avatar ); ?>" alt="" loading="lazy">
                                <?php else : ?>
                                    <span class="arcade-top-users-avatar" aria-hidden="true">&#9733;</span>
                                <?php endif; ?>
                                <span class="arcade-top-users-name"><?php echo $escape( $player['username'] ); ?></span>
                            </a>
                            <strong class="arcade-top-users-total"><?php echo $escape( number_format( (int) $player['total_plays'] ) ); ?></strong>
                            <span class="arcade-top-users-caption"><?php echo $escape( $labels['played'] ); ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
} )( (string) ( $bid ?? 'default' ) );
