<?php
/**
 * Single game page. @var array $game @var array $scores
 *
 * Theme override: themes/YOUR_THEME/template-parts/arcade/arcade-game.php
 * Values are provided through $args and extracted by the CMS loader.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

echo '<h2>' . arcade_e( arcade_game_title( $game['name'] ) ) . '</h2>';
echo '<p>' . arcade_e( $game['description'] ) . '</p>';
if ( $action === 'play' ) {
    get_template_part( 'arcade-player', null, $args );
} else {
    echo '<div class="arcade-game-actions">';
    get_template_part( 'arcade-player', null, $args );
    get_template_part( 'arcade-favourite', null, $args );
    echo '</div>';
}
get_template_part( 'arcade-leaderboard', null, array( 'scores' => $scores ) );
get_template_part( 'arcade-comments', null, $args );
