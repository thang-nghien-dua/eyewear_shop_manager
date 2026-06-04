<?php

require_once BASE_PATH . '/app/helpers/functions.php';

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return !empty($_SESSION['user']);
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        $user = current_user();
        return $user && (($user['role_name'] ?? '') === 'admin');
    }
}

if (!function_exists('auth_only')) {
    function auth_only(): void
    {
        require_login();
    }
}

if (!function_exists('admin_only')) {
    function admin_only(): void
    {
        require_admin();
    }
}