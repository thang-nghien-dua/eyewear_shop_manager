<?php

require_once __DIR__ . '/../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

admin_only();

$db = Database::connect();

$summaryStmt = $db->query(
    "SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN status IN ('pending', 'awaiting_stock', 'checking_prescription') THEN 1 ELSE 0 END) AS pending_like_orders,
        SUM(CASE WHEN status IN ('confirmed', 'processing', 'lens_processing', 'shipping') THEN 1 ELSE 0 END) AS in_progress_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_orders,
        COALESCE(SUM(CASE WHEN status NOT IN ('cancelled', 'refunded') THEN total_amount ELSE 0 END), 0) AS gross_revenue,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) AS completed_revenue
     FROM orders"
);
$summary = $summaryStmt->fetch() ?: [
    'total_orders' => 0,
    'pending_like_orders' => 0,
    'in_progress_orders' => 0,
    'completed_orders' => 0,
    'gross_revenue' => 0,
    'completed_revenue' => 0,
];

$entityStmt = $db->query(
    "SELECT
        (SELECT COUNT(*) FROM products WHERE status = 'active') AS active_products,
        (SELECT COUNT(*) FROM categories WHERE is_active = 1) AS active_categories,
        (SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.name = 'customer') AS customers"
);
$entities = $entityStmt->fetch() ?: [
    'active_products' => 0,
    'active_categories' => 0,
    'customers' => 0,
];

$statusStmt = $db->query(
    "SELECT status, COUNT(*) AS total
     FROM orders
     GROUP BY status
     ORDER BY total DESC, status ASC"
);
$statusRows = $statusStmt->fetchAll();
$statusTotal = array_sum(array_map(static fn(array $row): int => (int) $row['total'], $statusRows));

$typeStmt = $db->query(
    "SELECT order_type, COUNT(*) AS total
     FROM orders
     GROUP BY order_type
     ORDER BY total DESC, order_type ASC"
);
$typeRows = $typeStmt->fetchAll();

$recentOrdersStmt = $db->query(
    "SELECT
        o.id, o.order_code, o.order_type, o.status, o.customer_name, o.total_amount, o.created_at,
        COUNT(oi.id) AS item_count
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     GROUP BY o.id
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT 8"
);
$recentOrders = $recentOrdersStmt->fetchAll();

$attentionStmt = $db->query(
    "SELECT
        o.id, o.order_code, o.status, o.order_type, o.customer_name, o.total_amount, o.created_at
     FROM orders o
     WHERE o.status IN ('pending', 'awaiting_stock', 'checking_prescription')
     ORDER BY o.created_at ASC, o.id ASC
     LIMIT 6"
);
$attentionOrders = $attentionStmt->fetchAll();

$activityStmt = $db->query(
    "SELECT
        l.order_id, l.old_status, l.new_status, l.note, l.created_at, o.order_code, u.full_name AS changed_by_name
     FROM order_status_logs l
     INNER JOIN orders o ON o.id = l.order_id
     LEFT JOIN users u ON u.id = l.changed_by
     ORDER BY l.created_at DESC, l.id DESC
     LIMIT 8"
);
$recentActivities = $activityStmt->fetchAll();

$pageTitle = 'Admin Dashboard - ' . APP_NAME;
$pageDescription = 'Dashboard quản trị đơn hàng và vận hành ' . APP_NAME;
$adminPageTitle = 'Dashboard eCommerce';
$adminPageSubtitle = 'Theo dõi tổng quan đơn hàng, doanh thu và các công việc cần xử lý cho shop mắt kính.';

