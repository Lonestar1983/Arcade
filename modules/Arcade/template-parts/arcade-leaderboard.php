<?php
/**
 * Leaderboard markup. @var array $scores
 *
 * Theme override: themes/YOUR_THEME/template-parts/arcade/arcade-leaderboard.php
 * Values are provided through $args and extracted by the CMS loader.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

echo '<h2>' . arcade_e( arcade_t( 'leaderboard' ) ) . '</h2>';
echo '<ol class="arcade-player-cards">';

foreach ( $scores as $index => $score ) {
    $username = $score['username'] ?? arcade_t( 'unknown_user' );
    $rank = $index + 1;

    echo '<li class="arcade-player-card' . ( $rank === 1 ? ' is-first' : '' ) . '">';
    echo '<span class="arcade-player-rank">#' . $rank . '</span>';

    if ( ! empty( $score['avatar_url'] ) ) {
        echo '<img class="arcade-player-avatar" loading="lazy" src="'
            . arcade_e( $score['avatar_url'] ) . '" alt="">';
    } else {
        echo '<span class="arcade-player-avatar arcade-player-avatar-fallback" aria-hidden="true">'
            . '&#128100;</span>';
    }

    echo '<span class="arcade-player-name">' . arcade_e( $username ) . '</span>';
    echo '<strong class="arcade-player-score">'
        . arcade_e( arcade_format_score( $score['score'] ) ) . '</strong>';
    echo '</li>';
}

echo '</ol>';
