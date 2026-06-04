<?php

require_once __DIR__ . '/../../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

admin_only();

$db = Database::connect();

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;

$form = [
    'name' => '',
    'slug' => '',
    'category_type' => 'frame',
    'parent_id' => '',
    'description' => '',
    'sort_order' => '0',
    'is_active' => '1',
];

if ($isEdit) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $category = $stmt->fetch();

    if (!$category) {
        add_flash('warning', 'Không tìm thấy danh mục cần chỉnh sửa.');
        redirect_to('/admin/categories/index.php');
    }

    $form = [
        'name' => (string) $category['name'],
        'slug' => (string) $category['slug'],
        'category_type' => (string) ($category['category_type'] ?: 'frame'),
        'parent_id' => $category['parent_id'] !== null ? (string) $category['parent_id'] : '',
        'description' => (string) ($category['description'] ?? ''),
        'sort_order' => (string) ((int) ($category['sort_order'] ?? 0)),
        'is_active' => (string) ((int) ($category['is_active'] ?? 1)),
    ];
}

$parentSql = "
    SELECT id, name, category_type
    FROM categories
    WHERE parent_id IS NULL
";

$parentParams = [];

if ($isEdit) {
    $parentSql .= " AND id <> :current_id";
    $parentParams[':current_id'] = $id;
}

$parentSql .= " ORDER BY sort_order ASC, id ASC";

$parentStmt = $db->prepare($parentSql);
$parentStmt->execute($parentParams);
$parentCategories = $parentStmt->fetchAll();

$pageTitle = $isEdit ? 'Admin - Sửa danh mục' : 'Admin - Thêm danh mục';
$pageDescription = 'Tạo và cập nhật danh mục sản phẩm.';
$adminActive = 'categories';

require_once BASE_PATH . '/app/views/partials/admin-head.php';
?>
<div class="admin-shell">
    <?php require_once BASE_PATH . '/app/views/partials/admin-sidebar.php'; ?>

    <main class="admin-main">
        <?php require_once BASE_PATH . '/app/views/partials/admin-topbar.php'; ?>

        <section class="admin-dashboard">
            <div class="admin-hero-card">
                <div>
                    <span class="eyebrow">DANH MỤC SẢN PHẨM</span>
                    <h1><?= $isEdit ? 'Chỉnh sửa danh mục' : 'Tạo danh mục mới' ?></h1>
                    <p>Quản lý phân cấp danh mục, loại catalog và trạng thái hiển thị trên storefront.</p>
                </div>
                <div class="admin-topbar-actions">
                    <a class="btn-outline" href="<?= e(APP_URL) ?>/admin/categories/index.php"><i class="fi fi-rr-angle-left icon icon-sm"></i>Quay lại danh sách</a>
                </div>
            </div>

            <?php if ($flash = get_flash('warning')): ?>
                <div class="alert warning"><?= e($flash) ?></div>
            <?php endif; ?>

            <section class="admin-card">
                <form method="post" action="<?= e(APP_URL) ?>/admin/categories/save.php" class="form-grid two-cols">
                    <input type="hidden" name="id" value="<?= $isEdit ? (int) $id : 0 ?>">

                    <div class="form-field">
                        <label for="name">Tên danh mục</label>
                        <input id="name" name="name" value="<?= e($form['name']) ?>" required>
                    </div>

                    <div class="form-field">
                        <label for="slug">Slug</label>
                        <input id="slug" name="slug" value="<?= e($form['slug']) ?>" placeholder="Tự tạo nếu để trống">
                    </div>

                    <div class="form-field">
                        <label for="category_type">Loại danh mục</label>
                        <select id="category_type" name="category_type">
                            <option value="frame" <?= $form['category_type'] === 'frame' ? 'selected' : '' ?>>Gọng kính</option>
                            <option value="sunglasses" <?= $form['category_type'] === 'sunglasses' ? 'selected' : '' ?>>Kính mát</option>
                            <option value="lens" <?= $form['category_type'] === 'lens' ? 'selected' : '' ?>>Tròng kính</option>
                            <option value="other" <?= $form['category_type'] === 'other' ? 'selected' : '' ?>>Khác</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="parent_id">Danh mục cha</label>
                        <select id="parent_id" name="parent_id">
                            <option value="">Không có</option>
                            <?php foreach ($parentCategories as $parent): ?>
                                <option value="<?= (int) $parent['id'] ?>" <?= $form['parent_id'] !== '' && (int) $form['parent_id'] === (int) $parent['id'] ? 'selected' : '' ?>><?= e($parent['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="sort_order">Thứ tự sắp xếp</label>
                        <input id="sort_order" name="sort_order" type="number" min="0" value="<?= e($form['sort_order']) ?>">
                    </div>

                    <div class="form-field">
                        <label for="is_active">Trạng thái</label>
                        <select id="is_active" name="is_active">
                            <option value="1" <?= $form['is_active'] === '1' ? 'selected' : '' ?>>Đang bật</option>
                            <option value="0" <?= $form['is_active'] === '0' ? 'selected' : '' ?>>Đang ẩn</option>
                        </select>
                    </div>

                    <div class="form-field full-width">
                        <label for="description">Mô tả ngắn</label>
                        <textarea id="description" name="description" rows="5"><?= e($form['description']) ?></textarea>
                    </div>

                    <div class="form-field full-width">
                        <div class="admin-detail-actions">
                            <button class="btn-primary" type="submit"><i class="fi fi-rr-disk icon icon-sm"></i><?= $isEdit ? 'Lưu thay đổi' : 'Tạo danh mục' ?></button>
                            <a class="btn-outline" href="<?= e(APP_URL) ?>/admin/categories/index.php">Hủy</a>
                        </div>
                    </div>
                </form>
            </section>
        </section>
    </main>
</div>
