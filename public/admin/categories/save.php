<?php

require_once __DIR__ . '/../../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

admin_only();

$db = Database::connect();

function category_slugify(string $value): string
{
    $value = trim(mb_strtolower($value));
    $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'danh-muc';
}

$id = (int) ($_POST['id'] ?? 0);
$isEdit = $id > 0;

$name = trim((string) ($_POST['name'] ?? ''));
$slug = trim((string) ($_POST['slug'] ?? ''));
$categoryType = trim((string) ($_POST['category_type'] ?? 'frame'));
$parentId = trim((string) ($_POST['parent_id'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$sortOrder = (int) ($_POST['sort_order'] ?? 0);
$isActive = (int) ($_POST['is_active'] ?? 1);

$errors = [];

if ($name === '') {
    $errors[] = 'Tên danh mục không được để trống.';
}

$allowedTypes = ['frame', 'sunglasses', 'lens', 'other'];
if (!in_array($categoryType, $allowedTypes, true)) {
    $errors[] = 'Loại danh mục không hợp lệ.';
}

$slug = category_slugify($slug !== '' ? $slug : $name);

$parentCategoryId = null;
if ($parentId !== '') {
    $parentCategoryId = (int) $parentId;
    if ($isEdit && $parentCategoryId === $id) {
        $errors[] = 'Danh mục cha không được trùng với chính danh mục hiện tại.';
    }
}

$existsSql = "SELECT id FROM categories WHERE slug = :slug";
$params = [':slug' => $slug];

if ($isEdit) {
    $existsSql .= " AND id <> :id";
    $params[':id'] = $id;
}

$existsStmt = $db->prepare($existsSql);
$existsStmt->execute($params);

if ($existsStmt->fetch()) {
    $errors[] = 'Slug đã tồn tại, vui lòng chọn slug khác.';
}

if ($errors !== []) {
    add_flash('warning', implode(' ', $errors));
    $redirect = '/admin/categories/edit.php';
    if ($isEdit) {
        $redirect .= '?id=' . $id;
    }
    redirect_to($redirect);
}

if ($isEdit) {
    $stmt = $db->prepare("
        UPDATE categories
        SET name = :name,
            slug = :slug,
            category_type = :category_type,
            parent_id = :parent_id,
            description = :description,
            sort_order = :sort_order,
            is_active = :is_active,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':name' => $name,
        ':slug' => $slug,
        ':category_type' => $categoryType,
        ':parent_id' => $parentCategoryId,
        ':description' => $description !== '' ? $description : null,
        ':sort_order' => $sortOrder,
        ':is_active' => $isActive,
        ':id' => $id,
    ]);

    add_flash('success', 'Đã cập nhật danh mục thành công.');
} else {
    $stmt = $db->prepare("
        INSERT INTO categories (
            name, slug, category_type, parent_id, description, sort_order, is_active, created_at, updated_at
        ) VALUES (
            :name, :slug, :category_type, :parent_id, :description, :sort_order, :is_active, NOW(), NOW()
        )
    ");

    $stmt->execute([
        ':name' => $name,
        ':slug' => $slug,
        ':category_type' => $categoryType,
        ':parent_id' => $parentCategoryId,
        ':description' => $description !== '' ? $description : null,
        ':sort_order' => $sortOrder,
        ':is_active' => $isActive,
    ]);

    add_flash('success', 'Đã tạo danh mục mới thành công.');
}

redirect_to('/admin/categories/index.php');
