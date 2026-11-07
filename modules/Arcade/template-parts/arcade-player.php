<?php
/**
 * Player container and controls. @var string $action @var string $player_json @var string $play_url
 *
 * Theme override: themes/YOUR_THEME/template-parts/arcade/arcade-player.php
 * Values are provided through $args and extracted by the CMS loader.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

if ( $action === 'play' ) {
    echo '<label for="arcade-size">' . arcade_e( arcade_t( 'game_size' ) ) .
        '<select id="arcade-size">';
    foreach ( array( 'fit', 'larger', 'original' ) as $size_mode ) {
        echo '<option value="' . arcade_e( $size_mode ) . '">' .
            arcade_e( arcade_t( 'game_size_' . $size_mode ) ) . '</option>';
    }
    echo '</select></label>';
    echo '<div id="arcade-player" data-player="'
        . arcade_e( $player_json ) . '"></div>';

    echo '<div class="arcade-game-actions">';
    echo '<button type="button" id="arcade-fullscreen">'
        . arcade_e( arcade_t( 'fullscreen' ) ) . '</button> ';

    get_template_part( 'arcade-favourite', null, $args );

    echo '<a class="arcade-button" href="' . arcade_e( $play_url ) . '">'
        . arcade_e( arcade_t( 'restart' ) ) . '</a>';
    echo '</div>';
} else {
    echo '<a class="arcade-button" href="' . arcade_e( $play_url ) . '">'
        . arcade_e( arcade_t( 'play' ) ) . '</a>';
}
