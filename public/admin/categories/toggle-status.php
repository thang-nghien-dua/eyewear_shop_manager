<?php

require_once __DIR__ . '/../../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

admin_only();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/admin/categories/index.php');
}

$db = Database::connect();
$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    add_flash('warning', 'Danh mục không hợp lệ.');
    redirect_to('/admin/categories/index.php');
}

$stmt = $db->prepare("SELECT id, is_active FROM categories WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$category = $stmt->fetch();

if (!$category) {
    add_flash('warning', 'Không tìm thấy danh mục.');
    redirect_to('/admin/categories/index.php');
}

$newStatus = (int) $category['is_active'] === 1 ? 0 : 1;

$updateStmt = $db->prepare("UPDATE categories SET is_active = :is_active, updated_at = NOW() WHERE id = :id");
$updateStmt->execute([
    ':is_active' => $newStatus,
    ':id' => $id,
]);

add_flash('success', $newStatus === 1 ? 'Đã bật danh mục.' : 'Đã ẩn danh mục.');
redirect_to('/admin/categories/index.php');
