<?php

require_once __DIR__ . '/../../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

admin_only();

$db = Database::connect();
$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;

$product = [
    'id' => 0,
    'category_id' => '',
    'name' => '',
    'slug' => '',
    'brand' => 'LUMINA',
    'short_description' => '',
    'description' => '',
    'frame_type' => '',
    'target_gender' => '',
    'material' => '',
    'shape' => '',
    'default_price' => '',
    'compare_at_price' => '',
    'thumbnail' => '',
    'is_prescription_supported' => 0,
    'has_3d_model' => 0,
    'status' => 'active',
];

if ($isEdit) {
    $stmt = $db->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $found = $stmt->fetch();
    if (!$found) {
        http_response_code(404);
        exit('Không tìm thấy sản phẩm.');
    }
    $product = array_merge($product, $found);
}

$categoriesStmt = $db->query('SELECT id, name FROM categories ORDER BY name ASC');
$categories = $categoriesStmt->fetchAll();

$pageTitle = $isEdit ? 'Admin - Sửa sản phẩm' : 'Admin - Thêm sản phẩm';
$currentAdminPage = 'products';

require BASE_PATH . '/app/views/partials/admin-head.php';
?>
<div class="admin-shell admin-body">
    <?php require BASE_PATH . '/app/views/partials/admin-sidebar.php'; ?>
    <main class="admin-main">
        <?php require BASE_PATH . '/app/views/partials/admin-topbar.php'; ?>

        <section class="admin-dashboard">
            <div class="admin-topbar">
                <div>
                    <span class="admin-topbar-kicker">PRODUCT FORM</span>
                    <h1><?= $isEdit ? 'Chỉnh sửa sản phẩm' : 'Thêm sản phẩm mới' ?></h1>
                    <p>Quản trị thông tin catalog để storefront hiển thị đúng và đồng bộ.</p>
                </div>
                <div class="admin-topbar-actions">
                    <a class="btn-outline" href="<?= e(APP_URL) ?>/admin/products/index.php">Quay lại danh sách</a>
                </div>
            </div>

            <div class="admin-panel">
                <form action="<?= e(APP_URL) ?>/admin/products/save.php" method="post" class="form-grid two-cols">
                    <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">

                    <div class="form-field">
                        <label>Danh mục</label>
                        <select name="category_id" required>
                            <option value="">Chọn danh mục</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>" <?= (int)$product['category_id'] === (int)$category['id'] ? 'selected' : '' ?>>
                                    <?= e($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label>Tên sản phẩm</label>
                        <input type="text" name="name" required value="<?= e((string)$product['name']) ?>">
                    </div>

                    <div class="form-field">
                        <label>Slug</label>
                        <input type="text" name="slug" value="<?= e((string)$product['slug']) ?>">
                    </div>

                    <div class="form-field">
                        <label>Brand</label>
                        <input type="text" name="brand" value="<?= e((string)$product['brand']) ?>">
                    </div>

                    <div class="form-field full-width">
                        <label>Mô tả ngắn</label>
                        <input type="text" name="short_description" value="<?= e((string)$product['short_description']) ?>">
                    </div>

                    <div class="form-field full-width">
                        <label>Mô tả chi tiết</label>
                        <textarea name="description"><?= e((string)$product['description']) ?></textarea>
                    </div>

                    <div class="form-field">
                        <label>Frame type</label>
                        <input type="text" name="frame_type" value="<?= e((string)$product['frame_type']) ?>">
                    </div>
                    <div class="form-field">
                        <label>Target gender</label>
                        <input type="text" name="target_gender" value="<?= e((string)$product['target_gender']) ?>">
                    </div>
                    <div class="form-field">
                        <label>Material</label>
                        <input type="text" name="material" value="<?= e((string)$product['material']) ?>">
                    </div>
                    <div class="form-field">
                        <label>Shape</label>
                        <input type="text" name="shape" value="<?= e((string)$product['shape']) ?>">
                    </div>
                    <div class="form-field">
                        <label>Giá bán</label>
                        <input type="number" step="0.01" name="default_price" required value="<?= e((string)$product['default_price']) ?>">
                    </div>
                    <div class="form-field">
                        <label>Giá gạch</label>
                        <input type="number" step="0.01" name="compare_at_price" value="<?= e((string)$product['compare_at_price']) ?>">
                    </div>
                    <div class="form-field full-width">
                        <label>Thumbnail URL</label>
                        <input type="text" name="thumbnail" value="<?= e((string)$product['thumbnail']) ?>">
                    </div>
                    <div class="form-field">
                        <label>Prescription supported</label>
                        <select name="is_prescription_supported">
                            <option value="0" <?= (int)$product['is_prescription_supported'] === 0 ? 'selected' : '' ?>>Không</option>
                            <option value="1" <?= (int)$product['is_prescription_supported'] === 1 ? 'selected' : '' ?>>Có</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>3D model</label>
                        <select name="has_3d_model">
                            <option value="0" <?= (int)$product['has_3d_model'] === 0 ? 'selected' : '' ?>>Không</option>
                            <option value="1" <?= (int)$product['has_3d_model'] === 1 ? 'selected' : '' ?>>Có</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Trạng thái</label>
                        <select name="status">
                            <option value="active" <?= (string)$product['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= (string)$product['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="draft" <?= (string)$product['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                        </select>
                    </div>

                    <div class="form-field full-width form-field-actions">
                        <button class="btn-primary" type="submit">Lưu sản phẩm</button>
                        <a class="btn-outline" href="<?= e(APP_URL) ?>/admin/products/index.php">Hủy</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</div>
</body>
</html>