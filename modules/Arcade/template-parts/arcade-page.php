<?php
/**
 * Frontend page shell. @var array $game
 *
 * Theme override: themes/YOUR_THEME/template-parts/arcade/arcade-page.php
 * Values are provided through $args and extracted by the CMS loader.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

echo '<section class="evo-arcade">';
get_template_part( 'arcade-header', null, $args );
get_template_part( $game ? 'arcade-game' : 'arcade-browse', null, $args );
echo '</section>';
