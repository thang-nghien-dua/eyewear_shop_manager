<?php

require_once __DIR__ . '/../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

auth_only();
require_login();
$user = auth_user();

$db = Database::connect();

// Lấy đơn hàng
$whereConditions = ['user_id = :user_id'];
$params = ['user_id' => $user['id']];

if (!empty($user['email'])) {
    $whereConditions[] = 'customer_email = :email';
    $params['email'] = $user['email'];
}
if (!empty($user['phone'])) {
    $whereConditions[] = 'customer_phone = :phone';
    $params['phone'] = $user['phone'];
}
$whereSql = implode(' OR ', $whereConditions);

$orderSummaryStmt = $db->prepare(
    "SELECT COUNT(*) AS total_orders,
            COALESCE(SUM(total_amount), 0) AS total_spent
     FROM orders
     WHERE $whereSql"
);
$orderSummaryStmt->execute($params);
$orderSummary = $orderSummaryStmt->fetch() ?: ['total_orders' => 0, 'total_spent' => 0];

$recentOrdersStmt = $db->prepare(
    "SELECT id, order_code, order_type, status, total_amount, created_at
     FROM orders
     WHERE $whereSql
     ORDER BY id DESC
     LIMIT 5"
);
$recentOrdersStmt->execute($params);
$recentOrders = $recentOrdersStmt->fetchAll();

// Lấy ví đơn kính
$prescriptions = [];
try {
    $rxStmt = $db->prepare('SELECT * FROM customer_prescriptions WHERE user_id = :uid ORDER BY is_default DESC, id DESC');
    $rxStmt->execute(['uid' => $user['id']]);
    $prescriptions = $rxStmt->fetchAll();
} catch (\Throwable $e) { /* Bảng chưa tồn tại */ }

