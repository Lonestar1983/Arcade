<?php

defined('ADMIN_FILE') || exit;

global $admin_file;

require_once NUKE_MODULES_DIR . 'Arcade/includes/language-loader.php';
require_once NUKE_MODULES_DIR . 'Arcade/includes/functions.php';

if ( is_mod_admin('Arcade') ) {
	adminmenu(
		$admin_file . '.php?op=arcade',
		arcade_t('title'),
		'arcade.webp'
	);
}
