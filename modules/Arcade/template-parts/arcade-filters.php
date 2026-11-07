<?php
/**
 * Search, category and sorting controls. @var array $categories @var array $allowed @var int $cid @var string $q @var string $action
 *
 * Theme override: themes/YOUR_THEME/template-parts/arcade/arcade-filters.php
 * Values are provided through $args and extracted by the CMS loader.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

echo '<form class="arcade-filter" method="get">';
echo '<input type="hidden" name="name" value="Arcade">';
echo '<input type="hidden" name="action" value="' . arcade_e( $action ) . '">';
echo '<label>' . arcade_e( arcade_t( 'search' ) );
echo '<input name="q" value="' . arcade_e( $q ) . '">';
echo '</label>';
echo '<label>' . arcade_e( arcade_t( 'category' ) );
echo '<select name="cid">';
echo '<option value="0">' . arcade_e( arcade_t( 'all_games' ) ) . '</option>';

foreach ( $categories as $category ) {
    $category_id = (int) $category['id'];

    if ( ! in_array( $category_id, $allowed, true ) ) {
        continue;
    }

    echo '<option value="' . $category_id . '"'
        . ( $cid === $category_id ? ' selected' : '' ) . '>'
        . arcade_e( $category['name'] ) . '</option>';
}

echo '</select>';
echo '</label>';

echo '<label>' . arcade_e( arcade_t( 'sort_by' ) );
echo '<select name="sort">';

foreach ( array( 'default', 'plays_asc', 'plays_desc', 'score_asc', 'score_desc' ) as $sort_value ) {
    echo '<option value="' . $sort_value . '"'
        . ( ( $sort ?? 'default' ) === $sort_value ? ' selected' : '' ) . '>'
        . arcade_e( arcade_t( 'sort_' . $sort_value ) ) . '</option>';
}

echo '</select>';
echo '</label>';

arcade_button( 'search' );

echo '</form>';
