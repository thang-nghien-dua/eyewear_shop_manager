<?php
/**
 * Revenue API - Trả về dữ liệu doanh thu realtime dưới dạng JSON
 * Dùng cho trang báo cáo doanh thu admin
 */

require_once __DIR__ . '/../../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

admin_only();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$db = Database::connect();
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));

// ─── Mốc thời gian ───────────────────────────────────────────────────────────
$todayStart       = $now->format('Y-m-d 00:00:00');
$weekStart        = (clone $now)->modify('Monday this week')->format('Y-m-d 00:00:00');
$monthStart       = $now->format('Y-m-01 00:00:00');
$lastMonthStart   = $now->modify('first day of last month')->format('Y-m-d 00:00:00');
$lastMonthEnd     = $now->modify('last day of last month')->format('Y-m-d 23:59:59');

// ─── Helper: query KPI một khoảng thời gian ──────────────────────────────────
function fetchKpi(PDO $db, string $start, string $end = ''): array {
    $sql = 'SELECT
        COALESCE(SUM(CASE WHEN status = "completed" THEN total_amount ELSE 0 END), 0) AS revenue,
        COUNT(*) AS total_orders,
        SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) AS completed_orders
    FROM orders
    WHERE created_at >= :start';
    $params = ['start' => $start];
    if ($end !== '') {
        $sql .= ' AND created_at <= :end';
        $params['end'] = $end;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['revenue' => 0, 'total_orders' => 0, 'completed_orders' => 0];
}

// 1. KPI tổng hợp: hôm nay / tuần / tháng / tháng trước
$kpiToday     = fetchKpi($db, $todayStart);
$kpiWeek      = fetchKpi($db, $weekStart);
$kpiMonth     = fetchKpi($db, $monthStart);
$kpiLastMonth = fetchKpi($db, $lastMonthStart, $lastMonthEnd);

