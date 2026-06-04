<?php

require_once __DIR__ . '/../../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

admin_only();

$db = Database::connect();

$keyword = trim((string) ($_GET['keyword'] ?? ''));
$type = trim((string) ($_GET['type'] ?? ''));
$active = trim((string) ($_GET['active'] ?? ''));

$where = [];
$params = [];

if ($keyword !== '') {
    $where[] = "(c.name LIKE :keyword OR c.slug LIKE :keyword OR COALESCE(c.description, '') LIKE :keyword)";
    $params[':keyword'] = '%' . $keyword . '%';
}

if ($type !== '') {
    $where[] = "c.category_type = :type";
    $params[':type'] = $type;
}

if ($active === '1' || $active === '0') {
    $where[] = "c.is_active = :active";
    $params[':active'] = (int) $active;
}

$sql = "
    SELECT c.*,
           p.name AS parent_name,
           (
             SELECT COUNT(*)
             FROM products pr
             WHERE pr.category_id = c.id
           ) AS products_count
    FROM categories c
    LEFT JOIN categories p ON p.id = c.parent_id
";

if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= " ORDER BY COALESCE(c.parent_id, c.id), c.parent_id IS NOT NULL, c.sort_order ASC, c.id ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll();

$summary = $db->query("
    SELECT
        COUNT(*) AS total_categories,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_categories,
        SUM(CASE WHEN parent_id IS NULL THEN 1 ELSE 0 END) AS root_categories,
        SUM(CASE WHEN parent_id IS NOT NULL THEN 1 ELSE 0 END) AS child_categories
    FROM categories
")->fetch();

$pageTitle = 'Admin - Quản lý danh mục';
$pageDescription = 'Quản lý danh mục sản phẩm trong hệ thống LUMINA.';
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
                    <span class="eyebrow">QUẢN LÝ CATALOG</span>
                    <h1>Danh mục sản phẩm</h1>
                    <p>Tạo, chỉnh sửa và bật/tắt các danh mục đang dùng cho storefront và trang quản trị.</p>
                </div>
                <div class="admin-topbar-actions">
                    <a class="btn-outline" href="<?= e(APP_URL) ?>/products.php">
                        <i class="fi fi-rr-shop icon icon-sm"></i>
                        Xem storefront
                    </a>
                    <a class="btn-primary" href="<?= e(APP_URL) ?>/admin/categories/edit.php">
                        <i class="fi fi-rr-plus icon icon-sm"></i>
                        Thêm danh mục
                    </a>
                </div>
            </div>

            <?php if ($flash = get_flash('success')): ?>
                <div class="alert success"><?= e($flash) ?></div>
            <?php endif; ?>

            <?php if ($flash = get_flash('warning')): ?>
                <div class="alert warning"><?= e($flash) ?></div>
            <?php endif; ?>

            <div class="admin-summary-grid">
                <article class="admin-summary-card"><span>Tổng danh mục</span><strong><?= (int) ($summary['total_categories'] ?? 0) ?></strong></article>
                <article class="admin-summary-card"><span>Đang hoạt động</span><strong><?= (int) ($summary['active_categories'] ?? 0) ?></strong></article>
                <article class="admin-summary-card"><span>Danh mục cha</span><strong><?= (int) ($summary['root_categories'] ?? 0) ?></strong></article>
                <article class="admin-summary-card"><span>Danh mục con</span><strong><?= (int) ($summary['child_categories'] ?? 0) ?></strong></article>
            </div>

            <section class="admin-filter-card">
                <form method="get" class="form-grid two-cols admin-filter-grid">
                    <div class="form-field">
                        <label for="keyword">Từ khóa</label>
                        <input id="keyword" name="keyword" value="<?= e($keyword) ?>" placeholder="Tên, slug hoặc mô tả">
                    </div>

                    <div class="form-field">
                        <label for="type">Loại danh mục</label>
                        <select id="type" name="type">
                            <option value="">Tất cả</option>
                            <?php foreach (['frame' => 'Gọng kính', 'sunglasses' => 'Kính mát', 'lens' => 'Tròng kính', 'other' => 'Khác'] as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $type === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="active">Trạng thái</label>
                        <select id="active" name="active">
                            <option value="">Tất cả</option>
                            <option value="1" <?= $active === '1' ? 'selected' : '' ?>>Đang bật</option>
                            <option value="0" <?= $active === '0' ? 'selected' : '' ?>>Đang ẩn</option>
                        </select>
                    </div>

                    <div class="form-field form-field-actions">
                        <button class="btn-primary" type="submit"><i class="fi fi-rr-search icon icon-sm"></i>Lọc danh mục</button>
                        <a class="btn-outline" href="<?= e(APP_URL) ?>/admin/categories/index.php">Đặt lại</a>
                    </div>
                </form>
            </section>

            <section class="admin-table-card">
                <div class="admin-card-head">
                    <div>
                        <h2>Danh sách danh mục</h2>
                        <p class="summary-note">Hiển thị đầy đủ danh mục cha/con cùng số sản phẩm đang gắn.</p>
                    </div>
                </div>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên danh mục</th>
                                <th>Slug</th>
                                <th>Loại</th>
                                <th>Danh mục cha</th>
                                <th>Sắp xếp</th>
                                <th>Sản phẩm</th>
                                <th>Trạng thái</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($categories === []): ?>
                                <tr><td colspan="9"><div class="empty-mini-card">Chưa có danh mục phù hợp với bộ lọc hiện tại.</div></td></tr>
                            <?php else: ?>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td>#<?= (int) $category['id'] ?></td>
                                        <td>
                                            <strong><?= e($category['name']) ?></strong>
                                            <?php if (!empty($category['description'])): ?>
                                                <span class="muted-small"><?= e($category['description']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?= e($category['slug']) ?></code></td>
                                        <td><?= e(match ($category['category_type']) {
                                            'frame' => 'Gọng kính',
                                            'sunglasses' => 'Kính mát',
                                            'lens' => 'Tròng kính',
                                            default => 'Khác',
                                        }) ?></td>
                                        <td><?= e($category['parent_name'] ?: '—') ?></td>
                                        <td><?= (int) $category['sort_order'] ?></td>
                                        <td><?= (int) $category['products_count'] ?></td>
                                        <td><span class="status-pill <?= (int) $category['is_active'] === 1 ? 'status-completed' : 'status-default' ?>"><?= (int) $category['is_active'] === 1 ? 'Đang bật' : 'Đang ẩn' ?></span></td>
                                        <td>
                                            <div class="admin-detail-actions">
                                                <a class="btn-outline btn-sm" href="<?= e(APP_URL) ?>/admin/categories/edit.php?id=<?= (int) $category['id'] ?>">Sửa</a>
                                                <form method="post" action="<?= e(APP_URL) ?>/admin/categories/toggle-status.php" style="display:inline;">
                                                    <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                                    <button class="btn-outline btn-sm" type="submit"><?= (int) $category['is_active'] === 1 ? 'Ẩn' : 'Bật' ?></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>
</div>
