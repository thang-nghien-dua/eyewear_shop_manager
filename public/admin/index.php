<?php

require_once __DIR__ . '/../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

admin_only();

$db = Database::connect();
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
$todayStart  = $now->format('Y-m-d 00:00:00');
$monthStart  = $now->format('Y-m-01 00:00:00');

// ── 1. KPI tổng hợp ────────────────────────────────────
$summaryStmt = $db->query(
    "SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN status IN ('pending','awaiting_stock','checking_prescription') THEN 1 ELSE 0 END) AS pending_like_orders,
        SUM(CASE WHEN status IN ('confirmed','processing','lens_processing','shipping') THEN 1 ELSE 0 END) AS in_progress_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_orders,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
        SUM(CASE WHEN cancel_requested = 1 AND status NOT IN ('cancelled','completed','refunded') THEN 1 ELSE 0 END) AS cancel_requests,
        COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','refunded') THEN total_amount ELSE 0 END),0) AS gross_revenue,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END),0) AS completed_revenue
     FROM orders"
);
$summary = $summaryStmt->fetch() ?: [
    'total_orders' => 0, 'pending_like_orders' => 0, 'in_progress_orders' => 0,
    'completed_orders' => 0, 'cancelled_orders' => 0, 'cancel_requests' => 0,
    'gross_revenue' => 0, 'completed_revenue' => 0,
];

// ── 2. KPI hôm nay ─────────────────────────────────────
$todayStmt = $db->prepare(
    "SELECT
        COUNT(*) AS today_orders,
        COALESCE(SUM(CASE WHEN status='completed' THEN total_amount ELSE 0 END),0) AS today_revenue
     FROM orders WHERE created_at >= :start"
);
$todayStmt->execute(['start' => $todayStart]);
$today = $todayStmt->fetch() ?: ['today_orders' => 0, 'today_revenue' => 0];

// ── 3. KPI tháng này ────────────────────────────────────
$monthStmt = $db->prepare(
    "SELECT
        COUNT(*) AS month_orders,
        COALESCE(SUM(CASE WHEN status='completed' THEN total_amount ELSE 0 END),0) AS month_revenue
     FROM orders WHERE created_at >= :start"
);
$monthStmt->execute(['start' => $monthStart]);
$month = $monthStmt->fetch() ?: ['month_orders' => 0, 'month_revenue' => 0];

// ── 4. Entities ─────────────────────────────────────────
$entityStmt = $db->query(
    "SELECT
        (SELECT COUNT(*) FROM products WHERE status='active') AS active_products,
        (SELECT COUNT(*) FROM categories WHERE is_active=1) AS active_categories,
        (SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE r.name='customer') AS customers,
        (SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE r.name='customer' AND u.created_at >= CURDATE()) AS new_customers_today"
);
$entities = $entityStmt->fetch() ?: [
    'active_products' => 0, 'active_categories' => 0,
    'customers' => 0, 'new_customers_today' => 0,
];

// ── 5. Phân bố trạng thái đơn ──────────────────────────
$statusStmt = $db->query(
    "SELECT status, COUNT(*) AS total
     FROM orders GROUP BY status ORDER BY total DESC, status ASC"
);
$statusRows = $statusStmt->fetchAll();
$statusTotal = array_sum(array_column($statusRows, 'total'));

// ── 6. Đơn cần xử lý ───────────────────────────────────
$attentionStmt = $db->query(
    "SELECT o.id, o.order_code, o.status, o.order_type, o.customer_name, o.total_amount, o.created_at, o.cancel_requested
     FROM orders o
     WHERE o.status IN ('pending','awaiting_stock','checking_prescription')
        OR o.cancel_requested = 1
     ORDER BY o.cancel_requested DESC, o.created_at ASC
     LIMIT 8"
);
$attentionOrders = $attentionStmt->fetchAll();

