<?php

defined('ADMIN_FILE') || exit;

global $op;

if ( 'arcade' === $op ) {
	require_once NUKE_MODULES_DIR . 'Arcade/admin/index.php';
}