include BASE_PATH . '/app/views/partials/admin-head.php';
?>
<div class="admin-shell">
    <?php include BASE_PATH . '/app/views/partials/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php include BASE_PATH . '/app/views/partials/admin-topbar.php'; ?>

        <main class="admin-dashboard">
            <section class="admin-kpi-grid">
                <article class="admin-kpi-card accent-purple">
                    <div class="admin-kpi-icon"><i class="fi fi-rr-shopping-bag icon"></i></div>
                    <div>
                        <span>Tổng đơn hàng</span>
                        <strong><?= (int) $summary['total_orders'] ?></strong>
                        <small>Tất cả đơn đã phát sinh trong hệ thống</small>
                    </div>
                </article>

                <article class="admin-kpi-card accent-amber">
                    <div class="admin-kpi-icon"><i class="fi fi-rr-time-fast icon"></i></div>
                    <div>
                        <span>Cần xử lý</span>
                        <strong><?= (int) $summary['pending_like_orders'] ?></strong>
                        <small>Pending, awaiting stock, checking prescription</small>
                    </div>
                </article>

                <article class="admin-kpi-card accent-blue">
                    <div class="admin-kpi-icon"><i class="fi fi-rr-truck-side icon"></i></div>
                    <div>
                        <span>Đang vận hành</span>
                        <strong><?= (int) $summary['in_progress_orders'] ?></strong>
                        <small>Confirmed, processing, shipping</small>
                    </div>
                </article>

                <article class="admin-kpi-card accent-green">
                    <div class="admin-kpi-icon"><i class="fi fi-rr-badge-check icon"></i></div>
                    <div>
                        <span>Hoàn tất</span>
                        <strong><?= (int) $summary['completed_orders'] ?></strong>
                        <small>Đơn đã hoàn thành giao và xử lý</small>
                    </div>
                </article>
            </section>

            <section class="admin-overview-grid">
                <div class="admin-panel hero-panel">
                    <div class="admin-panel-head">
                        <div>
                            <span class="admin-panel-kicker">Hiệu suất vận hành</span>
                            <h2>Tổng quan shop hôm nay</h2>
                            <p>Dữ liệu thật lấy từ orders, order_items, order_status_logs và catalog hiện tại.</p>
                        </div>
                        <a href="<?= e(APP_URL) ?>/admin/orders/index.php" class="btn btn-secondary btn-sm">Quản lý đơn</a>
                    </div>

                    <div class="admin-metric-strip">
                        <div class="metric-pill">
                            <span>Doanh thu ghi nhận</span>
                            <strong><?= format_price($summary['gross_revenue']) ?></strong>
                        </div>
                        <div class="metric-pill">
                            <span>Doanh thu hoàn tất</span>
                            <strong><?= format_price($summary['completed_revenue']) ?></strong>
                        </div>
                        <div class="metric-pill">
                            <span>Sản phẩm active</span>
                            <strong><?= (int) $entities['active_products'] ?></strong>
                        </div>
                        <div class="metric-pill">
                            <span>Khách hàng</span>
                            <strong><?= (int) $entities['customers'] ?></strong>
                        </div>
                    </div>

                    <div class="admin-chart-card-grid">
                        <article class="admin-chart-card">
                            <div class="admin-mini-head">
                                <h3>Phân bố trạng thái đơn</h3>
                                <span><?= (int) $statusTotal ?> đơn</span>
                            </div>

                            <?php if ($statusRows === []): ?>
                                <p class="muted-small">Chưa có dữ liệu trạng thái đơn hàng.</p>
                            <?php else: ?>
                                <div class="status-progress-list">
                                    <?php foreach ($statusRows as $row): ?>
                                        <?php
                                            $count = (int) $row['total'];
                                            $percent = $statusTotal > 0 ? round(($count / $statusTotal) * 100, 1) : 0;
                                        ?>
                                        <div class="status-progress-item">
                                            <div class="status-progress-top">
                                                <span><?= e(order_status_label((string) $row['status'])) ?></span>
                                                <strong><?= $count ?> đơn • <?= $percent ?>%</strong>
                                            </div>
                                            <div class="status-progress-bar">
                                                <span style="width: <?= $percent ?>%"></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </article>

                        <article class="admin-chart-card">
                            <div class="admin-mini-head">
                                <h3>Cơ cấu loại đơn</h3>
                                <span><?= count($typeRows) ?> nhóm</span>
                            </div>

                            <?php if ($typeRows === []): ?>
                                <p class="muted-small">Chưa có dữ liệu loại đơn.</p>
                            <?php else: ?>
                                <div class="admin-type-grid">
                                    <?php foreach ($typeRows as $row): ?>
                                        <div class="admin-type-card">
                                            <span><?= e(ucfirst((string) $row['order_type'])) ?></span>
                                            <strong><?= (int) $row['total'] ?></strong>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="admin-entity-row">
                                <div class="admin-entity-box">
                                    <span>Danh mục active</span>
                                    <strong><?= (int) $entities['active_categories'] ?></strong>
                                </div>
                                <div class="admin-entity-box">
                                    <span>Đơn hoàn tất</span>
                                    <strong><?= (int) $summary['completed_orders'] ?></strong>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

                <aside class="admin-side-stack">
                    <section class="admin-panel">
                        <div class="admin-panel-head compact">
                            <div>
                                <span class="admin-panel-kicker">Ưu tiên</span>
                                <h2>Đơn cần xử lý</h2>
                            </div>
                        </div>

                        <?php if ($attentionOrders === []): ?>
                            <div class="empty-mini-card">
                                <p>Không có đơn chờ xử lý. Hệ thống đang khá ổn.</p>
                            </div>
                        <?php else: ?>
                            <div class="attention-list">
                                <?php foreach ($attentionOrders as $order): ?>
                                    <a class="attention-item" href="<?= e(APP_URL) ?>/admin/orders/detail.php?id=<?= (int) $order['id'] ?>">
                                        <div>
                                            <strong><?= e($order['order_code']) ?></strong>
                                            <p><?= e($order['customer_name']) ?></p>
                                        </div>
                                        <div class="attention-meta">
                                            <span class="status-pill <?= e(order_status_class((string) $order['status'])) ?>">
                                                <?= e(order_status_label((string) $order['status'])) ?>
                                            </span>
                                            <small><?= e(date('d/m H:i', strtotime((string) $order['created_at']))) ?></small>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="admin-panel">
                        <div class="admin-panel-head compact">
                            <div>
                                <span class="admin-panel-kicker">Hoạt động gần đây</span>
                                <h2>Nhật ký trạng thái</h2>
                            </div>
                        </div>

                        <?php if ($recentActivities === []): ?>
                            <div class="empty-mini-card">
                                <p>Chưa có lịch sử cập nhật trạng thái.</p>
                            </div>
                        <?php else: ?>
                            <div class="timeline-list">
                                <?php foreach ($recentActivities as $activity): ?>
                                    <div class="timeline-item">
                                        <span class="timeline-dot"></span>
                                        <div>
                                            <strong><?= e($activity['order_code']) ?></strong>
                                            <p>
                                                <?= e(order_status_label((string) $activity['old_status'])) ?>
                                                → <?= e(order_status_label((string) $activity['new_status'])) ?>
                                            </p>
                                            <small>
                                                <?= e($activity['changed_by_name'] ?: 'System') ?> •
                                                <?= e(date('d/m/Y H:i', strtotime((string) $activity['created_at']))) ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </aside>
            </section>

            <section class="admin-panel">
                <div class="admin-panel-head">
                    <div>
                        <span class="admin-panel-kicker">Bảng đơn hàng</span>
                        <h2>Đơn mới nhất</h2>
                        <p>Xem nhanh trạng thái, giá trị và số sản phẩm trong từng đơn.</p>
                    </div>
                    <a href="<?= e(APP_URL) ?>/admin/orders/index.php" class="btn btn-secondary btn-sm">Xem tất cả</a>
                </div>

                <?php if ($recentOrders === []): ?>
                    <div class="empty-mini-card">
                        <p>Chưa có đơn hàng nào để hiển thị trên dashboard.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <table class="admin-table admin-table-dashboard">
                            <thead>
                                <tr>
                                    <th>Mã đơn</th>
                                    <th>Khách hàng</th>
                                    <th>Loại đơn</th>
                                    <th>Trạng thái</th>
                                    <th>Sản phẩm</th>
                                    <th>Tổng tiền</th>
                                    <th>Ngày tạo</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td><strong><?= e($order['order_code']) ?></strong></td>
                                        <td><?= e($order['customer_name']) ?></td>
                                        <td><span class="order-type-pill"><?= e(ucfirst((string) $order['order_type'])) ?></span></td>
                                        <td>
                                            <span class="status-pill <?= e(order_status_class((string) $order['status'])) ?>">
                                                <?= e(order_status_label((string) $order['status'])) ?>
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
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>
</body>
</html>