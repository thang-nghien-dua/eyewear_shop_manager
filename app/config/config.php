<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

define('APP_NAME', 'LUMINA');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost:8080');
define('BASE_PATH', dirname(__DIR__, 2));
define('PUBLIC_PATH', BASE_PATH . '/public');

require_once BASE_PATH . '/app/config/database.php';
