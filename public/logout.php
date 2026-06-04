<?php

require_once __DIR__ . '/../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';

logout_user();
add_flash('success', 'Bạn đã đăng xuất.');
redirect_to('/login.php');