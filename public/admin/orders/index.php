<?php

require_once __DIR__ . '/../../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

admin_only();

$db = Database::connect();

$status = trim((string) ($_GET['status'] ?? ''));
$orderType = trim((string) ($_GET['order_type'] ?? ''));
$keyword = trim((string) ($_GET['keyword'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$allowedStatuses = [
    'pending', 'awaiting_stock', 'checking_prescription', 'confirmed', 'processing',
    'lens_processing', 'shipping', 'completed', 'cancelled', 'refunded',
];
$allowedOrderTypes = ['available', 'preorder', 'prescription'];

$where = [];
$params = [];

if ($status !== '' && in_array($status, $allowedStatuses, true)) {
    $where[] = 'o.status = :status';
    $params['status'] = $status;
}

if ($orderType !== '' && in_array($orderType, $allowedOrderTypes, true)) {
    $where[] = 'o.order_type = :order_type';
    $params['order_type'] = $orderType;
}

if ($keyword !== '') {
    $where[] = '(o.order_code LIKE :keyword OR o.customer_name LIKE :keyword OR o.customer_phone LIKE :keyword OR o.customer_email LIKE :keyword)';
    $params['keyword'] = '%' . $keyword . '%';
}

$whereSql = $where !== [] ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM orders o $whereSql");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$sql = "SELECT
            o.id,
            o.order_code,
            o.order_type,
            o.status,
            o.customer_name,
            o.customer_phone,
            o.customer_email,
            o.total_amount,
            o.payment_status,
            o.created_at,
            COUNT(oi.id) AS item_count
        FROM orders o
        LEFT JOIN order_items oi ON oi.order_id = o.id
        $whereSql
        GROUP BY o.id
        ORDER BY o.created_at DESC, o.id DESC
        LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();

$summaryStmt = $db->query(
    "SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN status IN ('pending', 'awaiting_stock', 'checking_prescription') THEN 1 ELSE 0 END) AS pending_orders,
        SUM(CASE WHEN status = 'shipping' THEN 1 ELSE 0 END) AS shipping_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_orders
     FROM orders"
);
$summary = $summaryStmt->fetch() ?: [
    'total_orders' => 0,
    'pending_orders' => 0,
    'shipping_orders' => 0,
    'completed_orders' => 0,
];

$pageTitle = 'Admin - Đơn hàng - ' . APP_NAME;
$pageDescription = 'Quản lý đơn hàng ' . APP_NAME;
$adminPageTitle = 'Quản lý đơn hàng';
$adminPageSubtitle = 'Lọc, kiểm tra và cập nhật trạng thái đơn hàng theo luồng vận hành.';

function build_admin_query(array $overrides = []): string
{
    $current = $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($current[$key]);
        } else {
            $current[$key] = $value;
        }
    }

    return http_build_query($current);
}

include BASE_PATH . '/app/views/partials/admin-head.php';
?>
<div class="admin-shell">
    <?php include BASE_PATH . '/app/views/partials/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php include BASE_PATH . '/app/views/partials/admin-topbar.php'; ?>

        <main class="admin-dashboard">
            <section class="admin-kpi-grid compact-grid">
                <article class="admin-kpi-card accent-purple compact">
                    <div>
                        <span>Tổng đơn</span>
                        <strong><?= (int) $summary['total_orders'] ?></strong>
                    </div>
                </article>
                <article class="admin-kpi-card accent-amber compact">
                    <div>
                        <span>Chờ xử lý</span>
                        <strong><?= (int) $summary['pending_orders'] ?></strong>
                    </div>
                </article>
                <article class="admin-kpi-card accent-blue compact">
                    <div>
                        <span>Đang giao</span>
                        <strong><?= (int) $summary['shipping_orders'] ?></strong>
                    </div>
                </article>
                <article class="admin-kpi-card accent-green compact">
                    <div>
                        <span>Hoàn tất</span>
                        <strong><?= (int) $summary['completed_orders'] ?></strong>
                    </div>
                </article>
            </section>

            <?php if (isset($_GET['updated'])): ?>
                <div class="alert success">Đã cập nhật trạng thái đơn hàng.</div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert warning"><?= e((string) $_GET['error']) ?></div>
            <?php endif; ?>

            <section class="admin-panel">
                <div class="admin-panel-head">
                    <div>
                        <span class="admin-panel-kicker">Filter workspace</span>
                        <h2>Bộ lọc đơn hàng</h2>
                        <p>Tìm kiếm theo mã đơn, khách hàng, email, số điện thoại hoặc phân loại theo trạng thái.</p>
                    </div>
                </div>

                <form class="admin-filter-card no-margin" method="get" action="<?= e(APP_URL) ?>/admin/orders/index.php">
                    <div class="form-grid admin-filter-grid">
                        <div class="form-field">
                            <label for="keyword">Từ khóa</label>
                            <input id="keyword" type="text" name="keyword" value="<?= e($keyword) ?>" placeholder="Mã đơn, tên, email, số điện thoại">
                        </div>
                        <div class="form-field">
                            <label for="status">Trạng thái</label>
                            <select id="status" name="status">
                                <option value="">Tất cả trạng thái</option>
                                <?php foreach ($allowedStatuses as $statusOption): ?>
                                    <option value="<?= e($statusOption) ?>" <?= $statusOption === $status ? 'selected' : '' ?>>
                                        <?= e(order_status_label($statusOption)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="order_type">Loại đơn</label>
                            <select id="order_type" name="order_type">
                                <option value="">Tất cả loại đơn</option>
                                <?php foreach ($allowedOrderTypes as $typeOption): ?>
                                    <option value="<?= e($typeOption) ?>" <?= $typeOption === $orderType ? 'selected' : '' ?>>
                                        <?= e(ucfirst($typeOption)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-field form-field-actions">
                            <button class="btn btn-primary" type="submit">Áp dụng</button>
                            <a class="btn btn-secondary" href="<?= e(APP_URL) ?>/admin/orders/index.php">Xóa lọc</a>
                        </div>
                    </div>
                </form>
            </section>

            <section class="admin-panel">
                <div class="admin-panel-head">
                    <div>
                        <span class="admin-panel-kicker">Order table</span>
                        <h2>Danh sách đơn hàng</h2>
                        <p>Hiển thị <?= count($orders) ?> / <?= $totalRows ?> đơn phù hợp.</p>
                    </div>
                </div>

                <?php if ($orders === []): ?>
                    <div class="empty-mini-card">
                        <p>Chưa có đơn hàng phù hợp với bộ lọc hiện tại.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Mã đơn</th>
                                    <th>Khách hàng</th>
                                    <th>Loại đơn</th>
                                    <th>Trạng thái</th>
                                    <th>Thanh toán</th>
                                    <th>Sản phẩm</th>
                                    <th>Tổng tiền</th>
                                    <th>Ngày tạo</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($order['order_code']) ?></strong>
                                            <div class="muted-small"><?= e($order['customer_phone']) ?></div>
                                        </td>
                                        <td>
                                            <strong><?= e($order['customer_name']) ?></strong>
                                            <div class="muted-small"><?= e($order['customer_email']) ?></div>
                                        </td>
                                        <td><span class="order-type-pill"><?= e(ucfirst((string) $order['order_type'])) ?></span></td>
                                        <td>
                                            <span class="status-pill <?= e(order_status_class((string) $order['status'])) ?>">
                                                <?= e(order_status_label((string) $order['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-pill <?= e(payment_status_class((string) $order['payment_status'])) ?>">
                                                <?= e(payment_status_label((string) $order['payment_status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= (int) $order['item_count'] ?></td>
                                        <td><strong><?= format_price($order['total_amount']) ?></strong></td>
                                        <td><?= e(date('d/m/Y H:i', strtotime((string) $order['created_at']))) ?></td>
                                        <td>
                                            <a class="btn btn-secondary btn-sm" href="<?= e(APP_URL) ?>/admin/orders/detail.php?id=<?= (int) $order['id'] ?>">Chi tiết</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="admin-pagination">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a class="pagination-link <?= $i === $page ? 'is-active' : '' ?>"
                                   href="<?= e(APP_URL) ?>/admin/orders/index.php?<?= e(build_admin_query(['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>
</body>
</html>