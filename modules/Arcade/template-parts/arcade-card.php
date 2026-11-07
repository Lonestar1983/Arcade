<?php
/**
 * One game card. @var array $listed_game @var string $picture @var string $play_url @var string $scores_url @var string $action
 *
 * Theme override: themes/YOUR_THEME/template-parts/arcade/arcade-card.php
 * Values are provided through $args and extracted by the CMS loader.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

echo '<article class="arcade-card">';

if ( $picture ) {
    echo '<img loading="lazy" src="' . arcade_e( $picture ) . '" alt="">';
}

echo '<h2>' . arcade_e( arcade_game_title( $listed_game['name'] ) ) . '</h2>';
if ( '' !== $listed_game['description'] ) {
    echo '<p>' . arcade_e( $listed_game['description'] ) . '</p>';
}
echo '<small>' . arcade_e( arcade_t( 'plays' ) )
    . ': ' . (int) $listed_game['plays'] . '</small>';
echo '<small>' . arcade_e( arcade_t( 'highest_score' ) )
    . ': ' . arcade_e( arcade_format_score( $listed_game['highest_score'] ?? 0 ) ) . '</small>';

echo '<div class="arcade-card-actions">';
echo '<a class="arcade-button" href="' . arcade_e( $play_url ) . '">'
    . arcade_e( arcade_t( 'play_short' ) ) . '</a>';
if ( arcade_uid() ) {
    $is_favourite = ! empty( $listed_game['is_favourite'] );
    $heart_label = arcade_t( $is_favourite ? 'remove_favourite' : 'add_favourite' );
    echo '<form class="arcade-heart-form" method="post" action="'
        . arcade_e( arcade_url( array( 'action' => 'favourite', 'gid' => $listed_game['id'] ) ) ) . '">';
    arcade_csrf();
    echo '<button type="submit" class="tp-tooltip arcade-heart-button' . ( $is_favourite ? ' is-active' : '' )
        . '" aria-label="' . arcade_e( $heart_label ) . '" title="' . arcade_e( $heart_label )
        . '" aria-pressed="' . ( $is_favourite ? 'true' : 'false' ) . '">';
    echo '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></svg>';
    echo '</button></form>';
}
echo '<a class="arcade-leaderboard-link" href="' . arcade_e( $scores_url ) . '">'
    . arcade_e( arcade_t( 'leaderboard' ) ) . '</a>';
echo '</div>';

if ( $action === 'hall' ) {
    get_template_part( 'arcade-leaderboard', null, array( 'scores' => $listed_game['scores'] ) );
}

echo '</article>';
