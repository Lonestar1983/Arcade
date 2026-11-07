<?php
/**
 * Standalone Evolution Xtreme Arcade helpers.
 * Based on the PHPNuke Arcade port and giefca's phpBB Arcade.
 * @package Arcade
 * @version 1.0.0
 */

defined( 'NUKE_EVO' ) || exit;

// Release version shared by the module's CSS and JavaScript.
if ( ! defined( 'EVO_ARCADE_VERSION' ) ) {
    define( 'EVO_ARCADE_VERSION', '1.0.0' );
}

/**
 * Execute an Arcade database query.
 *
 * @param string $sql SQL query.
 * @return mixed Database query result.
 * @throws RuntimeException If the query fails.
 */
function arcade_db( $sql ) {
    global $db;

    $result = $db->sql_query( $sql );

    if ( false === $result ) {
        throw new RuntimeException( 'Arcade database operation failed.' );
    }

    return $result;
}

/**
 * Retrieve all rows returned by a query.
 *
 * @param string $sql SQL query.
 * @return array Database rows.
 */
function arcade_rows( $sql ) {
    global $db;

    $result = arcade_db( $sql );
    $rows   = array();

    try {
        while ( $row = $db->sql_fetchrow( $result ) ) {
            $rows[] = $row;
        }
    } finally {
        $db->sql_freeresult( $result );
    }

    return $rows;
}

/**
 * Retrieve the first row returned by a query.
 *
 * @param string $sql SQL query.
 * @return array First database row, or an empty array.
 */
function arcade_one( $sql ) {
    global $db;

    $result = arcade_db( $sql );

    try {
        $row = $db->sql_fetchrow( $result );
    } finally {
        $db->sql_freeresult( $result );
    }

    return $row ? $row : array();
}

/**
 * Build a validated Arcade table identifier.
 *
 * @param string $name Arcade table suffix.
 * @return string Quoted table identifier.
 * @throws RuntimeException If the table name or prefix is invalid.
 */
function arcade_table( $name ) {
    global $prefix;

    $allowed = array(
        'content',
        'scores',
        'favourites',
        'runs',
    );

    if (
        ! in_array( $name, $allowed, true )
        || preg_match( '/^[a-zA-Z0-9_]+$/D', (string) $prefix ) !== 1
    ) {
        throw new RuntimeException( 'Invalid Arcade table.' );
    }

    return '`' . $prefix . '_arcade_' . $name . '`';
}

/**
 * Escape and quote a database string value.
 *
 * @param mixed $value Value to escape.
 * @return string Quoted SQL string.
 * @throws RuntimeException If the database escape API is unavailable.
 */
function arcade_quote( $value ) {
    global $db;

    if ( ! is_callable( array( $db, 'sql_escapestring' ) ) ) {
        throw new RuntimeException( 'The Evo database escape API is required.' );
    }

    return "'" . $db->sql_escapestring( (string) $value ) . "'";
}

/**
 * Escape a value for HTML output.
 *
 * @param mixed $value Value to escape.
 * @return string Escaped value.
 */
function arcade_e( $value ) {
    return escape_html( (string) $value );
}

/**
 * Remove legacy request escaping from a game title.
 *
 * Normalises backslashes before quotes, including already stored titles.
 * Other backslashes are preserved. SQL and HTML escaping remain separate.
 *
 * @param string $title Game title.
 * @return string Title without legacy quote escaping.
 */
function arcade_game_title( $title ) {
    return preg_replace( '/\\\\+(?=[\'"])/', '', (string) $title );
}

/**
 * Remove legacy request escaping before comment quotes.
 *
 * Also used when displaying older comments saved with escaped quotes.
 * Preserve other backslashes, and escape SQL/HTML separately afterwards.
 *
 * @param string $message Comment text.
 * @return string Comment without legacy quote escaping.
 */
function arcade_comment_text( $message ) {
    return preg_replace( '/\\\\+(?=[\'"])/', '', (string) $message );
}

/**
 * Retrieve an Arcade translation.
 *
 * @param string $key Translation key.
 * @return string Translated text, or the key when unavailable.
 */
function arcade_t( $key ) {
    global $lang_new;

    return $lang_new['arcade'][ $key ] ?? $key;
}

/**
 * Retrieve a scalar request value, preferring POST over GET.
 *
 * @param string $key     Request parameter name.
 * @param mixed  $default Fallback value.
 * @return string Request value or scalar fallback.
 */
function arcade_request( $key, $default = '' ) {
    $value = $_POST[ $key ] ?? $_GET[ $key ] ?? $default;

    if ( is_scalar( $value ) ) {
        return (string) $value;
    }

    return is_scalar( $default ) ? (string) $default : '';
}

/**
 * Retrieve the current authenticated user's ID.
 *
 * @return int User ID, or zero for guests.
 */
function arcade_uid() {
    global $userinfo;

    if ( ! is_user() ) {
        return 0;
    }

    return max( 0, (int) ( $userinfo['user_id'] ?? 0 ) );
}

