<?php
/**
 * Fresh installation of the four-table Arcade release schema.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

/**
 * Create the four physical tables without changing existing records.
 *
 * @return void
 * @throws RuntimeException On SQL failure.
 */
function arcade_create_tables() {
    $tables = array(
        'content' => "
            record_type ENUM('game','category','comment') NOT NULL,
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            category_id INT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(100) NOT NULL DEFAULT '',
            description TEXT NULL,
            swf VARCHAR(150) NOT NULL DEFAULT '',
            picture VARCHAR(150) NOT NULL DEFAULT '',
            width INT UNSIGNED NOT NULL DEFAULT 550,
            height INT UNSIGNED NOT NULL DEFAULT 380,
            scorevar VARCHAR(50) NOT NULL DEFAULT 'vscore',
            type TINYINT UNSIGNED NOT NULL DEFAULT 3,
            position INT NOT NULL DEFAULT 0,
            access TINYINT UNSIGNED NOT NULL DEFAULT 0,
            active TINYINT UNSIGNED NOT NULL DEFAULT 1,
            plays INT UNSIGNED NOT NULL DEFAULT 0,
            game_id INT UNSIGNED NOT NULL DEFAULT 0,
            user_id INT UNSIGNED NOT NULL DEFAULT 0,
            message TEXT NULL,
            created INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (record_type,id),
            KEY allocate_id (id),
            KEY browse (record_type,active,category_id,position),
            KEY comments (record_type,game_id,created)
        ",
        'scores' => "
            game_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            score DECIMAL(30,8) UNSIGNED NOT NULL,
            achieved INT UNSIGNED NOT NULL,
            plays INT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (game_id, user_id),
            KEY ranking (game_id, score, achieved)
        ",
        'favourites' => "
            user_id INT UNSIGNED NOT NULL,
            game_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (user_id, game_id)
        ",
        'runs' => "
            token CHAR(64) NOT NULL PRIMARY KEY,
            legacy_hash CHAR(32) NOT NULL,
            game_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            started INT UNSIGNED NOT NULL,
            KEY expiry (started),
            KEY lookup_run (user_id, game_id, legacy_hash)
        ",

    );
    foreach ( $tables as $name => $definition ) {
        arcade_db( 'CREATE TABLE IF NOT EXISTS ' . arcade_table( $name ) . ' (' . $definition .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' );
    }
}

/** Install the release schema and default catalogue on a fresh site. */
function arcade_install() {
    if ( arcade_installed() ) {
        return;
    }
    arcade_create_tables();
    $existing = arcade_one( 'SELECT COUNT(*) AS total FROM ' . arcade_table( 'content' ) );
    if ( (int) $existing['total'] === 0 ) {
        require __DIR__ . '/default-games.php';
    }
    $settings = arcade_settings();
    $settings['storage_version'] = 2;
    arcade_save_settings( $settings );
}