// ─── 2. Biểu đồ cột: doanh thu từng giờ HÔM NAY (0-23h) ─────────────────────
$hourlyStmt = $db->prepare('
    SELECT HOUR(created_at) AS h, COALESCE(SUM(total_amount), 0) AS rev
    FROM orders
    WHERE status = "completed" AND created_at >= :start
    GROUP BY h
    ORDER BY h
');
$hourlyStmt->execute(['start' => $todayStart]);
$hourlyRows = $hourlyStmt->fetchAll(PDO::FETCH_ASSOC);
$hourlyMap = [];
foreach ($hourlyRows as $r) $hourlyMap[(int)$r['h']] = (float)$r['rev'];
$hourlyLabels = [];
$hourlyValues = [];
for ($h = 0; $h <= 23; $h++) {
    $hourlyLabels[] = sprintf('%02d:00', $h);
    $hourlyValues[] = $hourlyMap[$h] ?? 0;
}

// ─── 3. Biểu đồ cột: doanh thu 7 ngày TUẦN NÀY ──────────────────────────────
$weeklyStmt = $db->prepare('
    SELECT DATE(created_at) AS d, COALESCE(SUM(total_amount), 0) AS rev
    FROM orders
    WHERE status = "completed" AND created_at >= :start
    GROUP BY d
    ORDER BY d
');
$weeklyStmt->execute(['start' => $weekStart]);
$weeklyRows = $weeklyStmt->fetchAll(PDO::FETCH_ASSOC);
$weeklyMap = [];
foreach ($weeklyRows as $r) $weeklyMap[$r['d']] = (float)$r['rev'];

$weeklyLabels = [];
$weeklyValues = [];
$dayNamesVi = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
for ($i = 0; $i < 7; $i++) {
    $d = (new DateTimeImmutable($weekStart))->modify("+{$i} days");
    $dateStr = $d->format('Y-m-d');
    $dow = (int)$d->format('w'); // 0=CN,1=T2..6=T7
    $weeklyLabels[] = $dayNamesVi[$dow] . ' ' . $d->format('d/m');
    $weeklyValues[] = $weeklyMap[$dateStr] ?? 0;
}

// ─── 4. Biểu đồ cột: doanh thu từng ngày THÁNG NÀY ─────────────────────────
$daysInMonth = (int)$now->format('t');
$monthlyDayStmt = $db->prepare('
    SELECT DAY(created_at) AS d, COALESCE(SUM(total_amount), 0) AS rev
    FROM orders
    WHERE status = "completed" AND created_at >= :start
    GROUP BY d
    ORDER BY d
');
$monthlyDayStmt->execute(['start' => $monthStart]);
$monthlyDayRows = $monthlyDayStmt->fetchAll(PDO::FETCH_ASSOC);
$monthlyDayMap = [];
foreach ($monthlyDayRows as $r) $monthlyDayMap[(int)$r['d']] = (float)$r['rev'];

$monthlyDayLabels = [];
$monthlyDayValues = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $monthlyDayLabels[] = 'Ngày ' . $d;
    $monthlyDayValues[] = $monthlyDayMap[$d] ?? 0;
}

// ─── 5. Biểu đồ so sánh tháng này vs tháng trước (theo tuần) ────────────────
// Tháng trước - chia theo tuần (1-4)
$lastMonthDaysInMonth = (int)(new DateTimeImmutable($lastMonthStart))->format('t');
function revenueByWeekInMonth(PDO $db, string $monthStart, int $daysInMonth): array {
    $stmt = $db->prepare('
        SELECT DAY(created_at) AS d, COALESCE(SUM(total_amount), 0) AS rev
        FROM orders
        WHERE status = "completed"
          AND created_at >= :month_start
          AND DAY(created_at) <= :days
        GROUP BY d
    ');
    $stmt->execute(['month_start' => $monthStart, 'days' => $daysInMonth]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $byWeek = [0, 0, 0, 0];
    foreach ($rows as $r) {
        $day = (int)$r['d'];
        $wk = min(3, intdiv($day - 1, 7));
        $byWeek[$wk] += (float)$r['rev'];
    }
    return $byWeek;
}

$thisMonthWeekly = revenueByWeekInMonth($db, $monthStart, $daysInMonth);
$lastMonthWeekly = revenueByWeekInMonth($db, $lastMonthStart, $lastMonthDaysInMonth);

// ─── 6. Top nhân viên sale (handled_by) ──────────────────────────────────────
$saleStmt = $db->prepare('
    SELECT u.full_name, u.email,
           COUNT(o.id) AS total_orders,
           COALESCE(SUM(o.total_amount), 0) AS total_revenue,
           COALESCE(SUM(CASE WHEN o.status = "completed" THEN o.total_amount ELSE 0 END), 0) AS completed_revenue
    FROM orders o
    JOIN users u ON u.id = o.handled_by
    WHERE o.created_at >= :start AND o.handled_by IS NOT NULL
    GROUP BY o.handled_by, u.full_name, u.email
    ORDER BY total_revenue DESC
    LIMIT 10
');
$saleStmt->execute(['start' => $monthStart]);
$topSales = $saleStmt->fetchAll(PDO::FETCH_ASSOC);

// ─── 7. Top sản phẩm bán chạy tháng này ──────────────────────────────────────
$topProductStmt = $db->prepare('
    SELECT oi.product_name,
           SUM(oi.quantity) AS total_qty,
           SUM(oi.line_total) AS total_sales
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE o.status = "completed" AND o.created_at >= :start
    GROUP BY oi.product_name
    ORDER BY total_qty DESC
    LIMIT 8
');
$topProductStmt->execute(['start' => $monthStart]);
$topProducts = $topProductStmt->fetchAll(PDO::FETCH_ASSOC);

// ─── 8. Cơ cấu danh mục tháng này ───────────────────────────────────────────
$catStmt = $db->prepare('
    SELECT c.name AS category_name,
           SUM(oi.quantity) AS total_qty,
           SUM(oi.line_total) AS total_sales
    FROM order_items oi
    JOIN product_variants pv ON pv.id = oi.product_variant_id
    JOIN products p ON p.id = pv.product_id
    JOIN categories c ON c.id = p.category_id
    JOIN orders o ON o.id = oi.order_id
    WHERE o.status = "completed" AND o.created_at >= :start
    GROUP BY c.name
    ORDER BY total_sales DESC
');
$catStmt->execute(['start' => $monthStart]);
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// ─── 9. So sánh doanh thu: tháng này vs tháng trước ─────────────────────────
$growthPct = 0;
if ((float)$kpiLastMonth['revenue'] > 0) {
    $growthPct = round((((float)$kpiMonth['revenue'] - (float)$kpiLastMonth['revenue']) / (float)$kpiLastMonth['revenue']) * 100, 1);
}

echo json_encode([
    'generated_at' => $now->format('d/m/Y H:i:s'),
    'kpi' => [
        'today'     => $kpiToday,
        'week'      => $kpiWeek,
        'month'     => $kpiMonth,
        'lastMonth' => $kpiLastMonth,
        'growthPct' => $growthPct,
    ],
    'charts' => [
        'today'    => ['labels' => $hourlyLabels,   'values' => $hourlyValues],
        'week'     => ['labels' => $weeklyLabels,    'values' => $weeklyValues],
        'month'    => ['labels' => $monthlyDayLabels,'values' => $monthlyDayValues],
        'compare'  => [
            'labels'    => ['Tuần 1', 'Tuần 2', 'Tuần 3', 'Tuần 4'],
            'thisMonth' => $thisMonthWeekly,
            'lastMonth' => $lastMonthWeekly,
        ],
    ],
    'topSales'    => $topSales,
    'topProducts' => $topProducts,
    'categories'  => $categories,
], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
