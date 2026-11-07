<?php
/**
 * Favourite form. @var int $uid @var bool $favourite @var string $favourite_url
 *
 * Theme override: themes/YOUR_THEME/template-parts/arcade/arcade-favourite.php
 * Values are provided through $args and extracted by the CMS loader.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

if ( $uid ) {
    echo '<form class="arcade-heart-form" method="post" action="' . arcade_e( $favourite_url ) . '">';

    arcade_csrf();
    $heart_label = arcade_t( $favourite ? 'remove_favourite' : 'add_favourite' );
    echo '<button type="submit" class="tp-tooltip arcade-heart-button' . ( $favourite ? ' is-active' : '' )
        . '" aria-label="' . arcade_e( $heart_label ) . '" title="' . arcade_e( $heart_label )
        . '" aria-pressed="' . ( $favourite ? 'true' : 'false' ) . '">';
    echo '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></svg>';
    echo '</button>';

    echo '</form>';
}
