<?php

require_once __DIR__ . '/../../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

admin_only();

$db = Database::connect();
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . APP_URL . '/admin/products/index.php');
    exit;
}

$stmt = $db->prepare('SELECT status FROM products WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$current = $stmt->fetchColumn();
if ($current === false) {
    header('Location: ' . APP_URL . '/admin/products/index.php');
    exit;
}

$newStatus = $current === 'active' ? 'inactive' : 'active';
$update = $db->prepare('UPDATE products SET status = :status, updated_at = NOW() WHERE id = :id');
$update->execute(['status' => $newStatus, 'id' => $id]);

header('Location: ' . APP_URL . '/admin/products/index.php');
exit;