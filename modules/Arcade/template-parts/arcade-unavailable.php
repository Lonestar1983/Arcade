<?php
/**
 * Installation unavailable message.
 *
 * Theme override: themes/YOUR_THEME/template-parts/arcade/arcade-unavailable.php
 * Values are provided through $args and extracted by the CMS loader.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

echo '<section class="evo-arcade"><p>' . arcade_e( arcade_t( 'not_installed' ) ) . '</p></section>';
