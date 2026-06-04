<?php

require_once __DIR__ . '/../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

auth_only();
require_login();
$user = auth_user();

$db = Database::connect();

$orderSummaryStmt = $db->prepare(
    'SELECT COUNT(*) AS total_orders,
            COALESCE(SUM(total_amount), 0) AS total_spent
     FROM orders
     WHERE user_id = :user_id'
);
$orderSummaryStmt->execute(['user_id' => $user['id']]);
$orderSummary = $orderSummaryStmt->fetch() ?: ['total_orders' => 0, 'total_spent' => 0];

$recentOrdersStmt = $db->prepare(
    'SELECT id, order_code, order_type, status, total_amount, created_at
     FROM orders
     WHERE user_id = :user_id
     ORDER BY id DESC
     LIMIT 5'
);
$recentOrdersStmt->execute(['user_id' => $user['id']]);
$recentOrders = $recentOrdersStmt->fetchAll();

$pageTitle = 'Tài khoản của tôi';
require_once BASE_PATH . '/app/views/partials/head.php';
require_once BASE_PATH . '/app/views/partials/header.php';
?>
<main class="page-section">
    <div class="container">
        <?php if ($message = get_flash('success')): ?>
            <div class="alert success"><?= e($message) ?></div>
        <?php endif; ?>

        <section class="section-heading-row">
            <div>
                <h1>Xin chào, <?= e($user['full_name']) ?></h1>
                <p>Quản lý thông tin tài khoản và xem nhanh đơn hàng gần đây.</p>
            </div>
            <a class="btn-outline" href="<?= e(APP_URL) ?>/orders.php">
                <i class="fi fi-rr-receipt icon"></i>
                <span>Đơn hàng của tôi</span>
            </a>
        </section>

        <div class="profile-layout">
            
            <section class="profile-card">
                <h2>Thông tin tài khoản</h2>
                <div class="profile-info-list">
                    <div class="profile-info-item"><span>Vai trò</span><strong><?= e(user_role_label($user['role_name'])) ?></strong></div>
                    <div class="profile-info-item"><span>Email</span><strong><?= e($user['email']) ?></strong></div>
                    <div class="profile-info-item"><span>Số điện thoại</span><strong><?= e($user['phone'] ?: 'Chưa cập nhật') ?></strong></div>
                </div>
                 <div class="profile-actions">
                    <a href="<?= e(APP_URL) ?>/logout.php" class="btn-logout">
                    <i class="fi fi-rr-sign-out-alt icon icon-sm"></i>
                    Đăng xuất
                </a>
            </div>

            </section>

            <section class="profile-card">
                <h2>Tóm tắt mua hàng</h2>
                <div class="stats-row">
                    <div class="stat-card">
                        <strong><?= (int) $orderSummary['total_orders'] ?></strong>
                        <span>Tổng đơn hàng</span>
                    </div>
                    <div class="stat-card">
                        <strong><?= e(format_currency((float) $orderSummary['total_spent'])) ?></strong>
                        <span>Tổng chi tiêu</span>
                    </div>
                </div>
            </section>
        </div>

        <section class="profile-card">
            <div class="section-heading-row compact">
                <div>
                    <h2>Đơn hàng gần đây</h2>
                    <p>Hiển thị 5 đơn mới nhất của tài khoản hiện tại.</p>
                </div>
            </div>
            
            <?php if (!$recentOrders): ?>
                <div class="empty-state">
                    <p>Bạn chưa có đơn hàng nào.</p>
                </div>
            <?php else: ?>
                <div class="order-table-wrap">
                    <table class="order-items-table">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Loại đơn</th>
                                <th>Trạng thái</th>
                                <th>Tổng tiền</th>
                                <th>Ngày tạo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><strong><?= e($order['order_code']) ?></strong></td>
                                    <td><?= e(order_type_label($order['order_type'])) ?></td>
                                    <td>
                                        <span class="status-pill <?= e(order_status_class($order['status'])) ?>">
                                            <?= e(order_status_label($order['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= e(format_currency((float) $order['total_amount'])) ?></td>
                                    <td><?= e(date('d/m/Y H:i', strtotime((string) $order['created_at']))) ?></td>
                                    <td>
                                        <a class="btn-outline btn-sm" href="<?= e(APP_URL) ?>/order-detail.php?id=<?= (int) $order['id'] ?>">
                                            Chi tiết
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php require_once BASE_PATH . '/app/views/partials/footer.php'; ?>