/**
 * Build an Arcade module URL.
 *
 * @param array $args Query parameters.
 * @return string Module URL.
 */
function arcade_url( $args = array() ) {
    $args = array_merge(
        array(
            'name' => 'Arcade',
        ),
        $args
    );

    return 'modules.php?' . http_build_query( $args );
}

/**
 * Redirect to an Arcade module page and stop execution.
 *
 * @param array $args Query parameters.
 * @return void
 */
function arcade_redirect( $args = array() ) {
    header( 'Location: ' . arcade_url( $args ), true, 303 );
    exit;
}

/**
 * Ensure a PHP session is active.
 *
 * @return void
 * @throws RuntimeException If the session cannot be started.
 */
function arcade_session() {
    if ( session_status() === PHP_SESSION_ACTIVE ) {
        return;
    }

    if ( ! session_start() ) {
        throw new RuntimeException( 'Unable to start session.' );
    }
}

/**
 * Display the Arcade CSRF token as a hidden input.
 *
 * @return void
 */
function arcade_csrf() {
    arcade_session();

    if (
        ! isset( $_SESSION['arcade_csrf'] )
        || ! is_string( $_SESSION['arcade_csrf'] )
        || $_SESSION['arcade_csrf'] === ''
    ) {
        $_SESSION['arcade_csrf'] = bin2hex( random_bytes( 32 ) );
    }

    echo '<input type="hidden" name="arcade_csrf" value="'
        . arcade_e( $_SESSION['arcade_csrf'] ) . '">';
}

/**
 * Validate the CSRF token submitted through POST.
 *
 * Invalid requests receive an HTTP 403 response and stop execution.
 *
 * @return void
 */
function arcade_check_csrf() {
    arcade_session();

    $session_token = $_SESSION['arcade_csrf'] ?? '';
    $request_token = $_POST['arcade_csrf'] ?? '';

    if (
        ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST'
        || ! is_string( $session_token )
        || $session_token === ''
        || ! is_string( $request_token )
        || ! hash_equals( $session_token, $request_token )
    ) {
        http_response_code( 403 );
        exit ( arcade_e( arcade_t( 'invalid_request' ) ) );
    }
}

/**
 * Retrieve Arcade settings, applying defaults for missing values.
 *
 * @return array Arcade settings.
 */
function arcade_settings() {
    $defaults = array(
        'title'        => 'Arcade',
        'announcement' => '',
        'per_page'     => '12',
    );
    $stored = get_evo_option( 'arcade_settings', array() );

    return array_merge( $defaults, is_array( $stored ) ? $stored : array() );
}

/**
 * Persist serialised module options and invalidate the CMS configuration cache.
 *
 * @param array $settings Module settings, including the storage version.
 * @return void
 * @throws RuntimeException On a database write failure.
 */
function arcade_save_settings( $settings ) {
    global $prefix, $evoconfig, $cache;

    // Validate the prefix using the normal identifier helper first.
    arcade_table( 'content' );
    $table = '`' . $prefix . '_evolution`';
    $value = serialize( $settings );
    arcade_db(
        'INSERT INTO ' . $table . ' (evo_field,evo_value) VALUES (' .
        arcade_quote( 'arcade_settings' ) . ',' . arcade_quote( $value ) .
        ') ON DUPLICATE KEY UPDATE evo_value=VALUES(evo_value)'
    );
    $evoconfig['arcade_settings'] = $value;
    if ( is_object( $cache ) ) {
        $cache->delete( 'evoconfig', 'config' );
    }
}

/**
 * Check a known Arcade table without treating SQL wildcard characters as patterns.
 *
 * @param string $name Whitelisted table suffix.
 * @return bool Whether the physical table exists.
 */
function arcade_table_exists( $name ) {
    global $db;

    $table = trim( arcade_table( $name ), '`' );
    $row = arcade_one(
        'SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema=DATABASE()' .
        ' AND table_name=' . arcade_quote( $table )
    );
    return (int) $row['total'] === 1;
}

/**
 * Check whether the four-table storage installation is ready.
 *
 * @return bool Whether all module tables and the storage version are present.
 */
function arcade_installed() {
    $settings = arcade_settings();
    if ( (int) ( $settings['storage_version'] ?? 0 ) !== 2 ) {
        return false;
    }
    foreach ( array( 'content', 'scores', 'favourites', 'runs' ) as $table ) {
        if ( ! arcade_table_exists( $table ) ) {
            return false;
        }
    }
    return true;
}

/**
 * Build a local URL for a validated game or picture filename.
 *
 * @param string $file    Filename without a directory path.
 * @param bool   $picture Whether the file is a game picture.
 * @return string File URL, or an empty string for an invalid filename.
 */
function arcade_file( $file, $picture = false ) {
    $pattern = $picture
        ? '/^[a-zA-Z0-9_-]+\.(?:gif|png|jpe?g|webp)$/iD'
        : '/^[a-zA-Z0-9_-]+\.(?:swf|html?)$/iD';

    if ( ! is_string( $file ) || preg_match( $pattern, $file ) !== 1 ) {
        return '';
    }

    $directory = 'modules/Arcade/games/';

    if ( $picture ) {
        $directory .= 'pics/';
    }

    return $directory . rawurlencode( $file );
}

