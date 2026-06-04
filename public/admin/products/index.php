<?php

require_once __DIR__ . '/../../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

admin_only();

$db = Database::connect();

$keyword = trim((string)($_GET['keyword'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$categoryId = (int)($_GET['category_id'] ?? 0);

$where = [];
$params = [];

if ($keyword !== '') {
    $where[] = '(p.name LIKE :keyword OR p.slug LIKE :keyword OR COALESCE(p.brand, "") LIKE :keyword)';
    $params['keyword'] = '%' . $keyword . '%';
}

if ($status !== '') {
    $where[] = 'p.status = :status';
    $params['status'] = $status;
}

if ($categoryId > 0) {
    $where[] = 'p.category_id = :category_id';
    $params['category_id'] = $categoryId;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$categoriesStmt = $db->query('SELECT id, name FROM categories ORDER BY name ASC');
$categories = $categoriesStmt->fetchAll();

$sql = "
    SELECT p.id, p.name, p.slug, p.brand, p.default_price, p.compare_at_price, p.status,
           p.thumbnail, p.material, p.shape, p.frame_type, p.target_gender,
           c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    {$whereSql}
    ORDER BY p.id DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$totalStmt = $db->query("SELECT COUNT(*) FROM products");
$totalProducts = (int)$totalStmt->fetchColumn();
$activeStmt = $db->query("SELECT COUNT(*) FROM products WHERE status = 'active'");
$activeProducts = (int)$activeStmt->fetchColumn();
$inactiveStmt = $db->query("SELECT COUNT(*) FROM products WHERE status <> 'active' OR status IS NULL");
$inactiveProducts = (int)$inactiveStmt->fetchColumn();

$pageTitle = 'Admin - Quản lý sản phẩm';
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
                    <span class="admin-topbar-kicker">PRODUCT MANAGEMENT</span>
                    <h1>Quản lý sản phẩm</h1>
                    <p>Theo dõi catalog, lọc nhanh và chỉnh sửa thông tin sản phẩm.</p>
                </div>
                <div class="admin-topbar-actions">
                    <a class="btn-outline" href="<?= e(APP_URL) ?>/admin/products/edit.php">
                        <i class="fi fi-rr-plus-small icon"></i> Thêm sản phẩm
                    </a>
                </div>
            </div>

            <div class="admin-kpi-grid compact-grid">
                <div class="admin-kpi-card compact accent-purple">
                    <div class="admin-kpi-icon"><i class="fi fi-rr-box-open"></i></div>
                    <div><span>Tổng sản phẩm</span><strong><?= number_format($totalProducts) ?></strong></div>
                </div>
                <div class="admin-kpi-card compact accent-green">
                    <div class="admin-kpi-icon"><i class="fi fi-rr-badge-check"></i></div>
                    <div><span>Đang hoạt động</span><strong><?= number_format($activeProducts) ?></strong></div>
                </div>
                <div class="admin-kpi-card compact accent-amber">
                    <div class="admin-kpi-icon"><i class="fi fi-rr-eye-crossed"></i></div>
                    <div><span>Đang ẩn</span><strong><?= number_format($inactiveProducts) ?></strong></div>
                </div>
                <div class="admin-kpi-card compact accent-blue">
                    <div class="admin-kpi-icon"><i class="fi fi-rr-layers"></i></div>
                    <div><span>Danh mục</span><strong><?= number_format(count($categories)) ?></strong></div>
                </div>
            </div>

            <div class="admin-panel">
                <div class="admin-panel-head compact">
                    <div>
                        <span class="admin-panel-kicker">BỘ LỌC</span>
                        <h2>Lọc và tìm kiếm</h2>
                    </div>
                </div>

                <form method="get" class="form-grid two-cols admin-filter-grid">
                    <div class="form-field">
                        <label for="keyword">Từ khóa</label>
                        <input id="keyword" type="text" name="keyword" value="<?= e($keyword) ?>" placeholder="Tên, slug, brand...">
                    </div>
                    <div class="form-field">
                        <label for="category_id">Danh mục</label>
                        <select id="category_id" name="category_id">
                            <option value="0">Tất cả danh mục</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>" <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>>
                                    <?= e($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="status">Trạng thái</label>
                        <select id="status" name="status">
                            <option value="">Tất cả</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                        </select>
                    </div>
                    <div class="form-field form-field-actions full-width">
                        <button type="submit" class="btn-primary">Tìm kiếm</button>
                        <a class="btn-outline" href="<?= e(APP_URL) ?>/admin/products/index.php">Xóa bộ lọc</a>
                    </div>
                </form>
            </div>

            <div class="admin-panel">
                <div class="admin-panel-head compact">
                    <div>
                        <span class="admin-panel-kicker">CATALOG</span>
                        <h2>Danh sách sản phẩm</h2>
                        <p><?= number_format(count($products)) ?> sản phẩm phù hợp bộ lọc hiện tại.</p>
                    </div>
                </div>

                <div class="admin-table-wrap">
                    <table class="admin-table admin-table-dashboard">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Sản phẩm</th>
                            <th>Danh mục</th>
                            <th>Giá</th>
                            <th>Thuộc tính</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$products): ?>
                            <tr>
                                <td colspan="7" class="muted-small">Không có sản phẩm nào.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>#<?= (int)$product['id'] ?></td>
                                    <td>
                                        <strong><?= e($product['name']) ?></strong>
                                        <span class="muted-small">Slug: <?= e($product['slug']) ?></span>
                                        <span class="muted-small">Brand: <?= e((string)$product['brand']) ?></span>
                                    </td>
                                    <td><?= e((string)$product['category_name']) ?></td>
                                    <td>
                                        <strong><?= function_exists('format_price') ? format_price((float)$product['default_price']) : number_format((float)$product['default_price'], 0, ',', '.') . 'đ' ?></strong>
                                        <?php if (!empty($product['compare_at_price'])): ?>
                                            <span class="muted-small"><s><?= function_exists('format_price') ? format_price((float)$product['compare_at_price']) : number_format((float)$product['compare_at_price'], 0, ',', '.') . 'đ' ?></s></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="muted-small">Dáng: <?= e((string)$product['shape']) ?></span>
                                        <span class="muted-small">Chất liệu: <?= e((string)$product['material']) ?></span>
                                        <span class="muted-small">Giới tính: <?= e((string)$product['target_gender']) ?></span>
                                    </td>
                                    <td>
                                        <?php $statusClass = 'status-default';
                                        if ($product['status'] === 'active') $statusClass = 'status-completed';
                                        elseif ($product['status'] === 'inactive') $statusClass = 'status-cancelled';
                                        elseif ($product['status'] === 'draft') $statusClass = 'status-pending'; ?>
                                        <span class="status-pill <?= $statusClass ?>"><?= e((string)$product['status']) ?></span>
                                    </td>
                                    <td>
                                        <div class="card-actions" style="min-width: 210px;">
                                            <a class="btn-outline btn-sm" href="<?= e(APP_URL) ?>/admin/products/edit.php?id=<?= (int)$product['id'] ?>">Sửa</a>
                                            <form action="<?= e(APP_URL) ?>/admin/products/toggle-status.php" method="post">
                                                <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                                                <button class="btn-primary btn-sm" type="submit">
                                                    <?= $product['status'] === 'active' ? 'Ẩn' : 'Bật' ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>