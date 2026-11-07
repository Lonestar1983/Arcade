<?php
/**
 * Comment list and form. @var array $comments @var int $uid @var string $comment_url
 *
 * Theme override: themes/YOUR_THEME/template-parts/arcade/arcade-comments.php
 * Values are provided through $args and extracted by the CMS loader.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

echo '<h2>' . arcade_e( arcade_t( 'comments' ) ) . '</h2>';

foreach ( $comments as $comment ) {
    $username = $comment['username'] ?? arcade_t( 'unknown_user' );

    echo '<article class="arcade-comment">';
    echo '<div class="arcade-comment-heading">';
    echo '<strong>' . arcade_e( $username ) . '</strong>';
    if ( ! empty( $comment['created'] ) ) {
        echo '<time datetime="' . arcade_e( gmdate( 'c', (int) $comment['created'] ) ) . '">'
            . arcade_e( date( 'j M Y, H:i', (int) $comment['created'] ) ) . '</time>';
    }
    echo '</div>';
    echo '<p>' . nl2br( arcade_e( arcade_comment_text( $comment['message'] ) ) ) . '</p>';
    echo '</article>';
}

if ( $uid ) {
    echo '<form method="post" action="' . arcade_e( $comment_url ) . '">';

    arcade_csrf();

    echo '<label>' . arcade_e( arcade_t( 'comment' ) );
    echo '<textarea name="message" maxlength="2000" required></textarea>';
    echo '</label>';

    arcade_button( 'post_comment' );

    echo '</form>';
}