/**
 * Retrieve an active game.
 *
 * @param int $id Game ID.
 * @return array Game data, or an empty array.
 */
function arcade_game( $id ) {
    $id = (int) $id;

    if ( $id <= 0 ) {
        return array();
    }

    $sql = 'SELECT *
            FROM ' . arcade_table( 'content' ) . "
            WHERE record_type='game' AND id = " . $id . '
                AND active = 1
            LIMIT 1';

    return arcade_one( $sql );
}

/**
 * Check whether the current visitor can play games in a category.
 *
 * Access level zero permits guests; level one requires authentication.
 * Arcade administrators can access any existing category.
 *
 * @param array|null $category Category data.
 * @return bool Whether access is permitted.
 */
function arcade_can_play( $category ) {
    if ( ! is_array( $category ) || empty( $category ) ) {
        return false;
    }

    if ( is_mod_admin( 'Arcade' ) ) {
        return true;
    }

    if ( ! isset( $category['access'] ) ) {
        return false;
    }

    $access = (int) $category['access'];

    return $access === 0 || ( $access === 1 && arcade_uid() > 0 );
}

/**
 * Retrieve an Arcade category.
 *
 * @param int $id Category ID.
 * @return array Category data, or an empty array.
 */
function arcade_category( $id ) {
    $id = (int) $id;

    if ( $id <= 0 ) {
        return array();
    }

    $sql = 'SELECT *
            FROM ' . arcade_table( 'content' ) . "
            WHERE record_type='category' AND id = " . $id . '
            LIMIT 1';

    return arcade_one( $sql );
}

/**
 * Retrieve the top 50 scores for a game, including player usernames.
 *
 * Scores are ranked highest first, with earlier achievements winning ties.
 *
 * @param int $id Game ID.
 * @return array Score rows with associated usernames.
 */
function arcade_scores( $id ) {
    global $prefix;

    $id = (int) $id;

    if ( $id <= 0 ) {
        return array();
    }

    $sql = 'SELECT s.*, u.username
            FROM ' . arcade_table( 'scores' ) . ' AS s
            LEFT JOIN `' . $prefix . '_users` AS u
                ON u.user_id = s.user_id
            WHERE s.game_id = ' . $id . '
            ORDER BY s.score DESC, s.achieved ASC
            LIMIT 50';

    $scores = arcade_rows( $sql );

    foreach ( $scores as &$score ) {
        $score['avatar_url'] = function_exists( 'get_user_avatar' )
            ? (string) get_user_avatar( (int) $score['user_id'] )
            : '';
    }
    unset( $score );

    return $scores;
}

/**
 * Display the leaderboard for a game.
 *
 * @param int $id Game ID.
 * @return void
 */
function arcade_score_list( $id ) {
    get_template_part( 'arcade-leaderboard', null, array( 'scores' => arcade_scores( $id ) ) );
}

/**
 * Display a labelled input field.
 *
 * @param string       $name  Input field name.
 * @param string       $label Display label.
 * @param string|int   $value Input field value.
 * @param string       $type  Input field type.
 * @return void
 */
function arcade_input( $name, $label, $value = '', $type = 'text' ) {
    echo '<label>';
    echo arcade_e( $label );
    echo '<input type="' . arcade_e( $type ) . '"';
    echo ' name="' . arcade_e( $name ) . '"';
    echo ' value="' . arcade_e( $value ) . '">';
    echo '</label>';
}

/**
 * Display a submit button with a translated label.
 *
 * @param string $label Translation key for the button label.
 * @return void
 */
function arcade_button( $label ) {
    echo '<button type="submit">' . arcade_e( arcade_t( $label ) ) . '</button>';
}

/**
 * Validate a non-negative whole-number or fractional game score.
 *
 * Accepts up to 12 whole-number digits and 8 fractional digits.
 *
 * @param mixed $score Score to validate.
 * @return bool Whether the score is a valid numeric string.
 */
function arcade_valid_score( $score ) {
    return is_string( $score )
        && preg_match( '/^[0-9]{1,12}(?:\.[0-9]{1,8})?$/D', $score ) === 1;
}

/**
 * Format a stored score with thousands separators and no trailing zeroes.
 *
 * Uses string formatting to preserve the precision of large scores.
 *
 * @param string|int|float $score Stored score.
 * @return string Formatted score.
 */
function arcade_format_score( $score ) {
    $parts    = explode( '.', (string) $score, 2 );
    $whole    = preg_replace( '/\B(?=(?:[0-9]{3})+(?![0-9]))/', ',', $parts[0] );
    $fraction = isset( $parts[1] ) ? rtrim( $parts[1], '0' ) : '';

    return $whole . ( $fraction !== '' ? '.' . $fraction : '' );
}