// ── 7. Đơn mới nhất ────────────────────────────────────
$recentOrdersStmt = $db->query(
    "SELECT o.id, o.order_code, o.order_type, o.status, o.customer_name, o.total_amount, o.created_at,
            o.cancel_requested,
            COUNT(oi.id) AS item_count
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     GROUP BY o.id
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT 8"
);
$recentOrders = $recentOrdersStmt->fetchAll();

// ── 8. Hoạt động gần đây ───────────────────────────────
$activityStmt = $db->query(
    "SELECT l.order_id, l.old_status, l.new_status, l.note, l.created_at, o.order_code, u.full_name AS changed_by_name
     FROM order_status_logs l
     INNER JOIN orders o ON o.id = l.order_id
     LEFT JOIN users u ON u.id = l.changed_by
     ORDER BY l.created_at DESC, l.id DESC
     LIMIT 8"
);
$recentActivities = $activityStmt->fetchAll();

// ── 9. Doanh thu 7 ngày gần nhất ───────────────────────
$weekStmt = $db->query(
    "SELECT DATE(created_at) AS d, COALESCE(SUM(total_amount),0) AS rev
     FROM orders WHERE status='completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY d ORDER BY d"
);
$weekRows = $weekStmt->fetchAll();
$weekMap = [];
foreach ($weekRows as $r) $weekMap[$r['d']] = (float)$r['rev'];
$weekLabels = [];
$weekValues = [];
for ($i = 6; $i >= 0; $i--) {
    $d = $now->modify("-{$i} days")->format('Y-m-d');
    $weekLabels[] = $now->modify("-{$i} days")->format('d/m');
    $weekValues[] = $weekMap[$d] ?? 0;
}
$maxWeekVal = max($weekValues) ?: 1;

$pageTitle = 'Dashboard – ' . APP_NAME;
$pageDescription = 'Tổng quan vận hành shop mắt kính LUMINA';
$adminActive = 'dashboard';

include BASE_PATH . '/app/views/partials/admin-head.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>
/* ═══════════════════════════════════════════════════════
   Dashboard – Premium Redesign
   ═══════════════════════════════════════════════════════ */

