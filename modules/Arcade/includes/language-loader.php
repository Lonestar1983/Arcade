<?php
/**
 * Load Arcade translations with English defaults.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

/**
 * Load English translations, then overlay the current language when available.
 *
 * Only letters, numbers, underscores and hyphens are permitted in locale names.
 * Missing or invalid locales retain the English translations.
 *
 * @global array  $lang_new   Module translation arrays.
 * @global string $currentlang Current site language.
 *
 * @return void
 */
function arcade_load_language() {
    global $lang_new, $currentlang;

    $language_directory = __DIR__ . '/../language/';

    require $language_directory . 'lang-english.php';

    $locale = (string) ( $currentlang ?? 'english' );

    if (
        $locale === 'english'
        || preg_match( '/^[a-zA-Z0-9_-]+$/D', $locale ) !== 1
    ) {
        return;
    }

    $file = $language_directory . 'lang-' . $locale . '.php';

    if ( is_file( $file ) ) {
        include $file;
    }
}

arcade_load_language();
