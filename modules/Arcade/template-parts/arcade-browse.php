<?php
/**
 * Game browser, favourites and hall. @var array $games
 *
 * Theme override: themes/YOUR_THEME/template-parts/arcade/arcade-browse.php
 * Values are provided through $args and extracted by the CMS loader.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

get_template_part( 'arcade-filters', null, $args );
get_template_part( 'arcade-pagination', null, $args );
echo '<div class="arcade-grid">';
if ( ! $games ) {
    echo '<p>' . arcade_e( arcade_t( 'empty' ) ) . '</p>';
}
foreach ( $games as $listed_game ) {
    get_template_part( 'arcade-card', null, array(
        'listed_game' => $listed_game,
        'picture'     => $listed_game['picture_url'],
        'play_url'    => $listed_game['play_url'],
        'scores_url'  => $listed_game['scores_url'],
        'action'      => $action,
    ) );
}
echo '</div>';
get_template_part( 'arcade-pagination', null, $args );