/* KPI Grid */
.dash-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.75rem;
}
.dash-kpi {
    background: #fff;
    border-radius: 18px;
    padding: 1.4rem 1.5rem 1.25rem;
    box-shadow: 0 2px 12px rgba(0,0,0,.055);
    display: flex; align-items: flex-start; gap: 1rem;
    transition: transform .2s, box-shadow .2s;
    position: relative; overflow: hidden;
}
.dash-kpi::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0;
    height: 3px; border-radius: 18px 18px 0 0;
}
.dash-kpi.green::before  { background: linear-gradient(90deg,#10b981,#34d399); }
.dash-kpi.blue::before   { background: linear-gradient(90deg,#3b82f6,#60a5fa); }
.dash-kpi.amber::before  { background: linear-gradient(90deg,#f59e0b,#fcd34d); }
.dash-kpi.purple::before { background: linear-gradient(90deg,#8b5cf6,#a78bfa); }
.dash-kpi.rose::before   { background: linear-gradient(90deg,#ef4444,#f87171); }
.dash-kpi.indigo::before { background: linear-gradient(90deg,#6366f1,#818cf8); }
.dash-kpi:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(0,0,0,.1); }
.dash-kpi-ico {
    width: 48px; height: 48px; border-radius: 13px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; flex-shrink: 0;
}
.green  .dash-kpi-ico { background: #ecfdf5; color: #059669; }
.blue   .dash-kpi-ico { background: #eff6ff; color: #2563eb; }
.amber  .dash-kpi-ico { background: #fffbeb; color: #d97706; }
.purple .dash-kpi-ico { background: #f5f3ff; color: #7c3aed; }
.rose   .dash-kpi-ico { background: #fff1f2; color: #e11d48; }
.indigo .dash-kpi-ico { background: #eef2ff; color: #4f46e5; }
.dash-kpi label { font-size: .75rem; font-weight: 700; color: #9ca3af; letter-spacing: .05em; text-transform: uppercase; }
.dash-kpi strong { display: block; font-size: 1.6rem; font-weight: 900; color: #111827; line-height: 1.2; margin: .2rem 0 .1rem; }
.dash-kpi small { font-size: .78rem; color: #6b7280; }
.dash-kpi .badge { display: inline-flex; align-items: center; gap: .25rem; border-radius: 99px; padding: .15rem .55rem; font-size: .72rem; font-weight: 800; }
.badge-up   { background: #ecfdf5; color: #059669; }
.badge-warn { background: #fffbeb; color: #d97706; }
.badge-red  { background: #fff1f2; color: #e11d48; }

/* Alert banner */
.dash-alert {
    display: flex; align-items: center; gap: 1rem;
    background: linear-gradient(135deg,#fef2f2,#fff1f2);
    border: 1.5px solid #fca5a5; border-radius: 14px;
    padding: 1rem 1.5rem; margin-bottom: 1.5rem;
}
.dash-alert-icon { font-size: 1.6rem; }
.dash-alert p { margin: 0; font-size: .92rem; color: #991b1b; font-weight: 700; }
.dash-alert a { color: #dc2626; text-decoration: underline; }

/* Main grid */
.dash-main-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

/* Card base */
.dash-card {
    background: #fff;
    border-radius: 18px;
    padding: 1.5rem 1.75rem;
    box-shadow: 0 2px 12px rgba(0,0,0,.055);
}
.dash-card-head {
    display: flex; align-items: flex-start; justify-content: space-between;
    margin-bottom: 1.25rem; flex-wrap: wrap; gap: .5rem;
}
.dash-card-head h2 { font-size: 1rem; font-weight: 800; color: #111827; margin: 0; }
.dash-card-head p  { font-size: .8rem; color: #9ca3af; margin: .2rem 0 0; }

/* Chart */
.chart-wrap { position: relative; height: 200px; }

/* Today mini-strip */
.today-strip {
    display: grid; grid-template-columns: repeat(3,1fr);
    gap: .75rem; margin-top: 1.25rem;
}
.today-mini {
    background: #f8fafc; border-radius: 12px;
    padding: .85rem 1rem; text-align: center;
}
.today-mini span { display: block; font-size: .73rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .05em; }
.today-mini strong { display: block; font-size: 1.25rem; font-weight: 900; color: #111827; margin-top: .2rem; }

/* Attention orders */
.att-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: .7rem .5rem; border-bottom: 1px solid #f1f5f9;
    transition: background .15s; border-radius: 8px; text-decoration: none;
    gap: .5rem;
}
.att-item:hover { background: #f8fafc; }
.att-item-left strong { display: block; font-size: .88rem; font-weight: 800; color: #111827; }
.att-item-left p { margin: 0; font-size: .78rem; color: #6b7280; }
.att-item-right { display: flex; flex-direction: column; align-items: flex-end; gap: .25rem; flex-shrink: 0; }

/* Status progress */
.stat-prog { margin-bottom: .85rem; }
.stat-prog-top { display: flex; justify-content: space-between; font-size: .82rem; font-weight: 700; color: #374151; margin-bottom: .3rem; }
.stat-prog-bar { height: 6px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
.stat-prog-bar-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg,#3b82f6,#6366f1); transition: width .6s ease; }

/* Timeline */
.tl-item { display: flex; gap: .75rem; padding: .7rem 0; border-bottom: 1px solid #f1f5f9; }
.tl-dot { width: 8px; height: 8px; border-radius: 50%; background: #3b82f6; flex-shrink: 0; margin-top: 5px; }
.tl-item:last-child { border-bottom: none; }
.tl-item strong { font-size: .85rem; font-weight: 800; color: #111827; display: block; }
.tl-item p { margin: .1rem 0 .2rem; font-size: .78rem; color: #6b7280; }
.tl-item small { font-size: .73rem; color: #9ca3af; }

/* Orders table */
.dash-table-wrap { overflow-x: auto; margin-top: .5rem; }
.dash-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.dash-table th { padding: .65rem .9rem; text-align: left; font-size: .73rem; font-weight: 800; color: #9ca3af; text-transform: uppercase; letter-spacing: .06em; border-bottom: 1.5px solid #f1f5f9; }
.dash-table td { padding: .75rem .9rem; border-bottom: 1px solid #f8fafc; color: #374151; vertical-align: middle; }
.dash-table tbody tr:hover td { background: #f8fafc; }
.dash-table tbody tr:last-child td { border-bottom: none; }

/* Cancel badge */
.cancel-badge { display: inline-flex; align-items: center; gap: .25rem; background: #fef2f2; color: #b91c1c; border-radius: 99px; font-size: .7rem; font-weight: 800; padding: .15rem .5rem; }

/* Responsive */
@media (max-width: 1200px) { .dash-kpi-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 900px)  { .dash-main-grid { grid-template-columns: 1fr; } .dash-kpi-grid { grid-template-columns: repeat(2,1fr); } }
</style>

<div class="admin-shell">
    <?php include BASE_PATH . '/app/views/partials/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php include BASE_PATH . '/app/views/partials/admin-topbar.php'; ?>

        <main class="admin-dashboard">

            <!-- Hero Header -->
            <div class="admin-hero-card" style="margin-bottom:1.75rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
                    <div>
                        <span class="eyebrow">TỔNG QUAN</span>
                        <h1>Dashboard</h1>
                        <p>Dữ liệu cập nhật theo thời gian thực – <?= $now->format('d/m/Y H:i') ?></p>
                    </div>
                    <div style="display:flex;gap:.75rem;">
                        <a href="<?= e(APP_URL) ?>/admin/orders/index.php" class="btn btn-secondary btn-sm">Quản lý đơn</a>
                        <a href="<?= e(APP_URL) ?>/admin/reports/index.php" class="btn btn-primary btn-sm">Xem báo cáo</a>
                    </div>
                </div>
            </div>

            <!-- Alert: Yêu cầu hủy đơn -->
            <?php if ((int)$summary['cancel_requests'] > 0): ?>
                <div class="dash-alert">
                    <div class="dash-alert-icon">🚨</div>
                    <div>
                        <p>Có <strong><?= (int)$summary['cancel_requests'] ?> đơn hàng</strong> đang có yêu cầu hủy từ khách hàng cần xem xét.
                        <a href="<?= e(APP_URL) ?>/admin/orders/index.php">Xử lý ngay →</a></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- KPI Grid – Row 1: Đơn hàng -->
            <div class="dash-kpi-grid">
                <a href="<?= e(APP_URL) ?>/admin/orders/index.php" class="dash-kpi purple" style="text-decoration:none;cursor:pointer;">
                    <div class="dash-kpi-ico"><i class="fi fi-rr-shopping-bag icon"></i></div>
                    <div>
                        <label>Tổng đơn</label>
                        <strong><?= (int)$summary['total_orders'] ?></strong>
                        <small>Tất cả đơn trong hệ thống</small>
                    </div>
                </a>
                <a href="<?= e(APP_URL) ?>/admin/orders/index.php?status=pending" class="dash-kpi amber" style="text-decoration:none;cursor:pointer;">
                    <div class="dash-kpi-ico"><i class="fi fi-rr-time-fast icon"></i></div>
                    <div>
                        <label>Cần xử lý</label>
                        <strong><?= (int)$summary['pending_like_orders'] ?></strong>
                        <small>Chờ xác nhận / kiểm tra</small>
                    </div>
                </a>
                <a href="<?= e(APP_URL) ?>/admin/orders/index.php?status=confirmed" class="dash-kpi blue" style="text-decoration:none;cursor:pointer;">
                    <div class="dash-kpi-ico"><i class="fi fi-rr-truck-side icon"></i></div>
                    <div>
                        <label>Đang vận hành</label>
                        <strong><?= (int)$summary['in_progress_orders'] ?></strong>
                        <small>Đang xử lý / giao hàng</small>
                    </div>
                </a>
                <a href="<?= e(APP_URL) ?>/admin/orders/index.php?status=completed" class="dash-kpi green" style="text-decoration:none;cursor:pointer;">
                    <div class="dash-kpi-ico"><i class="fi fi-rr-badge-check icon"></i></div>
                    <div>
                        <label>Hoàn tất</label>
                        <strong><?= (int)$summary['completed_orders'] ?></strong>
                        <small>Đã giao thành công</small>
                    </div>
                </a>
            </div>

            <!-- KPI Grid – Row 2: Doanh thu + Khách hàng -->
            <div class="dash-kpi-grid" style="margin-bottom:1.75rem;">
                <a href="<?= e(APP_URL) ?>/admin/reports/index.php" class="dash-kpi green" style="text-decoration:none;cursor:pointer;">
                    <div class="dash-kpi-ico"><i class="fi fi-rr-usd icon"></i></div>
                    <div>
                        <label>Doanh thu hôm nay</label>
                        <strong><?= format_price($today['today_revenue']) ?></strong>
                        <small><?= (int)$today['today_orders'] ?> đơn phát sinh hôm nay</small>
                    </div>
                </a>
                <a href="<?= e(APP_URL) ?>/admin/reports/index.php" class="dash-kpi indigo" style="text-decoration:none;cursor:pointer;">
                    <div class="dash-kpi-ico"><i class="fi fi-rr-chart-histogram icon"></i></div>
                    <div>
                        <label>Doanh thu tháng <?= $now->format('m') ?></label>
                        <strong><?= format_price($month['month_revenue']) ?></strong>
                        <small><?= (int)$month['month_orders'] ?> đơn trong tháng</small>
                    </div>
                </a>
                <a href="<?= e(APP_URL) ?>/admin/users/index.php" class="dash-kpi purple" style="text-decoration:none;cursor:pointer;">
                    <div class="dash-kpi-ico"><i class="fi fi-rr-users icon"></i></div>
                    <div>
                        <label>Khách hàng</label>
                        <strong><?= (int)$entities['customers'] ?></strong>
                        <small>
                            <?php if ((int)$entities['new_customers_today'] > 0): ?>
                                <span class="badge badge-up">+<?= (int)$entities['new_customers_today'] ?> hôm nay</span>
                            <?php else: ?>
                                Tổng người đăng ký
                            <?php endif; ?>
                        </small>
                    </div>
                </a>
                <a href="<?= e(APP_URL) ?>/admin/products/index.php" class="dash-kpi blue" style="text-decoration:none;cursor:pointer;">
                    <div class="dash-kpi-ico"><i class="fi fi-rr-box icon"></i></div>
                    <div>
                        <label>Sản phẩm đang bán</label>
                        <strong><?= (int)$entities['active_products'] ?></strong>
                        <small><?= (int)$entities['active_categories'] ?> danh mục hoạt động</small>
                    </div>
                </a>
            </div>

            <!-- Main 2-col Grid -->
            <div class="dash-main-grid">

                <!-- Left: Chart 7 ngày + Phân bố trạng thái -->
                <div style="display:flex;flex-direction:column;gap:1.5rem;">

                    <!-- Biểu đồ doanh thu 7 ngày -->
                    <div class="dash-card">
                        <div class="dash-card-head">
                            <div>
                                <h2>📈 Doanh thu 7 ngày gần nhất</h2>
                                <p>Đơn hàng đã hoàn tất · <?= $now->modify('-6 days')->format('d/m') ?> – <?= $now->format('d/m/Y') ?></p>
                            </div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="weekChart"></canvas>
                        </div>

                        <!-- Today mini strip -->
                        <div class="today-strip">
                            <div class="today-mini">
                                <span>Hôm nay</span>
                                <strong><?= (int)$today['today_orders'] ?></strong>
                                <span style="font-size:.72rem;color:#6b7280;">đơn hàng</span>
                            </div>
                            <div class="today-mini">
                                <span>Tháng này</span>
                                <strong><?= (int)$month['month_orders'] ?></strong>
                                <span style="font-size:.72rem;color:#6b7280;">đơn hàng</span>
                            </div>
                            <div class="today-mini">
                                <span>Tổng cộng</span>
                                <strong><?= (int)$summary['total_orders'] ?></strong>
                                <span style="font-size:.72rem;color:#6b7280;">đơn hàng</span>
                            </div>
                        </div>
                    </div>

                    <!-- Phân bố trạng thái -->
                    <div class="dash-card">
                        <div class="dash-card-head">
                            <div>
                                <h2>🗂️ Phân bố trạng thái đơn</h2>
                                <p>Tổng <?= (int)$statusTotal ?> đơn trong hệ thống</p>
                            </div>
                        </div>
                        <?php if ($statusRows): ?>
                            <?php foreach ($statusRows as $row):
                                $count = (int)$row['total'];
                                $pct   = $statusTotal > 0 ? round(($count / $statusTotal) * 100, 1) : 0;
                            ?>
                                <div class="stat-prog">
                                    <div class="stat-prog-top">
                                        <span><?= e(order_status_label((string)$row['status'])) ?></span>
                                        <strong><?= $count ?> đơn &nbsp;·&nbsp; <?= $pct ?>%</strong>
                                    </div>
                                    <div class="stat-prog-bar">
                                        <div class="stat-prog-bar-fill" style="width:<?= $pct ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-mini-card"><p>Chưa có dữ liệu trạng thái.</p></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right: Đơn cần xử lý + Nhật ký hoạt động -->
                <div style="display:flex;flex-direction:column;gap:1.5rem;">

                    <!-- Đơn ưu tiên / cần xử lý -->
                    <div class="dash-card">
                        <div class="dash-card-head">
                            <div>
                                <h2>⚡ Đơn cần xử lý</h2>
                                <p>Ưu tiên: yêu cầu hủy &amp; đơn chờ</p>
                            </div>
                            <a href="<?= e(APP_URL) ?>/admin/orders/index.php" style="font-size:.78rem;font-weight:700;color:#3b82f6;text-decoration:none;">Xem tất cả →</a>
                        </div>
                        <?php if ($attentionOrders): ?>
                            <?php foreach ($attentionOrders as $order): ?>
                                <a class="att-item" href="<?= e(APP_URL) ?>/admin/orders/detail.php?id=<?= (int)$order['id'] ?>">
                                    <div class="att-item-left">
                                        <strong>
                                            <?= e($order['order_code']) ?>
                                            <?php if (!empty($order['cancel_requested'])): ?>
                                                <span class="cancel-badge">🚨 Yêu cầu hủy</span>
                                            <?php endif; ?>
                                        </strong>
                                        <p><?= e($order['customer_name']) ?> · <?= format_price($order['total_amount']) ?></p>
                                    </div>
                                    <div class="att-item-right">
                                        <span class="status-pill <?= e(order_status_class((string)$order['status'])) ?>"><?= e(order_status_label((string)$order['status'])) ?></span>
                                        <small style="color:#9ca3af;"><?= e(date('d/m H:i', strtotime((string)$order['created_at']))) ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-mini-card" style="text-align:center;padding:1.5rem;">
                                <div style="font-size:2rem;margin-bottom:.5rem;">✅</div>
                                <p>Không có đơn cần xử lý. Mọi thứ ổn!</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Nhật ký hoạt động -->
                    <div class="dash-card">
                        <div class="dash-card-head">
                            <div>
                                <h2>🕐 Nhật ký trạng thái</h2>
                                <p>8 cập nhật gần nhất</p>
                            </div>
                        </div>
                        <?php if ($recentActivities): ?>
                            <?php foreach ($recentActivities as $act): ?>
                                <div class="tl-item">
                                    <div class="tl-dot"></div>
                                    <div>
                                        <strong><?= e($act['order_code']) ?></strong>
                                        <p><?= e(order_status_label((string)$act['old_status'])) ?> → <?= e(order_status_label((string)$act['new_status'])) ?></p>
                                        <small><?= e($act['changed_by_name'] ?: 'System') ?> · <?= e(date('d/m/Y H:i', strtotime((string)$act['created_at']))) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-mini-card"><p>Chưa có nhật ký nào.</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Bảng đơn hàng mới nhất -->
            <div class="dash-card">
                <div class="dash-card-head">
                    <div>
                        <h2>📋 Đơn hàng mới nhất</h2>
                        <p>8 đơn vừa phát sinh gần đây</p>
                    </div>
                    <a href="<?= e(APP_URL) ?>/admin/orders/index.php" class="btn btn-secondary btn-sm">Xem tất cả đơn</a>
                </div>
                <?php if ($recentOrders): ?>
                    <div class="dash-table-wrap">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Mã đơn</th>
                                    <th>Khách hàng</th>
                                    <th>Loại đơn</th>
                                    <th>Trạng thái</th>
                                    <th>SP</th>
                                    <th>Tổng tiền</th>
                                    <th>Ngày đặt</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($order['order_code']) ?></strong>
                                            <?php if (!empty($order['cancel_requested'])): ?>
                                                <div><span class="cancel-badge">🚨 Yêu cầu hủy</span></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($order['customer_name']) ?></td>
                                        <td><span class="order-type-pill"><?= e(order_type_label((string)$order['order_type'])) ?></span></td>
                                        <td>
                                            <span class="status-pill <?= e(order_status_class((string)$order['status'])) ?>">
                                                <?= e(order_status_label((string)$order['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= (int)$order['item_count'] ?></td>
                                        <td><strong><?= format_price($order['total_amount']) ?></strong></td>
                                        <td style="white-space:nowrap;"><?= e(date('d/m/Y H:i', strtotime((string)$order['created_at']))) ?></td>
                                        <td><a class="btn btn-secondary btn-sm" href="<?= e(APP_URL) ?>/admin/orders/detail.php?id=<?= (int)$order['id'] ?>">Chi tiết</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-mini-card" style="text-align:center;padding:2rem;">
                        <p>Chưa có đơn hàng nào trong hệ thống.</p>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

<script>
// ── Biểu đồ doanh thu 7 ngày ─────────────────────────
const weekLabels = <?= json_encode($weekLabels, JSON_UNESCAPED_UNICODE) ?>;
const weekValues = <?= json_encode($weekValues) ?>;

const ctx = document.getElementById('weekChart').getContext('2d');
const grad = ctx.createLinearGradient(0, 0, 0, 200);
grad.addColorStop(0, 'rgba(99,102,241,.35)');
grad.addColorStop(1, 'rgba(99,102,241,.02)');

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: weekLabels,
        datasets: [{
            label: 'Doanh thu',
            data: weekValues,
            backgroundColor: weekValues.map(v => v > 0 ? 'rgba(99,102,241,.85)' : 'rgba(99,102,241,.2)'),
            borderColor: '#6366f1',
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ' ' + parseFloat(ctx.raw || 0).toLocaleString('vi-VN') + ' ₫'
                }
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { font: { size: 12, weight: '700' }, color: '#6b7280' }
            },
            y: {
                grid: { color: '#f1f5f9' },
                ticks: {
                    font: { size: 11 }, color: '#9ca3af',
                    callback: v => {
                        if (v >= 1e9) return (v/1e9).toFixed(1)+'B';
                        if (v >= 1e6) return (v/1e6).toFixed(1)+'M';
                        return v.toLocaleString('vi-VN');
                    }
                }
            }
        },
        animation: { duration: 700 }
    }
});
</script>

</body>
</html>