// Lấy dữ liệu đánh giá (sản phẩm từ đơn completed)
$pendingReviews  = []; // Chưa đánh giá
$doneReviews     = []; // Đã đánh giá
try {
    // Sản phẩm từ đơn completed chưa đánh giá
    $pendingStmt = $db->prepare("
        SELECT DISTINCT
            o.id AS order_id, o.order_code,
            p.id AS product_id, p.name AS product_name, p.thumbnail,
            pv.color, pv.size_label, o.created_at AS order_date
        FROM orders o
        INNER JOIN order_items oi ON oi.order_id = o.id
        INNER JOIN product_variants pv ON pv.id = oi.product_variant_id
        INNER JOIN products p ON p.id = pv.product_id
        WHERE ($whereSql) AND o.status = 'completed'
          AND NOT EXISTS (
              SELECT 1 FROM product_reviews pr
              WHERE pr.user_id = :uid2 AND pr.product_id = p.id
          )
        ORDER BY o.created_at DESC
        LIMIT 30
    ");
    $pendingStmt->execute(array_merge($params, ['uid2' => $user['id']]));
    $pendingReviews = $pendingStmt->fetchAll();

    // Đã đánh giá
    $doneStmt = $db->prepare("
        SELECT pr.id AS review_id, pr.rating, pr.body, pr.created_at AS review_date,
               p.id AS product_id, p.name AS product_name, p.thumbnail, p.slug AS product_slug,
               o.order_code
        FROM product_reviews pr
        INNER JOIN products p ON p.id = pr.product_id
        LEFT JOIN orders o ON o.id = pr.order_id
        WHERE pr.user_id = :uid AND pr.status = 'approved'
        ORDER BY pr.created_at DESC
    ");
    $doneStmt->execute(['uid' => $user['id']]);
    $doneReviews = $doneStmt->fetchAll();
} catch (\Throwable $e) { /* Bảng chưa tồn tại */ }

// Lấy dữ liệu hoàn hàng
$pendingReturns = []; // Đơn completed chưa gửi yêu cầu
$doneReturns    = []; // Đã gửi yêu cầu
try {
    // Đơn completed chưa có return_request
    $pendingRetStmt = $db->prepare("
        SELECT o.id, o.order_code, o.total_amount, o.created_at
        FROM orders o
        WHERE ($whereSql) AND o.status = 'completed'
          AND NOT EXISTS (
              SELECT 1 FROM return_requests rr WHERE rr.order_id = o.id
          )
        ORDER BY o.created_at DESC
        LIMIT 20
    ");
    $pendingRetStmt->execute($params);
    $pendingReturns = $pendingRetStmt->fetchAll();

    // Đã gửi yêu cầu
    $doneRetConditions = ['o.user_id = :user_id'];
    if (!empty($user['email'])) {
        $doneRetConditions[] = 'o.customer_email = :email';
    }
    if (!empty($user['phone'])) {
        $doneRetConditions[] = 'o.customer_phone = :phone';
    }
    $doneRetWhereSql = implode(' OR ', $doneRetConditions);

    $doneRetStmt = $db->prepare("
        SELECT o.id AS order_id, o.order_code, o.total_amount, o.created_at AS order_date,
               rr.id AS rr_id, rr.request_type, rr.status AS rr_status, rr.reason, rr.created_at AS rr_date,
               rr.resolution_note
        FROM orders o
        INNER JOIN return_requests rr ON rr.order_id = o.id
        WHERE ($doneRetWhereSql)
        ORDER BY rr.created_at DESC
    ");
    $doneRetStmt->execute($params);
    $doneReturns = $doneRetStmt->fetchAll();
} catch (\Throwable $e) { /* Bảng chưa tồn tại */ }

$pageTitle = 'Tài khoản của tôi';
require_once BASE_PATH . '/app/views/partials/head.php';
require_once BASE_PATH . '/app/views/partials/header.php';
?>
<style>
/* ── Base Profile Styles ── */
.profile-wrapper {
    background: #f8f9fa;
    padding: 3rem 0;
    min-height: calc(100vh - 80px);
}
.profile-header {
    background: linear-gradient(135deg, #1a2e4a 0%, #2a4365 100%);
    border-radius: 16px;
    padding: 2.5rem;
    color: #fff;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 10px 30px rgba(26,46,74,0.15);
    position: relative;
    overflow: hidden;
}
.profile-header::after {
    content: '';
    position: absolute;
    top: 0; right: 0; bottom: 0; left: 0;
    background: url('data:image/svg+xml;utf8,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><circle cx="100" cy="0" r="40" fill="rgba(255,255,255,0.05)"/></svg>') no-repeat top right;
    background-size: cover;
    pointer-events: none;
}
.profile-header-info h1 { margin: 0 0 0.5rem 0; font-size: 2rem; font-weight: 800; }
.profile-header-info p { margin: 0; color: #a0aec0; font-size: 1.1rem; }
.profile-header-actions .btn-logout {
    background: rgba(255,255,255,0.1); color: #fff;
    padding: 0.75rem 1.5rem; border-radius: 99px; text-decoration: none;
    font-weight: 600; transition: all 0.3s ease;
    display: flex; align-items: center; gap: 0.5rem;
    border: 1px solid rgba(255,255,255,0.2);
}
.profile-header-actions .btn-logout:hover { background: rgba(255,255,255,0.2); }

.profile-content {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 2rem;
}
@media (max-width: 900px) {
    .profile-content { grid-template-columns: 1fr; }
    .profile-header { flex-direction: column; align-items: flex-start; gap: 1.5rem; }
}
.premium-card {
    background: #fff; border-radius: 16px; padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    border: 1px solid rgba(0,0,0,0.02);
}
.premium-card h2 {
    font-size: 1.25rem; font-weight: 700; margin-top: 0; margin-bottom: 1.5rem;
    color: #1a2e4a; border-bottom: 2px solid #edf2f7; padding-bottom: 0.75rem;
    display: flex; justify-content: space-between; align-items: center;
}
.info-group { margin-bottom: 1.25rem; }
.info-group label { display: block; font-size: 0.85rem; color: #718096; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 0.25rem; }
.info-group .info-value { font-size: 1.1rem; color: #2d3748; font-weight: 500; }
.stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
.stat-box { background: #f7fafc; border-radius: 12px; padding: 1.5rem; text-align: center; border: 1px solid #edf2f7; }
.stat-box .stat-value { display: block; font-size: 1.75rem; font-weight: 800; color: #d4880a; margin-bottom: 0.25rem; }
.stat-box .stat-label { font-size: 0.9rem; color: #4a5568; font-weight: 500; }

/* ── Profile Tabs ── */
.profile-tabs { display: flex; gap: 0; margin-bottom: 1.5rem; background: #fff; border-radius: 12px; padding: 6px; border: 1px solid #e8ecef; overflow: hidden; flex-wrap: wrap; }
.profile-tab-btn {
    flex: 1; min-width: 120px; padding: .65rem .8rem; border: none; background: transparent;
    font-weight: 700; font-size: .82rem; color: #718096; cursor: pointer;
    border-radius: 8px; transition: all 0.2s; display: flex; align-items: center;
    justify-content: center; gap: .4rem;
}
.profile-tab-btn.active { background: #1a2e4a; color: #fff; }
.profile-tab-btn:hover:not(.active) { background: #f0f4f8; color: #1a2e4a; }
.profile-tab-content { display: none; }
.profile-tab-content.active { display: block; }

/* Sub-tabs for reviews/returns */
.sub-tabs { display:flex; border-bottom:2px solid #e8ecef; margin-bottom:1.25rem; gap:0; }
.sub-tab-btn {
    padding:.6rem 1.25rem; border:none; background:transparent;
    font-size:.88rem; font-weight:700; color:#9aa3a6;
    cursor:pointer; border-bottom:2.5px solid transparent;
    margin-bottom:-2px; transition:.15s;
}
.sub-tab-btn.active { color:#d4880a; border-bottom-color:#d4880a; }
.sub-tab-btn:hover:not(.active) { color:#1a2e4a; }
.sub-tab-pane { display:none; }
.sub-tab-pane.active { display:block; }

/* Review card */
.rv-item { display:flex; gap:1rem; padding:1rem 0; border-bottom:1px solid #f0f3f5; align-items:flex-start; }
.rv-item:last-child { border-bottom:none; }
.rv-thumb { width:56px; height:56px; object-fit:contain; border-radius:8px; background:#f4f7f9; flex:0 0 56px; padding:.25rem; }
.rv-info { flex:1; }
.rv-name { font-weight:700; font-size:.9rem; color:#1a2e4a; margin-bottom:.2rem; }
.rv-meta { font-size:.78rem; color:#9aa3a6; margin-bottom:.4rem; }
.rv-stars { color:#f59e0b; font-size:.9rem; letter-spacing:1px; }
.rv-body { font-size:.85rem; color:#4a5568; line-height:1.55; margin-top:.35rem; }
.btn-write-rev {
    display:inline-flex; align-items:center; gap:.35rem;
    padding:.45rem 1rem; background:#d4880a; color:#fff;
    border-radius:8px; font-size:.82rem; font-weight:800;
    text-decoration:none; transition:.2s; white-space:nowrap;
    flex:0 0 auto;
}
.btn-write-rev:hover { background:#b8720a; }

/* Return card */
.ret-item { padding:.9rem 1rem; background:#f8fafc; border-radius:10px; border:1px solid #e4ebee; margin-bottom:.75rem; display:flex; justify-content:space-between; align-items:center; gap:1rem; }
.ret-item:last-child { margin-bottom:0; }
.ret-info { flex:1; }
.ret-code { font-weight:800; font-size:.9rem; color:#1a2e4a; }
.ret-date { font-size:.78rem; color:#9aa3a6; }
.ret-amount { font-size:.85rem; font-weight:700; color:#d4880a; }
.ret-status-badge { font-size:.75rem; font-weight:800; padding:.22rem .65rem; border-radius:99px; white-space:nowrap; }
.ret-pending  { background:#fff3cd; color:#856404; }
.ret-approved { background:#d1fae5; color:#065f46; }
.ret-rejected { background:#fee2e2; color:#991b1b; }
.ret-resolved { background:#dbeafe; color:#1e40af; }
.btn-send-ret {
    display:inline-flex; align-items:center; gap:.35rem;
    padding:.45rem 1rem; background:#1a2e4a; color:#fff;
    border-radius:8px; font-size:.82rem; font-weight:800;
    text-decoration:none; transition:.2s; white-space:nowrap;
    flex:0 0 auto;
}
.btn-send-ret:hover { background:#2d4563; }
.empty-state-rv { text-align:center; padding:2.5rem 1rem; color:#a0aec0; }
.empty-state-rv svg { margin-bottom:.75rem; opacity:.4; }

/* ── Order Table ── */
.order-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.order-table th { background: #f8f9fa; color: #4a5568; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 1rem; text-align: left; font-weight: 600; }
.order-table th:first-child { border-top-left-radius: 8px; border-bottom-left-radius: 8px; }
.order-table th:last-child { border-top-right-radius: 8px; border-bottom-right-radius: 8px; }
.order-table td { padding: 1.25rem 1rem; border-bottom: 1px solid #edf2f7; vertical-align: middle; }
.order-table tr:last-child td { border-bottom: none; }
.order-table tr:hover { background-color: #f7fafc; }
.order-code { font-weight: 700; color: #1a2e4a; }
.order-date { color: #718096; font-size: 0.9rem; }
.btn-view { background: #edf2f7; color: #4a5568; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: all 0.2s; display: inline-block; }
.btn-view:hover { background: #e2e8f0; color: #1a2e4a; }
.empty-orders { text-align: center; padding: 3rem 1rem; color: #a0aec0; }
.empty-orders svg { margin-bottom: 1rem; opacity: 0.5; }

/* ── Prescription Wallet ── */
.rx-wallet-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
    margin-bottom: 1.25rem;
}
.rx-card {
    background: linear-gradient(145deg, #f0f7ff 0%, #e8f4ff 100%);
    border: 1.5px solid #c8dff7;
    border-radius: 14px;
    padding: 1.25rem;
    position: relative;
    transition: all 0.2s;
}
.rx-card:hover { box-shadow: 0 6px 20px rgba(26,46,74,0.1); border-color: #1a2e4a; }
.rx-card.is-default { border-color: #d4880a; background: linear-gradient(145deg, #fff9f0, #fff3e0); }
.rx-card-name {
    font-weight: 800; font-size: 1rem; color: #1a2e4a;
    margin-bottom: .75rem; display: flex; align-items: center; gap: .5rem;
}
.rx-default-badge {
    background: #d4880a; color: #fff; font-size: .68rem;
    padding: .15rem .5rem; border-radius: 99px; font-weight: 800;
}
.rx-table { width: 100%; font-size: .82rem; border-collapse: collapse; }
.rx-table th { color: #718096; font-weight: 700; padding: .25rem .5rem .25rem 0; width: 55%; font-size: .78rem; }
.rx-table td { color: #1a2e4a; font-weight: 600; padding: .25rem 0; }
.rx-divider { font-size: .7rem; color: #a0aec0; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; margin: .6rem 0 .3rem; }
.rx-card-actions { display: flex; gap: .5rem; margin-top: 1rem; padding-top: .75rem; border-top: 1px solid rgba(0,0,0,0.06); }
.rx-btn { flex: 1; padding: .4rem; border: 1.5px solid; border-radius: 6px; font-size: .8rem; font-weight: 700; cursor: pointer; transition: all 0.2s; background: transparent; }
.rx-btn-edit { border-color: #1a2e4a; color: #1a2e4a; }
.rx-btn-edit:hover { background: #1a2e4a; color: #fff; }
.rx-btn-default { border-color: #d4880a; color: #d4880a; }
.rx-btn-default:hover { background: #d4880a; color: #fff; }
.rx-btn-delete { border-color: #dc2626; color: #dc2626; }
.rx-btn-delete:hover { background: #dc2626; color: #fff; }
.rx-add-card {
    border: 2px dashed #c8dff7; border-radius: 14px; padding: 1.5rem;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.2s; color: #718096; text-align: center;
    min-height: 180px;
}
.rx-add-card:hover { border-color: #1a2e4a; color: #1a2e4a; background: #f0f7ff; }

/* ── Modal ── */
.rx-modal-backdrop {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.5); z-index: 1000;
    align-items: center; justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(3px);
}
.rx-modal-backdrop.open { display: flex; }
.rx-modal {
    background: #fff; border-radius: 16px;
    width: 100%; max-width: 680px;
    max-height: 90vh; overflow-y: auto;
    padding: 2rem;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    animation: modalSlideIn 0.25s ease;
}
@keyframes modalSlideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to   { transform: translateY(0);     opacity: 1; }
}
.rx-modal h3 { font-size: 1.25rem; font-weight: 800; color: #1a2e4a; margin: 0 0 1.5rem; }
.rx-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.rx-form-group { display: flex; flex-direction: column; gap: .35rem; }
.rx-form-group label { font-size: .82rem; font-weight: 700; color: #4a5568; }
.rx-form-group input[type="number"],
.rx-form-group input[type="text"],
.rx-form-group textarea {
    padding: .55rem .75rem; border: 1.5px solid #dbe4e7; border-radius: 8px;
    font-size: .9rem; transition: border-color 0.2s; outline: none;
    background: #f8fafc;
}
.rx-form-group input:focus,
.rx-form-group textarea:focus { border-color: #1a2e4a; background: #fff; }
.rx-modal-divider { font-size: .75rem; color: #a0aec0; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; margin: 1.25rem 0 .75rem; display: flex; align-items: center; gap: .5rem; }
.rx-modal-divider::before,
.rx-modal-divider::after { content: ''; flex: 1; height: 1px; background: #edf2f7; }
.rx-modal-actions { display: flex; gap: .75rem; margin-top: 1.5rem; justify-content: flex-end; }
.btn-rx-cancel { padding: .65rem 1.5rem; border: 1.5px solid #dbe4e7; border-radius: 8px; background: #fff; color: #718096; font-weight: 700; cursor: pointer; transition: all 0.2s; }
.btn-rx-cancel:hover { background: #f0f4f8; }
.btn-rx-save { padding: .65rem 1.75rem; border: none; border-radius: 8px; background: #1a2e4a; color: #fff; font-weight: 800; cursor: pointer; transition: all 0.2s; }
.btn-rx-save:hover { background: #2d4563; }
.rx-full-width { grid-column: 1 / -1; }

/* Notification toast */
.profile-toast {
    position: fixed; bottom: 2rem; right: 2rem; z-index: 9999;
    background: #1a2e4a; color: #fff; padding: .9rem 1.4rem;
    border-radius: 10px; font-size: .9rem; font-weight: 700;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    display: flex; align-items: center; gap: .6rem;
    transform: translateY(20px); opacity: 0; transition: .3s ease;
    pointer-events: none;
}
.profile-toast.show { transform: translateY(0); opacity: 1; }
</style>

<div class="profile-wrapper">
    <div class="container">
        <?php if ($message = get_flash('success')): ?>
            <div class="alert success" style="margin-bottom: 2rem; border-radius: 12px;"><?= e($message) ?></div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="profile-header-info">
                <h1>Xin chào, <?= e($user['full_name']) ?></h1>
                <p>Quản lý thông tin tài khoản và theo dõi đơn hàng của bạn.</p>
            </div>
            <div class="profile-header-actions">
                <a href="<?= e(APP_URL) ?>/logout.php" class="btn-logout">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    Đăng xuất
                </a>
            </div>
        </div>

        <div class="profile-content">
            <!-- Sidebar: Account Info & Stats -->
            <div class="profile-sidebar">
                <div class="premium-card" style="margin-bottom: 2rem;">
                    <h2>Thông tin tài khoản</h2>
                    <div class="info-group">
                        <label>Họ và tên</label>
                        <div class="info-value"><?= e($user['full_name']) ?></div>
                    </div>
                    <div class="info-group">
                        <label>Email</label>
                        <div class="info-value"><?= e($user['email']) ?></div>
                    </div>
                    <div class="info-group">
                        <label>Số điện thoại</label>
                        <div class="info-value"><?= e($user['phone'] ?: 'Chưa cập nhật') ?></div>
                    </div>
                    <div class="info-group">
                        <label>Vai trò</label>
                        <div class="info-value" style="display:inline-block; background:#e9eff2; color:#1a2e4a; padding:0.25rem 0.75rem; border-radius:99px; font-size:0.85rem; font-weight:700;">
                            <?= e(user_role_label($user['role_name'] ?? 'customer')) ?>
                        </div>
                    </div>
                </div>

                <div class="premium-card">
                    <h2>Tóm tắt mua sắm</h2>
                    <div class="stats-grid">
                        <div class="stat-box">
                            <span class="stat-value"><?= (int) $orderSummary['total_orders'] ?></span>
                            <span class="stat-label">Đơn hàng</span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-value" style="font-size:1.4rem; line-height:1.25; padding-top:0.25rem;"><?= e(format_currency((float) $orderSummary['total_spent'])) ?></span>
                            <span class="stat-label">Đã chi tiêu</span>
                        </div>
                    </div>

                    <div style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid #edf2f7;">
                        <div style="display:flex;align-items:center;gap:.5rem;font-size:.88rem;color:#4a5568;font-weight:600;margin-bottom:.5rem;">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Ví đơn kính của tôi
                        </div>
                        <div style="font-size:2rem;font-weight:800;color:#1a2e4a;"><?= count($prescriptions) ?><span style="font-size:.9rem;color:#718096;font-weight:500;"> hồ sơ</span></div>
                    </div>
                </div>
            </div>

            <!-- Main content with tabs -->
            <div class="profile-main">
                <!-- Tabs -->
                <div class="profile-tabs">
                    <button class="profile-tab-btn active" onclick="switchTab('orders', this)" id="tab-orders">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        Đơn hàng
                    </button>
                    <button class="profile-tab-btn" onclick="switchTab('reviews', this)" id="tab-reviews">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        Đánh giá
                        <?php if (count($pendingReviews) > 0): ?>
                            <span style="background:#d4880a;color:#fff;font-size:.68rem;padding:.1rem .4rem;border-radius:99px;font-weight:800;"><?= count($pendingReviews) ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="profile-tab-btn" onclick="switchTab('returns', this)" id="tab-returns">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                        Hoàn hàng
                        <?php if (count($pendingReturns) > 0): ?>
                            <span style="background:#1a2e4a;color:#f5b700;font-size:.68rem;padding:.1rem .4rem;border-radius:99px;font-weight:800;"><?= count($pendingReturns) ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="profile-tab-btn" onclick="switchTab('wallet', this)" id="tab-wallet">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Ví đơn kính
                        <?php if (count($prescriptions) > 0): ?>
                            <span style="background:#1a2e4a;color:#f5b700;font-size:.68rem;padding:.1rem .4rem;border-radius:99px;font-weight:800;"><?= count($prescriptions) ?></span>
                        <?php endif; ?>
                    </button>
                </div>

                <!-- Tab: Orders -->
                <div id="tab-content-orders" class="profile-tab-content active">
                    <div class="premium-card" style="min-height: 100%;">
                        <h2>
                            Đơn hàng gần đây
                            <a href="<?= e(APP_URL) ?>/orders.php" style="font-size:0.9rem; font-weight:600; color:#d4880a; text-decoration:none;">Xem tất cả →</a>
                        </h2>

                        <?php if (!$recentOrders): ?>
                            <div class="empty-orders">
                                <svg width="64" height="64" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                <h3>Chưa có đơn hàng nào</h3>
                                <p>Bạn chưa thực hiện đơn hàng nào gần đây.</p>
                                <a href="<?= e(APP_URL) ?>/products.php" class="btn btn-primary" style="margin-top:1rem; display:inline-block;">Mua sắm ngay</a>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="order-table">
                                    <thead>
                                        <tr>
                                            <th>Mã đơn</th>
                                            <th>Ngày đặt</th>
                                            <th>Loại đơn</th>
                                            <th>Trạng thái</th>
                                            <th>Tổng tiền</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentOrders as $order): ?>
                                            <tr>
                                                <td><div class="order-code"><?= e($order['order_code']) ?></div></td>
                                                <td><div class="order-date"><?= e(date('d/m/Y', strtotime((string) $order['created_at']))) ?></div></td>
                                                <td><?= e(order_type_label($order['order_type'])) ?></td>
                                                <td>
                                                    <span class="status-pill <?= e(order_status_class($order['status'])) ?>">
                                                        <?= e(order_status_label($order['status'])) ?>
                                                    </span>
                                                </td>
                                                <td><strong><?= e(format_currency((float) $order['total_amount'])) ?></strong></td>
                                                <td style="text-align: right;">
                                                    <a class="btn-view" href="<?= e(APP_URL) ?>/order-detail.php?code=<?= e($order['order_code']) ?>">Chi tiết</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tab: Reviews -->
                <div id="tab-content-reviews" class="profile-tab-content">
                    <div class="premium-card">
                        <h2>⭐ Đánh giá của tôi</h2>
                        <div class="sub-tabs">
                            <button class="sub-tab-btn active" onclick="switchSubTab('rev', 'pending', this)">
                                Chưa đánh giá (<?= count($pendingReviews) ?>)
                            </button>
                            <button class="sub-tab-btn" onclick="switchSubTab('rev', 'done', this)">
                                Đã đánh giá (<?= count($doneReviews) ?>)
                            </button>
                        </div>

                        <!-- Chưa đánh giá -->
                        <div class="sub-tab-pane active" id="sub-rev-pending">
                            <?php if (empty($pendingReviews)): ?>
                                <div class="empty-state-rv">
                                    <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <p style="font-weight:600;font-size:.9rem;">Không có sản phẩm nào cần đánh giá!</p>
                                    <p style="font-size:.82rem;">Tất cả sản phẩm đã được đánh giá hoặc chưa có đơn hoàn tất.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($pendingReviews as $rv): ?>
                                    <div class="rv-item">
                                        <img class="rv-thumb" src="<?= e($rv['thumbnail'] ? (str_starts_with($rv['thumbnail'],'/') ? APP_URL.$rv['thumbnail'] : APP_URL.'/'.$rv['thumbnail']) : APP_URL.'/assets/images/placeholder-glasses.png') ?>" alt="<?= e($rv['product_name']) ?>">
                                        <div class="rv-info">
                                            <div class="rv-name"><?= e($rv['product_name']) ?></div>
                                            <div class="rv-meta">
                                                Đơn <?= e($rv['order_code']) ?> &middot;
                                                <?php $meta2 = array_filter([$rv['color'],$rv['size_label']]); ?>
                                                <?php if ($meta2) echo e(implode(', ', $meta2)) . ' &middot; '; ?>
                                                <?= e(date('d/m/Y', strtotime($rv['order_date']))) ?>
                                            </div>
                                        </div>
                                        <a class="btn-write-rev"
                                           href="<?= e(APP_URL) ?>/write-review.php?order_id=<?= (int)$rv['order_id'] ?>&product_id=<?= (int)$rv['product_id'] ?>">
                                            ✍️ Viết đánh giá
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Đã đánh giá -->
                        <div class="sub-tab-pane" id="sub-rev-done">
                            <?php if (empty($doneReviews)): ?>
                                <div class="empty-state-rv">
                                    <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                    <p style="font-weight:600;font-size:.9rem;">Chưa có đánh giá nào.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($doneReviews as $rv): ?>
                                    <div class="rv-item-done" style="border: 1px solid #e4ebee; border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; background: #fff;">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                                            <div style="flex: 1; min-width: 250px;">
                                                <!-- Stars and date -->
                                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                                    <span class="rv-stars" style="color: #f59e0b; font-size: 1.1rem;"><?= str_repeat('★', (int)$rv['rating']) . str_repeat('☆', 5-(int)$rv['rating']) ?></span>
                                                    <span style="font-size: 0.78rem; color: #9aa3a6;"><?= e(date('d/m/Y H:i', strtotime($rv['review_date']))) ?></span>
                                                </div>
                                                
                                                <!-- Comment text -->
                                                <div class="rv-body" style="font-size: 0.9rem; color: #2d3748; line-height: 1.6; margin-bottom: 1rem; font-weight: 500;">
                                                    <?= nl2br(e($rv['body'])) ?>
                                                </div>
                                                
                                                <!-- Order & Product card -->
                                                <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: #f8fafc; border: 1px solid #edf2f7; border-radius: 8px;">
                                                    <img src="<?= e($rv['thumbnail'] ? (str_starts_with($rv['thumbnail'],'/') ? APP_URL.$rv['thumbnail'] : APP_URL.'/'.$rv['thumbnail']) : APP_URL.'/assets/images/placeholder-glasses.png') ?>" 
                                                         alt="<?= e($rv['product_name']) ?>" 
                                                         style="width: 48px; height: 48px; object-fit: contain; background: #fff; border-radius: 6px; border: 1px solid #e2e8f0; padding: 2px;">
                                                    <div>
                                                        <div style="font-size: 0.85rem; font-weight: 700; color: #1a2e4a;"><?= e($rv['product_name']) ?></div>
                                                        <div style="font-size: 0.75rem; color: #718096;">Đơn hàng: <strong><?= e($rv['order_code'] ?? 'N/A') ?></strong></div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Action Button -->
                                            <div style="align-self: center;">
                                                <a href="<?= e(APP_URL) ?>/product-detail.php?id=<?= (int)$rv['product_id'] ?>#reviews" 
                                                   class="btn-write-rev" 
                                                   style="background: #1a2e4a; color: #f5b700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.6rem 1.1rem; border-radius: 8px; font-size: 0.82rem; font-weight: 800; transition: 0.2s;">
                                                    Xem chi tiết
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tab: Returns -->
                <div id="tab-content-returns" class="profile-tab-content">
                    <div class="premium-card">
                        <h2>↩️ Hoàn hàng & Bảo hành</h2>
                        <div class="sub-tabs">
                            <button class="sub-tab-btn active" onclick="switchSubTab('ret', 'pending', this)">
                                Chưa gửi yêu cầu (<?= count($pendingReturns) ?>)
                            </button>
                            <button class="sub-tab-btn" onclick="switchSubTab('ret', 'done', this)">
                                Đã gửi yêu cầu (<?= count($doneReturns) ?>)
                            </button>
                        </div>

                        <!-- Chưa gửi -->
                        <div class="sub-tab-pane active" id="sub-ret-pending">
                            <?php if (empty($pendingReturns)): ?>
                                <div class="empty-state-rv">
                                    <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <p style="font-weight:600;font-size:.9rem;">Không có đơn nào cần xử lý.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($pendingReturns as $ret): ?>
                                    <div class="ret-item">
                                        <div class="ret-info">
                                            <div class="ret-code"><?= e($ret['order_code']) ?></div>
                                            <div class="ret-date"><?= e(date('d/m/Y', strtotime($ret['created_at']))) ?></div>
                                            <div class="ret-amount"><?= e(format_currency((float)$ret['total_amount'])) ?></div>
                                        </div>
                                        <a class="btn-send-ret" href="<?= e(APP_URL) ?>/submit-return.php?code=<?= e($ret['order_code']) ?>">
                                            Gửi yêu cầu
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Đã gửi -->
                        <div class="sub-tab-pane" id="sub-ret-done">
                            <?php if (empty($doneReturns)): ?>
                                <div class="empty-state-rv">
                                    <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <p style="font-weight:600;font-size:.9rem;">Chưa có yêu cầu nào.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($doneReturns as $ret): ?>
                                    <?php
                                        $retStatusClass = match($ret['rr_status']) {
                                            'approved' => 'ret-approved',
                                            'rejected' => 'ret-rejected',
                                            'resolved' => 'ret-resolved',
                                            default    => 'ret-pending',
                                        };
                                        $retStatusLabel = match($ret['rr_status']) {
                                            'pending'  => 'Chờ xử lý',
                                            'approved' => 'Đã duyệt',
                                            'rejected' => 'Bị từ chối',
                                            'received' => 'Đã nhận',
                                            'resolved' => 'Đã giải quyết',
                                            default    => $ret['rr_status'],
                                        };
                                        $retTypeLabel = match($ret['request_type']) {
                                            'return'   => 'Đổi hàng mới',
                                            'exchange' => 'Đổi sản phẩm lỗi',
                                            'warranty' => 'Bảo hành',
                                            'refund'   => 'Hoàn tiền',
                                            default    => $ret['request_type'],
                                        };
                                    ?>
                                    <div class="ret-item" style="flex-direction:column;align-items:flex-start;gap:.5rem;">
                                        <div style="display:flex;justify-content:space-between;width:100%;align-items:center;">
                                            <div>
                                                <div class="ret-code"><?= e($ret['order_code']) ?></div>
                                                <div class="ret-date"><?= e($retTypeLabel) ?> &middot; <?= e(date('d/m/Y', strtotime($ret['rr_date']))) ?></div>
                                            </div>
                                            <span class="ret-status-badge <?= $retStatusClass ?>"><?= e($retStatusLabel) ?></span>
                                        </div>
                                        <?php if (!empty($ret['reason'])): ?>
                                            <div style="font-size:.82rem;color:#4a5568;"><strong>Lý do gửi:</strong> <?= e($ret['reason']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($ret['resolution_note'])): ?>
                                            <div style="font-size:.82rem; color:#b91c1c; background: #fef2f2; padding: 0.6rem; border-radius: 6px; border: 1.5px solid #fca5a5; width: 100%; margin: 0.25rem 0;">
                                                <strong>Phản hồi từ cửa hàng:</strong> <?= e($ret['resolution_note']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <a href="<?= e(APP_URL) ?>/order-detail.php?code=<?= e($ret['order_code']) ?>" style="font-size:.78rem;color:#d4880a;font-weight:700;text-decoration:none;margin-top:0.25rem;">Xem đơn hàng &rarr;</a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tab: Prescription Wallet -->
                <div id="tab-content-wallet" class="profile-tab-content">
                    <div class="premium-card">
                        <h2>
                            🕶️ Ví đơn kính cá nhân
                            <button onclick="openRxModal()" style="font-size:.82rem;font-weight:700;color:#d4880a;background:transparent;border:none;cursor:pointer;padding:0;">
                                + Thêm hồ sơ
                            </button>
                        </h2>
                        <p style="color:#718096;font-size:.88rem;margin-bottom:1.25rem;margin-top:-.75rem;">
                            Lưu sẵn thông số khúc xạ để tự động điền khi đặt hàng kính. Tối đa 10 hồ sơ.
                        </p>

                        <div class="rx-wallet-grid" id="rxWalletGrid">
                            <?php foreach ($prescriptions as $rx): ?>
                                <div class="rx-card <?= $rx['is_default'] ? 'is-default' : '' ?>" id="rxCard<?= (int) $rx['id'] ?>">
                                    <div class="rx-card-name">
                                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <?= e($rx['profile_name']) ?>
                                        <?php if ($rx['is_default']): ?>
                                            <span class="rx-default-badge">Mặc định</span>
                                        <?php endif; ?>
                                    </div>

                                    <table class="rx-table">
                                        <tr>
                                            <th colspan="3">
                                                <div class="rx-divider">Mắt Phải (OD)</div>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th>Cầu (SPH)</th>
                                            <td><?= $rx['od_sphere'] !== null ? sprintf('%+.2f', $rx['od_sphere']) : '—' ?></td>
                                        </tr>
                                        <tr>
                                            <th>Trụ (CYL)</th>
                                            <td><?= $rx['od_cylinder'] !== null ? sprintf('%+.2f', $rx['od_cylinder']) : '—' ?></td>
                                        </tr>
                                        <tr>
                                            <th>Trục (AXIS)</th>
                                            <td><?= $rx['od_axis'] !== null ? $rx['od_axis'] . '°' : '—' ?></td>
                                        </tr>
                                        <?php if ($rx['od_addition'] !== null): ?>
                                        <tr>
                                            <th>Cộng (ADD)</th>
                                            <td><?= sprintf('%+.2f', $rx['od_addition']) ?></td>
                                        </tr>
                                        <?php endif; ?>

                                        <tr>
                                            <th colspan="3">
                                                <div class="rx-divider">Mắt Trái (OS)</div>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th>Cầu (SPH)</th>
                                            <td><?= $rx['os_sphere'] !== null ? sprintf('%+.2f', $rx['os_sphere']) : '—' ?></td>
                                        </tr>
                                        <tr>
                                            <th>Trụ (CYL)</th>
                                            <td><?= $rx['os_cylinder'] !== null ? sprintf('%+.2f', $rx['os_cylinder']) : '—' ?></td>
                                        </tr>
                                        <tr>
                                            <th>Trục (AXIS)</th>
                                            <td><?= $rx['os_axis'] !== null ? $rx['os_axis'] . '°' : '—' ?></td>
                                        </tr>
                                        <?php if ($rx['os_addition'] !== null): ?>
                                        <tr>
                                            <th>Cộng (ADD)</th>
                                            <td><?= sprintf('%+.2f', $rx['os_addition']) ?></td>
                                        </tr>
                                        <?php endif; ?>

                                        <tr>
                                            <th colspan="3">
                                                <div class="rx-divider">Khoảng cách đồng tử (PD)</div>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th>PD mắt phải</th>
                                            <td><?= $rx['pd_right'] !== null ? $rx['pd_right'] . ' mm' : '—' ?></td>
                                        </tr>
                                        <tr>
                                            <th>PD mắt trái</th>
                                            <td><?= $rx['pd_left'] !== null ? $rx['pd_left'] . ' mm' : '—' ?></td>
                                        </tr>
                                        <?php if ($rx['pd_distance'] !== null): ?>
                                        <tr>
                                            <th>PD nhìn xa</th>
                                            <td><?= $rx['pd_distance'] . ' mm' ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>

                                    <?php if (!empty($rx['note'])): ?>
                                        <p style="font-size:.78rem;color:#718096;margin-top:.5rem;padding-top:.5rem;border-top:1px solid rgba(0,0,0,0.06);">
                                            📝 <?= e($rx['note']) ?>
                                        </p>
                                    <?php endif; ?>

                                    <div class="rx-card-actions">
                                        <button class="rx-btn rx-btn-edit" onclick="editRx(<?= htmlspecialchars(json_encode($rx), ENT_QUOTES) ?>)">
                                            ✏️ Sửa
                                        </button>
                                        <?php if (!$rx['is_default']): ?>
                                        <button class="rx-btn rx-btn-default" onclick="setDefaultRx(<?= (int) $rx['id'] ?>)">
                                            ⭐ Mặc định
                                        </button>
                                        <?php endif; ?>
                                        <button class="rx-btn rx-btn-delete" onclick="deleteRx(<?= (int) $rx['id'] ?>, '<?= e($rx['profile_name']) ?>')">
                                            🗑️
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (count($prescriptions) < 10): ?>
                            <div class="rx-add-card" onclick="openRxModal()">
                                <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="margin-bottom:.75rem;opacity:.5;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"/>
                                </svg>
                                <div style="font-weight:700;font-size:.9rem;">Thêm hồ sơ đơn kính</div>
                                <div style="font-size:.8rem;margin-top:.25rem;">Lưu thông số kính cho bản thân hoặc người thân</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Prescription Modal -->
<div class="rx-modal-backdrop" id="rxModalBackdrop">
    <div class="rx-modal">
        <h3 id="rxModalTitle">🕶️ Thêm hồ sơ đơn kính</h3>
        <form id="rxForm">
            <input type="hidden" id="rxId" name="id" value="">
            <input type="hidden" id="rxAction" name="action" value="add">

            <div class="rx-form-group rx-full-width" style="margin-bottom:1rem;">
                <label for="rxProfileName">Tên hồ sơ *</label>
                <input type="text" id="rxProfileName" name="profile_name" placeholder="Ví dụ: Kính cận của tôi, Kính lão của Bố..." required maxlength="150">
            </div>

            <div class="rx-modal-divider">👁️ Mắt Phải (OD - Oculus Dexter)</div>
            <div class="rx-form-grid">
                <div class="rx-form-group">
                    <label>Cầu / SPH (Cận âm, Viễn dương)</label>
                    <input type="number" id="rxOdSphere" name="od_sphere" step="0.25" min="-25" max="25" placeholder="-3.00">
                </div>
                <div class="rx-form-group">
                    <label>Trụ / CYL (Loạn thị, thường âm)</label>
                    <input type="number" id="rxOdCylinder" name="od_cylinder" step="0.25" min="-10" max="10" placeholder="-0.75">
                </div>
                <div class="rx-form-group">
                    <label>Trục / AXIS (0 – 180°)</label>
                    <input type="number" id="rxOdAxis" name="od_axis" step="1" min="0" max="180" placeholder="180">
                </div>
                <div class="rx-form-group">
                    <label>Cộng / ADD (Kính hai tròng/lão)</label>
                    <input type="number" id="rxOdAddition" name="od_addition" step="0.25" min="0" max="4" placeholder="+1.50">
                </div>
            </div>

            <div class="rx-modal-divider">👁️ Mắt Trái (OS - Oculus Sinister)</div>
            <div class="rx-form-grid">
                <div class="rx-form-group">
                    <label>Cầu / SPH</label>
                    <input type="number" id="rxOsSphere" name="os_sphere" step="0.25" min="-25" max="25" placeholder="-2.50">
                </div>
                <div class="rx-form-group">
                    <label>Trụ / CYL</label>
                    <input type="number" id="rxOsCylinder" name="os_cylinder" step="0.25" min="-10" max="10" placeholder="-0.50">
                </div>
                <div class="rx-form-group">
                    <label>Trục / AXIS</label>
                    <input type="number" id="rxOsAxis" name="os_axis" step="1" min="0" max="180" placeholder="175">
                </div>
                <div class="rx-form-group">
                    <label>Cộng / ADD</label>
                    <input type="number" id="rxOsAddition" name="os_addition" step="0.25" min="0" max="4" placeholder="+1.50">
                </div>
            </div>

            <div class="rx-modal-divider">📏 Khoảng cách đồng tử (PD)</div>
            <div class="rx-form-grid">
                <div class="rx-form-group">
                    <label>PD mắt phải (mm)</label>
                    <input type="number" id="rxPdRight" name="pd_right" step="0.5" min="20" max="40" placeholder="31.5">
                </div>
                <div class="rx-form-group">
                    <label>PD mắt trái (mm)</label>
                    <input type="number" id="rxPdLeft" name="pd_left" step="0.5" min="20" max="40" placeholder="31.5">
                </div>
                <div class="rx-form-group">
                    <label>PD nhìn xa (mm)</label>
                    <input type="number" id="rxPdDistance" name="pd_distance" step="0.5" min="40" max="80" placeholder="63.0">
                </div>
                <div class="rx-form-group">
                    <label>PD nhìn gần (mm)</label>
                    <input type="number" id="rxPdNear" name="pd_near" step="0.5" min="40" max="80" placeholder="60.0">
                </div>
            </div>

            <div class="rx-form-group rx-full-width" style="margin-top:1rem;">
                <label>Ghi chú</label>
                <input type="text" id="rxNote" name="note" maxlength="200" placeholder="Ví dụ: Đơn năm 2024, khám tại BV Mắt TP.HCM">
            </div>

            <div class="rx-modal-actions">
                <button type="button" class="btn-rx-cancel" onclick="closeRxModal()">Hủy</button>
                <button type="submit" class="btn-rx-save" id="rxSaveBtn">💾 Lưu hồ sơ</button>
            </div>
        </form>
    </div>
</div>

<!-- Toast -->
<div class="profile-toast" id="profileToast">
    <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" stroke="#f5b700" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span id="profileToastMsg"></span>
</div>

<script>
const RX_ENDPOINT = '<?= e(APP_URL) ?>/prescription-wallet.php';

// ── Tab switching ────────────────────────────────────────────────
function switchTab(tab, btn) {
    document.querySelectorAll('.profile-tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.profile-tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-content-' + tab).classList.add('active');
    btn.classList.add('active');
    history.replaceState(null, '', '#' + tab);
}

// ── Sub-tab switching ───────────────────────────────────────────
function switchSubTab(group, pane, btn) {
    // Ẩn tất cả các pane của group này
    document.querySelectorAll('[id^="sub-' + group + '-"]').forEach(el => el.classList.remove('active'));
    // Bỏ active tất cả các button cùng hàng
    btn.closest('.sub-tabs').querySelectorAll('.sub-tab-btn').forEach(el => el.classList.remove('active'));
    // Kích hoạt pane và button được chọn
    document.getElementById('sub-' + group + '-' + pane).classList.add('active');
    btn.classList.add('active');
}


// ── Toast ────────────────────────────────────────────────────────
function showToast(msg, isError = false) {
    const toast = document.getElementById('profileToast');
    document.getElementById('profileToastMsg').textContent = msg;
    toast.style.background = isError ? '#dc2626' : '#1a2e4a';
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3200);
}

// ── Modal ────────────────────────────────────────────────────────
function openRxModal(rx = null) {
    const form = document.getElementById('rxForm');
    form.reset();
    document.getElementById('rxId').value = '';
    document.getElementById('rxAction').value = 'add';
    document.getElementById('rxModalTitle').textContent = '🕶️ Thêm hồ sơ đơn kính';
    document.getElementById('rxSaveBtn').textContent = '💾 Lưu hồ sơ';

    if (rx) {
        document.getElementById('rxModalTitle').textContent = '✏️ Sửa hồ sơ: ' + rx.profile_name;
        document.getElementById('rxSaveBtn').textContent = '💾 Cập nhật';
        document.getElementById('rxAction').value = 'update';
        document.getElementById('rxId').value = rx.id;
        document.getElementById('rxProfileName').value = rx.profile_name || '';
        document.getElementById('rxOdSphere').value = rx.od_sphere ?? '';
        document.getElementById('rxOdCylinder').value = rx.od_cylinder ?? '';
        document.getElementById('rxOdAxis').value = rx.od_axis ?? '';
        document.getElementById('rxOdAddition').value = rx.od_addition ?? '';
        document.getElementById('rxOsSphere').value = rx.os_sphere ?? '';
        document.getElementById('rxOsCylinder').value = rx.os_cylinder ?? '';
        document.getElementById('rxOsAxis').value = rx.os_axis ?? '';
        document.getElementById('rxOsAddition').value = rx.os_addition ?? '';
        document.getElementById('rxPdRight').value = rx.pd_right ?? '';
        document.getElementById('rxPdLeft').value = rx.pd_left ?? '';
        document.getElementById('rxPdDistance').value = rx.pd_distance ?? '';
        document.getElementById('rxPdNear').value = rx.pd_near ?? '';
        document.getElementById('rxNote').value = rx.note || '';
    }

    document.getElementById('rxModalBackdrop').classList.add('open');
    document.getElementById('rxProfileName').focus();
}

function editRx(rx) { openRxModal(rx); }

function closeRxModal() {
    document.getElementById('rxModalBackdrop').classList.remove('open');
}

// Close on backdrop click
document.getElementById('rxModalBackdrop').addEventListener('click', function(e) {
    if (e.target === this) closeRxModal();
});

// ── Form submit (AJAX) ───────────────────────────────────────────
document.getElementById('rxForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('rxSaveBtn');
    btn.disabled = true;
    btn.textContent = 'Đang lưu...';

    const data = new FormData(this);
    try {
        const res = await fetch(RX_ENDPOINT, { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
            closeRxModal();
            showToast('Đã lưu hồ sơ đơn kính thành công!');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(json.message || 'Có lỗi xảy ra!', true);
        }
    } catch (err) {
        showToast('Lỗi kết nối. Vui lòng thử lại.', true);
    } finally {
        btn.disabled = false;
        btn.textContent = '💾 Lưu hồ sơ';
    }
});

// ── Delete ───────────────────────────────────────────────────────
async function deleteRx(id, name) {
    if (!confirm(`Xóa hồ sơ "${name}"? Hành động này không thể hoàn tác.`)) return;
    const data = new FormData();
    data.append('action', 'delete');
    data.append('id', id);
    try {
        const res = await fetch(RX_ENDPOINT, { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
            document.getElementById('rxCard' + id)?.remove();
            showToast('Đã xóa hồ sơ.');
        } else {
            showToast(json.message || 'Có lỗi xảy ra!', true);
        }
    } catch (err) {
        showToast('Lỗi kết nối.', true);
    }
}

// ── Set default ──────────────────────────────────────────────────
async function setDefaultRx(id) {
    const data = new FormData();
    data.append('action', 'set_default');
    data.append('id', id);
    try {
        const res = await fetch(RX_ENDPOINT, { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
            showToast('Đã đặt làm hồ sơ mặc định!');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(json.message || 'Có lỗi!', true);
        }
    } catch (err) {
        showToast('Lỗi kết nối.', true);
    }
}

// Auto-open wallet tab if URL has #wallet
const _hash = location.hash.replace('#', '');
const _validTabs = ['orders', 'reviews', 'returns', 'wallet'];
if (_validTabs.includes(_hash)) {
    const btn = document.getElementById('tab-' + _hash);
    if (btn) switchTab(_hash, btn);
}
</script>

<?php require_once BASE_PATH . '/app/views/partials/footer.php'; ?>
