<?php

require_once __DIR__ . '/../../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

admin_only();

$db  = Database::connect();
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));

// ─── Mốc thời gian ───────────────────────────────────────────────────────────
$monthStart     = $now->format('Y-m-01 00:00:00');
$lastMonthStart = $now->modify('first day of last month')->format('Y-m-d 00:00:00');
$lastMonthEnd   = $now->modify('last day of last month')->format('Y-m-d 23:59:59');

// KPI tháng này & tháng trước (chỉ để render ban đầu, JS sẽ refresh qua API)
$kpiMonth = $db->prepare('
    SELECT
        COALESCE(SUM(CASE WHEN status="completed" THEN total_amount ELSE 0 END),0) AS revenue,
        COUNT(*) AS total_orders,
        SUM(CASE WHEN status="completed" THEN 1 ELSE 0 END) AS completed_orders
    FROM orders WHERE created_at >= :s
');
$kpiMonth->execute(['s' => $monthStart]);
$kpiM = $kpiMonth->fetch(PDO::FETCH_ASSOC) ?: ['revenue' => 0, 'total_orders' => 0, 'completed_orders' => 0];

$kpiLast = $db->prepare('
    SELECT COALESCE(SUM(CASE WHEN status="completed" THEN total_amount ELSE 0 END),0) AS revenue
    FROM orders WHERE created_at >= :s AND created_at <= :e
');
$kpiLast->execute(['s' => $lastMonthStart, 'e' => $lastMonthEnd]);
$kpiL = $kpiLast->fetch(PDO::FETCH_ASSOC) ?: ['revenue' => 0];

$growthPct = 0;
if ((float)$kpiL['revenue'] > 0) {
    $growthPct = round((((float)$kpiM['revenue'] - (float)$kpiL['revenue']) / (float)$kpiL['revenue']) * 100, 1);
}

$pageTitle       = 'Admin – Doanh thu & Thống kê';
$pageDescription = 'Phân tích doanh thu realtime, top nhân viên sale và biểu đồ so sánh tháng.';
$adminActive     = 'reports';

include BASE_PATH . '/app/views/partials/admin-head.php';
?>
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<style>
/* ═══════════════════════════════════════════════════════
   Revenue Dashboard – Custom Styles
   ═══════════════════════════════════════════════════════ */

/* Tabs */
.rev-tabs {
    display: flex;
    gap: .5rem;
    background: #f0f4f8;
    border-radius: 12px;
    padding: .35rem;
}
.rev-tab {
    flex: 1;
    padding: .5rem 1rem;
    border: none;
    background: transparent;
    border-radius: 8px;
    font-weight: 700;
    font-size: .83rem;
    color: #718096;
    cursor: pointer;
    transition: all .2s;
    white-space: nowrap;
}
.rev-tab.active {
    background: #fff;
    color: #1a2e4a;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}

/* KPI Grid */
.rev-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.25rem;
    margin: 1.5rem 0;
}
.rev-kpi {
    background: #fff;
    border-radius: 16px;
    padding: 1.4rem 1.6rem;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
    display: flex;
    align-items: center;
    gap: 1.1rem;
    transition: transform .2s, box-shadow .2s;
}
.rev-kpi:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.1); }
.rev-kpi-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.rev-kpi-icon.green  { background: #ecfdf5; color: #059669; }
.rev-kpi-icon.purple { background: #f5f3ff; color: #7c3aed; }
.rev-kpi-icon.blue   { background: #eff6ff; color: #2563eb; }
.rev-kpi-icon.amber  { background: #fffbeb; color: #d97706; }
.rev-kpi-icon.rose   { background: #fff1f2; color: #e11d48; }
.rev-kpi label { display: block; font-size: .78rem; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: .04em; }
.rev-kpi strong { display: block; font-size: 1.45rem; font-weight: 800; color: #1a2e4a; line-height: 1.2; margin: .15rem 0; }
.rev-kpi small  { font-size: .78rem; color: #6b7280; }
.rev-kpi .badge-up   { display: inline-block; background: #ecfdf5; color: #059669; border-radius: 99px; padding: .15rem .5rem; font-size: .75rem; font-weight: 700; }
.rev-kpi .badge-down { display: inline-block; background: #fff1f2; color: #e11d48; border-radius: 99px; padding: .15rem .5rem; font-size: .75rem; font-weight: 700; }

/* Chart wrapper */
.rev-chart-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem 1.75rem;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
}
.rev-chart-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
    gap: .75rem;
}
.rev-chart-head h2 { font-size: 1.05rem; font-weight: 800; color: #1a2e4a; margin: 0; }
.rev-chart-head p  { font-size: .82rem; color: #9ca3af; margin: 0; }
.chart-canvas-wrap { position: relative; height: 300px; }

/* Sales table */
.sale-rank { display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 50%; font-size: .8rem; font-weight: 800; }
.sale-rank.gold   { background: #fef3c7; color: #b45309; }
.sale-rank.silver { background: #f1f5f9; color: #64748b; }
.sale-rank.bronze { background: #fff7ed; color: #c2410c; }
.sale-rank.other  { background: #f8fafc; color: #94a3b8; }

/* Live indicator */
.live-dot {
    display: inline-block;
    width: 8px; height: 8px;
    background: #10b981;
    border-radius: 50%;
    animation: pulse-dot 1.5s infinite;
}
@keyframes pulse-dot {
    0%,100% { opacity: 1; transform: scale(1); }
    50%      { opacity: .5; transform: scale(.6); }
}
.live-badge {
    display: inline-flex; align-items: center; gap: .4rem;
    background: #ecfdf5; color: #059669;
    border-radius: 99px; padding: .25rem .75rem;
    font-size: .75rem; font-weight: 700;
}

/* Compare legend */
.compare-legend { display: flex; gap: 1.5rem; flex-wrap: wrap; }
.compare-legend span { display: flex; align-items: center; gap: .4rem; font-size: .82rem; font-weight: 600; color: #374151; }
.compare-legend span::before { content: ''; display: block; width: 12px; height: 12px; border-radius: 3px; }
.compare-legend .this-month::before { background: #3b82f6; }
.compare-legend .last-month::before { background: #d1d5db; }

/* Responsive */
@media (max-width: 900px) {
    .rev-grid-2 { grid-template-columns: 1fr !important; }
}
#rev-update-time { font-size: .78rem; color: #9ca3af; }
</style>

<div class="admin-shell">
    <?php include BASE_PATH . '/app/views/partials/admin-sidebar.php'; ?>

    <main class="admin-main">
        <?php include BASE_PATH . '/app/views/partials/admin-topbar.php'; ?>

        <section class="admin-dashboard">

            <!-- Header -->
            <div class="admin-hero-card" style="margin-bottom:1.5rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
                    <div>
                        <span class="eyebrow">PHÂN TÍCH KINH DOANH</span>
                        <h1>Doanh thu &amp; Thống kê</h1>
                        <p>Cập nhật thời gian thực – tự động làm mới mỗi 30 giây</p>
                    </div>
                    <div style="text-align:right;">
                        <div class="live-badge" style="margin-bottom:.5rem;">
                            <span class="live-dot"></span> LIVE
                        </div>
                        <div id="rev-update-time">Đang tải…</div>
                    </div>
                </div>
            </div>

            <!-- Tabs chọn chế độ biểu đồ -->
            <div style="background:#fff;border-radius:16px;padding:1.25rem 1.5rem;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:1.5rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
                    <div>
                        <h2 style="font-size:.95rem;font-weight:800;color:#1a2e4a;margin:0 0 .25rem;">Xem doanh thu theo</h2>
                        <p style="font-size:.8rem;color:#9ca3af;margin:0;">Chọn chu kỳ để hiển thị biểu đồ cột doanh thu</p>
                    </div>
                    <div class="rev-tabs">
                        <button class="rev-tab active" data-view="today">Hôm nay</button>
                        <button class="rev-tab" data-view="week">Tuần này</button>
                        <button class="rev-tab" data-view="month">Tháng này</button>
                        <button class="rev-tab" data-view="compare">So sánh tháng</button>
                    </div>
                </div>
            </div>

            <!-- KPI Cards (realtime) -->
            <div class="rev-kpi-grid" id="kpi-grid">
                <!-- Render ban đầu từ PHP, sau đó JS sẽ cập nhật -->
                <div class="rev-kpi">
                    <div class="rev-kpi-icon green"><i class="fi fi-rr-usd"></i></div>
                    <div>
                        <label>Doanh thu hôm nay</label>
                        <strong id="kpi-today">–</strong>
                        <small>Đơn hoàn tất trong ngày</small>
                    </div>
                </div>
                <div class="rev-kpi">
                    <div class="rev-kpi-icon blue"><i class="fi fi-rr-calendar-week"></i></div>
                    <div>
                        <label>Tuần này</label>
                        <strong id="kpi-week">–</strong>
                        <small id="kpi-week-orders">– đơn</small>
                    </div>
                </div>
                <div class="rev-kpi">
                    <div class="rev-kpi-icon purple"><i class="fi fi-rr-chart-histogram"></i></div>
                    <div>
                        <label>Tháng <?= $now->format('m/Y') ?></label>
                        <strong id="kpi-month"><?= format_price($kpiM['revenue']) ?></strong>
                        <small><?= (int)$kpiM['completed_orders'] ?> đơn hoàn tất</small>
                    </div>
                </div>
                <div class="rev-kpi">
                    <div class="rev-kpi-icon amber"><i class="fi fi-rr-chart-line-up"></i></div>
                    <div>
                        <label>So với tháng trước</label>
                        <strong id="kpi-growth"><?= $growthPct >= 0 ? '+' . $growthPct : $growthPct ?>%</strong>
                        <?php if ($growthPct >= 0): ?>
                            <span class="badge-up" id="kpi-vs-badge">▲ tăng</span>
                        <?php else: ?>
                            <span class="badge-down" id="kpi-vs-badge">▼ giảm</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="rev-kpi">
                    <div class="rev-kpi-icon rose"><i class="fi fi-rr-receipt"></i></div>
                    <div>
                        <label>Tháng trước</label>
                        <strong id="kpi-last-month"><?= format_price($kpiL['revenue']) ?></strong>
                        <small><?= $now->modify('first day of last month')->format('m/Y') ?></small>
                    </div>
                </div>
            </div>

            <!-- Biểu đồ chính -->
            <div class="rev-chart-card" style="margin-bottom:1.5rem;">
                <div class="rev-chart-head">
                    <div>
                        <h2 id="chart-title">Doanh thu hôm nay (theo giờ)</h2>
                        <p id="chart-subtitle">Chỉ tính các đơn hàng có trạng thái <strong>Hoàn tất</strong></p>
                    </div>
                    <div class="compare-legend" id="compare-legend" style="display:none;">
                        <span class="this-month">Tháng này</span>
                        <span class="last-month">Tháng trước</span>
                    </div>
                </div>
                <div class="chart-canvas-wrap">
                    <canvas id="mainChart"></canvas>
                </div>
            </div>

            <!-- Grid 2 cột: Top Sale + Top Sản phẩm -->
            <div class="rev-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">

                <!-- Top nhân viên sale -->
                <div class="rev-chart-card">
                    <div class="rev-chart-head">
                        <div>
                            <h2>🏆 Top Nhân viên Sale</h2>
                            <p>Xếp hạng theo doanh thu tháng này</p>
                        </div>
                    </div>
                    <div id="top-sales-container">
                        <div style="text-align:center;padding:2rem;color:#9ca3af;">Đang tải…</div>
                    </div>
                </div>

                <!-- Top sản phẩm -->
                <div class="rev-chart-card">
                    <div class="rev-chart-head">
                        <div>
                            <h2>🔥 Sản phẩm bán chạy</h2>
                            <p>Tháng <?= $now->format('m/Y') ?> – số lượng bán ra</p>
                        </div>
                    </div>
                    <div class="chart-canvas-wrap" style="height:280px;">
                        <canvas id="productChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Cơ cấu danh mục -->
            <div class="rev-chart-card" style="margin-bottom:1.5rem;">
                <div class="rev-chart-head">
                    <div>
                        <h2>📊 Cơ cấu danh mục</h2>
                        <p>Tỷ trọng doanh thu theo danh mục sản phẩm – tháng này</p>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:300px 1fr;gap:2rem;align-items:center;" id="cat-grid">
                    <div class="chart-canvas-wrap" style="height:260px;">
                        <canvas id="catChart"></canvas>
                    </div>
                    <div id="cat-list"></div>
                </div>
            </div>

        </section>
    </main>
</div>

<script>
/* ═══════════════════════════════════════════════════════
   Revenue Dashboard JavaScript
   ═══════════════════════════════════════════════════════ */

const API_URL = 'revenue-api.php';
const REFRESH_INTERVAL = 30000; // 30 giây

let mainChart = null;
let productChart = null;
let catChart = null;
let currentView = 'today';
let lastData = null;

// Palette
const COLORS = {
    primary   : '#3b82f6',
    secondary : '#d1d5db',
    success   : '#10b981',
    warning   : '#f59e0b',
    danger    : '#ef4444',
    purple    : '#8b5cf6',
    gradient  : (ctx, color) => {
        const g = ctx.createLinearGradient(0, 0, 0, ctx.canvas.offsetHeight);
        g.addColorStop(0, color + 'cc');
        g.addColorStop(1, color + '11');
        return g;
    }
};

const CAT_PALETTE = ['#3b82f6','#8b5cf6','#10b981','#f59e0b','#ef4444','#06b6d4','#ec4899','#6366f1'];

// ── Formatters ─────────────────────────────────────────
function fmtVND(n) {
    n = parseFloat(n) || 0;
    if (n >= 1e9) return (n/1e9).toFixed(1) + ' tỷ';
    if (n >= 1e6) return (n/1e6).toFixed(1) + ' tr';
    return n.toLocaleString('vi-VN') + ' ₫';
}
function fmtFull(n) {
    return parseFloat(n || 0).toLocaleString('vi-VN') + ' ₫';
}

// ── Fetch data ─────────────────────────────────────────
async function fetchData() {
    const res  = await fetch(API_URL + '?_=' + Date.now());
    const data = await res.json();
    return data;
}

// ── Render KPI Cards ───────────────────────────────────
function renderKPI(data) {
    const k = data.kpi;

    // Today
    document.getElementById('kpi-today').textContent = fmtVND(k.today.revenue);

    // Week
    document.getElementById('kpi-week').textContent = fmtVND(k.week.revenue);
    document.getElementById('kpi-week-orders').textContent = k.week.completed_orders + ' đơn hoàn tất';

    // Month
    document.getElementById('kpi-month').textContent = fmtVND(k.month.revenue);

    // Growth
    const gPct = parseFloat(k.growthPct);
    document.getElementById('kpi-growth').textContent = (gPct >= 0 ? '+' : '') + gPct + '%';
    const badge = document.getElementById('kpi-vs-badge');
    if (gPct >= 0) {
        badge.className = 'badge-up';
        badge.textContent = '▲ tăng';
    } else {
        badge.className = 'badge-down';
        badge.textContent = '▼ giảm';
    }

    // Last month
    document.getElementById('kpi-last-month').textContent = fmtVND(k.lastMonth.revenue);

    document.getElementById('rev-update-time').textContent = 'Cập nhật: ' + data.generated_at;
}

// ── Main Bar Chart ─────────────────────────────────────
function buildMainChart(labels, values, color, label2 = null, values2 = null) {
    const canvas = document.getElementById('mainChart');
    const ctx = canvas.getContext('2d');

    if (mainChart) mainChart.destroy();

    const datasets = [{
        label: 'Doanh thu',
        data: values,
        backgroundColor: values.map(v => v > 0 ? color + 'cc' : color + '22'),
        borderColor: color,
        borderWidth: 2,
        borderRadius: 6,
        borderSkipped: false,
    }];

    if (label2 && values2) {
        datasets.push({
            label: label2,
            data: values2,
            backgroundColor: COLORS.secondary + 'aa',
            borderColor: COLORS.secondary,
            borderWidth: 2,
            borderRadius: 6,
            borderSkipped: false,
        });
    }

    mainChart = new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: !!label2,
                    position: 'top',
                    labels: { font: { weight: '700', size: 12 }, usePointStyle: true }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + fmtFull(ctx.raw)
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11, weight: '600' }, color: '#6b7280' }
                },
                y: {
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        font: { size: 11 }, color: '#6b7280',
                        callback: v => fmtVND(v)
                    }
                }
            },
            animation: { duration: 500 }
        }
    });
}

// ── Switch view ────────────────────────────────────────
function switchView(view, data) {
    currentView = view;
    const c = data.charts;
    const compareLegend = document.getElementById('compare-legend');
    const chartTitle    = document.getElementById('chart-title');
    const chartSub      = document.getElementById('chart-subtitle');

    document.querySelectorAll('.rev-tab').forEach(b => b.classList.toggle('active', b.dataset.view === view));

    compareLegend.style.display = 'none';

    switch (view) {
        case 'today':
            chartTitle.textContent = 'Doanh thu hôm nay (theo giờ)';
            chartSub.innerHTML = 'Chỉ tính các đơn hàng có trạng thái <strong>Hoàn tất</strong>';
            buildMainChart(c.today.labels, c.today.values, COLORS.primary);
            break;
        case 'week':
            chartTitle.textContent = 'Doanh thu tuần này (theo ngày)';
            chartSub.innerHTML = 'Chỉ tính các đơn hàng có trạng thái <strong>Hoàn tất</strong>';
            buildMainChart(c.week.labels, c.week.values, COLORS.success);
            break;
        case 'month':
            chartTitle.textContent = 'Doanh thu tháng này (theo ngày)';
            chartSub.innerHTML = 'Chỉ tính các đơn hàng có trạng thái <strong>Hoàn tất</strong>';
            buildMainChart(c.month.labels, c.month.values, COLORS.purple);
            break;
        case 'compare':
            chartTitle.textContent = 'So sánh doanh thu: tháng này vs tháng trước';
            chartSub.innerHTML = 'Phân tích theo từng tuần trong tháng';
            compareLegend.style.display = 'flex';
            buildMainChart(
                c.compare.labels,
                c.compare.thisMonth,
                COLORS.primary,
                'Tháng trước',
                c.compare.lastMonth
            );
            break;
    }
}

// ── Top Sales Table ────────────────────────────────────
function renderTopSales(topSales) {
    const el = document.getElementById('top-sales-container');
    if (!topSales || topSales.length === 0) {
        el.innerHTML = `<div style="text-align:center;padding:2rem;color:#9ca3af;">
            <div style="font-size:2rem;margin-bottom:.5rem;">👤</div>
            <p>Chưa có dữ liệu nhân viên sale trong tháng này</p>
        </div>`;
        return;
    }

    const maxRev = Math.max(...topSales.map(s => parseFloat(s.total_revenue) || 0)) || 1;

    const rows = topSales.map((s, i) => {
        const rev = parseFloat(s.total_revenue) || 0;
        const pct = Math.round((rev / maxRev) * 100);
        const rankClass = i === 0 ? 'gold' : i === 1 ? 'silver' : i === 2 ? 'bronze' : 'other';
        const rankIcon  = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : `#${i+1}`;
        return `
        <div style="display:flex;align-items:center;gap:.9rem;padding:.75rem 0;border-bottom:1px solid #f1f5f9;">
            <div class="sale-rank ${rankClass}">${rankIcon}</div>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:800;font-size:.88rem;color:#1a2e4a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(s.full_name)}</div>
                <div style="display:flex;align-items:center;gap:.5rem;margin-top:.3rem;">
                    <div style="flex:1;height:6px;background:#e5e7eb;border-radius:99px;overflow:hidden;">
                        <div style="width:${pct}%;height:100%;background:${i===0?'#f59e0b':i===1?'#94a3b8':i===2?'#c2410c':'#3b82f6'};border-radius:99px;"></div>
                    </div>
                    <span style="font-size:.75rem;color:#6b7280;font-weight:700;white-space:nowrap;">${s.total_orders} đơn</span>
                </div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
                <div style="font-weight:800;font-size:.9rem;color:#1a2e4a;">${fmtVND(rev)}</div>
                <div style="font-size:.72rem;color:#9ca3af;">hoàn tất: ${fmtVND(s.completed_revenue)}</div>
            </div>
        </div>`;
    }).join('');

    el.innerHTML = `<div>${rows}</div>`;
}

// ── Product Horizontal Bar Chart ──────────────────────
function renderProductChart(topProducts) {
    const canvas = document.getElementById('productChart');
    const ctx = canvas.getContext('2d');

    if (productChart) productChart.destroy();

    if (!topProducts || topProducts.length === 0) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.font = '14px sans-serif';
        ctx.fillStyle = '#9ca3af';
        ctx.textAlign = 'center';
        ctx.fillText('Chưa có dữ liệu', canvas.width / 2, canvas.height / 2);
        return;
    }

    const labels = topProducts.map(p => p.product_name.length > 25 ? p.product_name.substring(0, 23) + '…' : p.product_name);
    const values = topProducts.map(p => parseInt(p.total_qty) || 0);

    productChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Số lượng bán',
                data: values,
                backgroundColor: CAT_PALETTE.slice(0, values.length),
                borderRadius: 5,
                borderSkipped: false,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + ctx.raw + ' sản phẩm'
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: '#f1f5f9' },
                    ticks: { font: { size: 11 }, color: '#6b7280' }
                },
                y: {
                    grid: { display: false },
                    ticks: { font: { size: 11, weight: '600' }, color: '#374151' }
                }
            },
            animation: { duration: 500 }
        }
    });
}

// ── Category Doughnut Chart ────────────────────────────
function renderCatChart(categories) {
    const canvas = document.getElementById('catChart');
    const ctx = canvas.getContext('2d');

    if (catChart) catChart.destroy();

    const el = document.getElementById('cat-list');

    if (!categories || categories.length === 0) {
        el.innerHTML = '<div style="color:#9ca3af;text-align:center;padding:2rem;">Chưa có dữ liệu danh mục</div>';
        return;
    }

    const labels = categories.map(c => c.category_name);
    const values = categories.map(c => parseFloat(c.total_sales) || 0);
    const total  = values.reduce((a, b) => a + b, 0) || 1;

    catChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: CAT_PALETTE,
                borderWidth: 3,
                borderColor: '#fff',
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + fmtFull(ctx.raw) + ' (' + Math.round((ctx.raw/total)*100) + '%)'
                    }
                }
            }
        }
    });

    // Render list
    const listHtml = categories.map((c, i) => {
        const pct = Math.round((parseFloat(c.total_sales)||0) / total * 100);
        return `<div style="display:flex;align-items:center;gap:.75rem;padding:.45rem 0;">
            <div style="width:10px;height:10px;border-radius:3px;background:${CAT_PALETTE[i]||'#ccc'};flex-shrink:0;"></div>
            <span style="flex:1;font-size:.85rem;font-weight:700;color:#374151;">${escHtml(c.category_name)}</span>
            <span style="font-size:.8rem;color:#6b7280;">${c.total_qty} SP</span>
            <span style="font-size:.82rem;font-weight:800;color:#1a2e4a;">${pct}%</span>
        </div>`;
    }).join('');
    el.innerHTML = listHtml;
}

// ── Escape HTML ────────────────────────────────────────
function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Main update cycle ──────────────────────────────────
async function update() {
    try {
        const data = await fetchData();
        lastData = data;
        renderKPI(data);
        switchView(currentView, data);
        renderTopSales(data.topSales);
        renderProductChart(data.topProducts);
        renderCatChart(data.categories);
    } catch(e) {
        console.error('Revenue API error:', e);
        document.getElementById('rev-update-time').textContent = 'Lỗi kết nối – thử lại sau…';
    }
}

// ── Tab clicks ─────────────────────────────────────────
document.querySelectorAll('.rev-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        if (lastData) switchView(btn.dataset.view, lastData);
    });
});

// ── Init ────────────────────────────────────────────────
update();
setInterval(update, REFRESH_INTERVAL);
</script>

</body>
</html>
