<?php

require_once __DIR__ . '/../../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

admin_only();

$db = Database::connect();

// Lọc theo khoảng thời gian
$range = trim((string) ($_GET['range'] ?? 'this_month'));
$startDate = '';
$endDate = '';

switch ($range) {
    case 'last_30':
        $startDate = date('Y-m-d H:i:s', strtotime('-30 days'));
        break;
    case 'this_year':
        $startDate = date('Y-01-01 00:00:00');
        break;
    case 'all':
        $startDate = '1970-01-01 00:00:00';
        break;
    case 'this_month':
    default:
        $startDate = date('Y-m-01 00:00:00');
        $range = 'this_month';
        break;
}

// 1. KPI Cards queries
$kpiStmt = $db->prepare('
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(CASE WHEN status = "completed" THEN total_amount ELSE 0 END), 0) AS revenue_completed,
        COALESCE(SUM(total_amount), 0) AS revenue_gross,
        COALESCE(AVG(CASE WHEN status = "completed" THEN total_amount ELSE NULL END), 0) AS avg_order_value,
        SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) AS total_cancelled
    FROM orders
    WHERE created_at >= :start_date
');
$kpiStmt->execute(['start_date' => $startDate]);
$kpi = $kpiStmt->fetch();

// 2. Doanh thu theo tháng (12 tháng qua)
$monthlyStmt = $db->query('
    SELECT DATE_FORMAT(created_at, "%m/%Y") AS month_label, SUM(total_amount) AS monthly_revenue
    FROM orders
    WHERE status = "completed"
    GROUP BY month_label
    ORDER BY MIN(created_at) ASC
    LIMIT 12
');
$monthlyData = $monthlyStmt->fetchAll();
$maxMonthlyRevenue = count($monthlyData) > 0 ? max(array_column($monthlyData, 'monthly_revenue')) : 1;

// 3. Top sản phẩm bán chạy nhất
$productStmt = $db->prepare('
    SELECT oi.product_name, SUM(oi.quantity) AS total_qty, SUM(oi.line_total) AS total_sales
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE o.status = "completed" AND o.created_at >= :start_date
    GROUP BY oi.product_name
    ORDER BY total_qty DESC
    LIMIT 6
');
$productStmt->execute(['start_date' => $startDate]);
$products = $productStmt->fetchAll();
$maxProductQty = count($products) > 0 ? max(array_column($products, 'total_qty')) : 1;

// 4. Doanh thu theo danh mục
$categoryStmt = $db->prepare('
    SELECT c.name AS category_name, SUM(oi.quantity) AS total_qty, SUM(oi.line_total) AS total_sales
    FROM order_items oi
    JOIN product_variants pv ON pv.id = oi.product_variant_id
    JOIN products p ON p.id = pv.product_id
    JOIN categories c ON c.id = p.category_id
    JOIN orders o ON o.id = oi.order_id
    WHERE o.status = "completed" AND o.created_at >= :start_date
    GROUP BY c.name
    ORDER BY total_sales DESC
');
$categoryStmt->execute(['start_date' => $startDate]);
$categories = $categoryStmt->fetchAll();
$maxCategorySales = count($categories) > 0 ? max(array_column($categories, 'total_sales')) : 1;

$pageTitle = 'Admin - Báo cáo doanh thu';
$pageDescription = 'Xem thống kê doanh thu, sản phẩm bán chạy và cơ cấu danh mục.';
$adminActive = 'reports';

include BASE_PATH . '/app/views/partials/admin-head.php';
?>
<style>
.filter-btn {
    padding: .45rem 1.1rem;
    border-radius: 6px;
    border: 1.5px solid #dbe4e7;
    background: #fff;
    color: #4a5568;
    font-weight: 600;
    font-size: .85rem;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}
.filter-btn.active, .filter-btn:hover {
    border-color: #696cff;
    background: #696cff;
    color: #fff;
}
</style>
<div class="admin-shell">
    <?php include BASE_PATH . '/app/views/partials/admin-sidebar.php'; ?>

    <main class="admin-main">
        <?php include BASE_PATH . '/app/views/partials/admin-topbar.php'; ?>

        <section class="admin-dashboard">
            <div class="admin-hero-card">
                <div>
                    <span class="eyebrow">DỮ LIỆU KINH DOANH</span>
                    <h1>Báo cáo doanh thu & Thống kê</h1>
                    <p>Phân tích hiệu quả kinh doanh, doanh thu thực tế và xu hướng tiêu dùng sản phẩm mắt kính.</p>
                </div>
            </div>

            <!-- Bộ lọc thời gian -->
            <div class="admin-filter-card" style="display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;">
                <span style="font-weight:700; color:#4a5568; font-size:.88rem; margin-right: 1rem;">Thời gian:</span>
                <a href="?range=this_month" class="filter-btn <?= $range === 'this_month' ? 'active' : '' ?>">Tháng này</a>
                <a href="?range=last_30" class="filter-btn <?= $range === 'last_30' ? 'active' : '' ?>">30 ngày qua</a>
                <a href="?range=this_year" class="filter-btn <?= $range === 'this_year' ? 'active' : '' ?>">Năm nay</a>
                <a href="?range=all" class="filter-btn <?= $range === 'all' ? 'active' : '' ?>">Tất cả thời gian</a>
            </div>

            <!-- KPI Grid -->
            <section class="admin-kpi-grid">
                <article class="admin-kpi-card accent-green">
                    <div class="admin-kpi-icon"><i class="fi fi-rr-usd icon"></i></div>
                    <div>
                        <span>Doanh thu hoàn tất</span>
                        <strong><?= format_price($kpi['revenue_completed']) ?></strong>
                        <small>Từ các đơn hàng hoàn tất</small>
                    </div>
                </article>

                <article class="admin-kpi-card accent-purple">
                    <div class="admin-kpi-icon"><i class="fi fi-rr-shopping-bag icon"></i></div>
                    <div>
                        <span>Doanh thu ghi nhận</span>
                        <strong><?= format_price($kpi['revenue_gross']) ?></strong>
                        <small>Tổng trị giá tất cả các đơn hàng</small>
                    </div>
                </article>

                <article class="admin-kpi-card accent-blue">
                    <div class="admin-kpi-icon"><i class="fi fi-rr-receipt icon"></i></div>
                    <div>
                        <span>Tổng số đơn đặt</span>
                        <strong><?= (int) $kpi['total_orders'] ?> đơn</strong>
                        <small>Số đơn phát sinh</small>
                    </div>
                </article>

                <article class="admin-kpi-card accent-amber">
                    <div class="admin-kpi-icon"><i class="fi fi-rr-calculator icon"></i></div>
                    <div>
                        <span>Giá trị TB đơn</span>
                        <strong><?= format_price($kpi['avg_order_value']) ?></strong>
                        <small>Doanh thu TB trên một đơn hàng</small>
                    </div>
                </article>
            </section>

            <!-- Biểu đồ phân tích doanh thu -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-top: 2rem;">
                <!-- Doanh thu theo tháng -->
                <section class="admin-panel">
                    <div class="admin-panel-head">
                        <div>
                            <h2>Xu hướng doanh thu theo tháng</h2>
                            <p>Doanh thu của các đơn hàng đã hoàn tất thành công.</p>
                        </div>
                    </div>

                    <div style="margin-top: 2rem; display: flex; flex-direction: column; gap: 1rem;">
                        <?php if ($monthlyData === []): ?>
                            <div class="empty-mini-card">Chưa có dữ liệu doanh thu tháng.</div>
                        <?php else: ?>
                            <?php foreach ($monthlyData as $month): ?>
                                <?php
                                    $revenue = (float) $month['monthly_revenue'];
                                    $percent = round(($revenue / $maxMonthlyRevenue) * 100, 1);
                                ?>
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <span style="width: 70px; font-weight: 700; font-size: 0.9rem; color: #1a2e4a;"><?= e($month['month_label']) ?></span>
                                    <div style="flex: 1; height: 24px; background: #e9eff2; border-radius: 6px; overflow: hidden; position: relative;">
                                        <div style="width: <?= $percent ?>%; height: 100%; background: linear-gradient(90deg, #1a2e4a, #2a4365); border-radius: 6px; transition: width 0.5s ease;"></div>
                                    </div>
                                    <span style="width: 120px; text-align: right; font-weight: 800; font-size: 0.9rem; color: #1a2e4a;"><?= format_price($revenue) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Cơ cấu danh mục -->
                <section class="admin-panel">
                    <div class="admin-panel-head">
                        <div>
                            <h2>Cơ cấu danh mục</h2>
                            <p>Doanh số theo danh mục sản phẩm.</p>
                        </div>
                    </div>

                    <div style="margin-top: 2rem; display: flex; flex-direction: column; gap: 1.25rem;">
                        <?php if ($categories === []): ?>
                            <div class="empty-mini-card">Chưa có doanh số theo danh mục.</div>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                                <?php
                                    $sales = (float) $cat['total_sales'];
                                    $percent = round(($sales / $maxCategorySales) * 100, 1);
                                ?>
                                <div>
                                    <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 0.88rem; margin-bottom: 0.25rem;">
                                        <span><?= e($cat['category_name']) ?></span>
                                        <span><?= format_price($sales) ?> (<?= (int) $cat['total_qty'] ?> sản phẩm)</span>
                                    </div>
                                    <div style="height: 10px; background: #e9eff2; border-radius: 99px; overflow: hidden;">
                                        <div style="width: <?= $percent ?>%; height: 100%; background: #d4880a; border-radius: 99px;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <!-- Top Sản Phẩm Bán Chạy -->
            <section class="admin-panel" style="margin-top: 2rem;">
                <div class="admin-panel-head">
                    <div>
                        <h2>Sản phẩm bán chạy nhất</h2>
                        <p>Xếp hạng sản phẩm có số lượng bán ra cao nhất trong kỳ.</p>
                    </div>
                </div>

                <div style="margin-top: 1.5rem;" class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Thứ hạng</th>
                                <th>Tên sản phẩm</th>
                                <th>Số lượng đã bán</th>
                                <th>Tỷ lệ bán</th>
                                <th>Tổng doanh thu sản phẩm</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($products === []): ?>
                                <tr><td colspan="5"><div class="empty-mini-card">Chưa có sản phẩm bán chạy.</div></td></tr>
                            <?php else: ?>
                                <?php $rank = 1; foreach ($products as $p): ?>
                                    <?php
                                        $qty = (int) $p['total_qty'];
                                        $percent = round(($qty / $maxProductQty) * 100, 1);
                                    ?>
                                    <tr>
                                        <td><strong>#<?= $rank++ ?></strong></td>
                                        <td><strong><?= e($p['product_name']) ?></strong></td>
                                        <td><strong><?= $qty ?></strong></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem; width: 150px;">
                                                <div style="flex: 1; height: 8px; background: #e9eff2; border-radius: 99px; overflow: hidden;">
                                                    <div style="width: <?= $percent ?>%; height: 100%; background: #3b4fd4; border-radius: 99px;"></div>
                                                </div>
                                                <span style="font-size: 0.8rem; font-weight: 700; color: #718096;"><?= $percent ?>%</span>
                                            </div>
                                        </td>
                                        <td><strong><?= format_price($p['total_sales']) ?></strong></td>
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
</body>
</html>
