<?php
/**
 * Arcade heading, navigation and notices. @var array $settings @var string $notice @var int $uid
 *
 * Theme override: themes/YOUR_THEME/template-parts/arcade/arcade-header.php
 * Values are provided through $args and extracted by the CMS loader.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

echo '<header class="arcade-header">';
echo '<div>';
echo '<h1>' . arcade_e( $settings['title'] ) . '</h1>';
echo '<p>' . arcade_e( $settings['announcement'] ) . '</p>';
echo '</div>';
echo '<nav>';
echo '<a href="' . arcade_e( arcade_url() ) . '">'
    . arcade_e( arcade_t( 'all_games' ) ) . '</a>';

echo '<a href="' . arcade_e(
    arcade_url(
        array(
            'action' => 'favourites',
        )
    )
) . '">' . arcade_e( arcade_t( 'favourites' ) ) . '</a>';

echo '<a href="' . arcade_e(
    arcade_url(
        array(
            'action' => 'hall',
        )
    )
) . '">' . arcade_e( arcade_t( 'hall' ) ) . '</a>';

echo '</nav>';
echo '</header>';


if ( in_array( $notice, array( 'score_saved', 'score_invalid' ), true ) ) {
    echo '<p role="status" class="arcade-notice">'
        . arcade_e( arcade_t( $notice ) ) . '</p>';
}

if ( ! $uid ) {
    echo '<p>' . arcade_e( arcade_t( 'login' ) ) . '</p>';
}
