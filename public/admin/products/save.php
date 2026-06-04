<?php

require_once __DIR__ . '/../../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

admin_only();

$db = Database::connect();

function slugify_admin_product(string $text): string {
    $text = trim(mb_strtolower($text));
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text) ?? $text;
    $text = trim($text, '-');
    return $text !== '' ? $text : 'san-pham';
}

$id = (int)($_POST['id'] ?? 0);
$categoryId = (int)($_POST['category_id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$slug = trim((string)($_POST['slug'] ?? ''));
$brand = trim((string)($_POST['brand'] ?? ''));
$shortDescription = trim((string)($_POST['short_description'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$frameType = trim((string)($_POST['frame_type'] ?? ''));
$targetGender = trim((string)($_POST['target_gender'] ?? ''));
$material = trim((string)($_POST['material'] ?? ''));
$shape = trim((string)($_POST['shape'] ?? ''));
$defaultPrice = (float)($_POST['default_price'] ?? 0);
$compareAtPrice = $_POST['compare_at_price'] !== '' ? (float)$_POST['compare_at_price'] : null;
$thumbnail = trim((string)($_POST['thumbnail'] ?? ''));
$isPrescriptionSupported = (int)($_POST['is_prescription_supported'] ?? 0);
$has3dModel = (int)($_POST['has_3d_model'] ?? 0);
$status = trim((string)($_POST['status'] ?? 'active'));

if ($name === '' || $categoryId <= 0 || $defaultPrice < 0) {
    exit('Dữ liệu không hợp lệ.');
}

if ($slug === '') {
    $slug = slugify_admin_product($name);
}

$slugCheck = $db->prepare('SELECT id FROM products WHERE slug = :slug AND id <> :id LIMIT 1');
$slugCheck->execute(['slug' => $slug, 'id' => $id]);
if ($slugCheck->fetch()) {
    $slug .= '-' . time();
}

$params = [
    'category_id' => $categoryId,
    'name' => $name,
    'slug' => $slug,
    'brand' => $brand,
    'short_description' => $shortDescription,
    'description' => $description,
    'frame_type' => $frameType,
    'target_gender' => $targetGender,
    'material' => $material,
    'shape' => $shape,
    'default_price' => $defaultPrice,
    'compare_at_price' => $compareAtPrice,
    'thumbnail' => $thumbnail,
    'is_prescription_supported' => $isPrescriptionSupported,
    'has_3d_model' => $has3dModel,
    'status' => $status,
];

if ($id > 0) {
    $params['id'] = $id;
    $sql = 'UPDATE products SET
        category_id = :category_id,
        name = :name,
        slug = :slug,
        brand = :brand,
        short_description = :short_description,
        description = :description,
        frame_type = :frame_type,
        target_gender = :target_gender,
        material = :material,
        shape = :shape,
        default_price = :default_price,
        compare_at_price = :compare_at_price,
        thumbnail = :thumbnail,
        is_prescription_supported = :is_prescription_supported,
        has_3d_model = :has_3d_model,
        status = :status,
        updated_at = NOW()
        WHERE id = :id';
} else {
    $sql = 'INSERT INTO products (
        category_id, name, slug, brand, short_description, description,
        frame_type, target_gender, material, shape, default_price,
        compare_at_price, thumbnail, is_prescription_supported, has_3d_model,
        status, created_at, updated_at
    ) VALUES (
        :category_id, :name, :slug, :brand, :short_description, :description,
        :frame_type, :target_gender, :material, :shape, :default_price,
        :compare_at_price, :thumbnail, :is_prescription_supported, :has_3d_model,
        :status, NOW(), NOW()
    )';
}

$stmt = $db->prepare($sql);
$stmt->execute($params);

header('Location: ' . APP_URL . '/admin/products/index.php');
exit;