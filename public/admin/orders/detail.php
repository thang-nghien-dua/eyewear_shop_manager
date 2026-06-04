<?php

require_once __DIR__ . '/../../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

admin_only();

$db = Database::connect();

$orderId = max(0, (int) ($_GET['id'] ?? 0));
if ($orderId <= 0) {
    header('Location: ' . APP_URL . '/admin/orders/index.php?error=' . urlencode('Thiếu mã đơn hàng.'));
    exit;
}

$orderStmt = $db->prepare(
    "SELECT o.*, u.full_name AS user_full_name, u.email AS user_email, u.phone AS user_phone
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     WHERE o.id = :id
     LIMIT 1"
);
$orderStmt->execute(['id' => $orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    header('Location: ' . APP_URL . '/admin/orders/index.php?error=' . urlencode('Không tìm thấy đơn hàng.'));
    exit;
}

$itemStmt = $db->prepare(
    "SELECT oi.*, pv.color, pv.size_label, pv.material AS variant_material
     FROM order_items oi
     LEFT JOIN product_variants pv ON pv.id = oi.product_variant_id
     WHERE oi.order_id = :order_id
     ORDER BY oi.id ASC"
);
$itemStmt->execute(['order_id' => $orderId]);
$orderItems = $itemStmt->fetchAll();

$logStmt = $db->prepare(
    "SELECT l.*, u.full_name AS changed_by_name
     FROM order_status_logs l
     LEFT JOIN users u ON u.id = l.changed_by
     WHERE l.order_id = :order_id
     ORDER BY l.created_at DESC, l.id DESC"
);
$logStmt->execute(['order_id' => $orderId]);
$statusLogs = $logStmt->fetchAll();

$allowedStatuses = [
    'pending', 'awaiting_stock', 'checking_prescription', 'confirmed', 'processing',
    'lens_processing', 'shipping', 'completed', 'cancelled', 'refunded',
];

$pageTitle = 'Chi tiết đơn hàng - ' . APP_NAME;
$pageDescription = 'Chi tiết đơn hàng ' . $order['order_code'];
$adminPageTitle = 'Chi tiết đơn ' . $order['order_code'];
$adminPageSubtitle = 'Kiểm tra thông tin khách hàng, sản phẩm và cập nhật trạng thái xử lý.';

include BASE_PATH . '/app/views/partials/admin-head.php';
?>
<div class="admin-shell">
    <?php include BASE_PATH . '/app/views/partials/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php include BASE_PATH . '/app/views/partials/admin-topbar.php'; ?>

        <main class="admin-dashboard">
            <?php if (isset($_GET['updated'])): ?>
                <div class="alert success">Đã cập nhật trạng thái đơn hàng.</div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert warning"><?= e((string) $_GET['error']) ?></div>
            <?php endif; ?>

            <section class="admin-detail-grid">
                <section class="admin-detail-main">
                    <div class="admin-panel">
                        <div class="admin-panel-head">
                            <div>
                                <span class="admin-panel-kicker">Order overview</span>
                                <h2>Tổng quan đơn hàng</h2>
                                <p>Mã đơn <?= e($order['order_code']) ?> • tạo lúc <?= e(date('d/m/Y H:i', strtotime((string) $order['created_at']))) ?></p>
                            </div>
                            <a class="btn btn-secondary btn-sm" href="<?= e(APP_URL) ?>/admin/orders/index.php">Quay lại danh sách</a>
                        </div>

                        <div class="detail-grid">
                            <div>
                                <span class="detail-label">Loại đơn</span>
                                <strong><?= e(ucfirst((string) $order['order_type'])) ?></strong>
                            </div>
                            <div>
                                <span class="detail-label">Trạng thái</span>
                                <strong>
                                    <span class="status-pill <?= e(order_status_class((string) $order['status'])) ?>">
                                        <?= e(order_status_label((string) $order['status'])) ?>
                                    </span>
                                </strong>
                            </div>
                            <div>
                                <span class="detail-label">Thanh toán</span>
                                <strong>
                                    <span class="status-pill <?= e(payment_status_class((string) $order['payment_status'])) ?>">
                                        <?= e(payment_status_label((string) $order['payment_status'])) ?>
                                    </span>
                                </strong>
                            </div>
                            <div>
                                <span class="detail-label">Phương thức</span>
                                <strong><?= e(strtoupper((string) $order['payment_method'])) ?></strong>
                            </div>
                            <div>
                                <span class="detail-label">Tạm tính</span>
                                <strong><?= format_price($order['subtotal']) ?></strong>
                            </div>
                            <div>
                                <span class="detail-label">Phí ship</span>
                                <strong><?= format_price($order['shipping_fee']) ?></strong>
                            </div>
                            <div>
                                <span class="detail-label">Giảm giá</span>
                                <strong><?= format_price($order['discount_amount']) ?></strong>
                            </div>
                            <div>
                                <span class="detail-label">Tổng tiền</span>
                                <strong><?= format_price($order['total_amount']) ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="admin-panel">
                        <div class="admin-panel-head">
                            <div>
                                <span class="admin-panel-kicker">Customer</span>
                                <h2>Thông tin khách hàng</h2>
                            </div>
                        </div>

                        <div class="detail-grid">
                            <div>
                                <span class="detail-label">Tên khách</span>
                                <strong><?= e($order['customer_name']) ?></strong>
                            </div>
                            <div>
                                <span class="detail-label">Số điện thoại</span>
                                <strong><?= e($order['customer_phone']) ?></strong>
                            </div>
                            <div>
                                <span class="detail-label">Email</span>
                                <strong><?= e($order['customer_email']) ?></strong>
                            </div>
                            <div>
                                <span class="detail-label">Địa chỉ giao hàng</span>
                                <strong>
                                    <?= e(trim(implode(', ', array_filter([
                                        (string) $order['shipping_address_line'],
                                        (string) $order['shipping_ward'],
                                        (string) $order['shipping_district'],
                                        (string) $order['shipping_province'],
                                    ])))) ?>
                                </strong>
                            </div>
                        </div>

                        <?php if (!empty($order['note'])): ?>
                            <div class="order-note-box">
                                <span class="detail-label">Ghi chú khách hàng</span>
                                <p><?= nl2br(e((string) $order['note'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($order['internal_note'])): ?>
                            <div class="order-note-box">
                                <span class="detail-label">Ghi chú nội bộ</span>
                                <p><?= nl2br(e((string) $order['internal_note'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="admin-panel">
                        <div class="admin-panel-head">
                            <div>
                                <span class="admin-panel-kicker">Order items</span>
                                <h2>Sản phẩm trong đơn</h2>
                            </div>
                        </div>

                        <div class="order-item-list">
                            <?php foreach ($orderItems as $item): ?>
                                <?php
                                    $snapshot = [];
                                    if (!empty($item['variant_snapshot'])) {
                                        $decoded = json_decode((string) $item['variant_snapshot'], true);
                                        if (is_array($decoded)) {
                                            $snapshot = $decoded;
                                        }
                                    }
                                ?>
                                <article class="order-item-card">
                                    <div class="order-item-row">
                                        <div>
                                            <h3><?= e($item['product_name']) ?></h3>
                                            <p class="muted-small">SKU: <?= e($item['variant_sku']) ?></p>
                                            <?php if (!empty($snapshot['brand'])): ?>
                                                <p class="muted-small">Brand: <?= e((string) $snapshot['brand']) ?></p>
                                            <?php endif; ?>
                                            <p class="muted-small">
                                                <?= !empty($snapshot['color']) ? 'Màu: ' . e((string) $snapshot['color']) . ' • ' : '' ?>
                                                <?= !empty($snapshot['size_label']) ? 'Size: ' . e((string) $snapshot['size_label']) . ' • ' : '' ?>
                                                <?= !empty($snapshot['material']) ? 'Chất liệu: ' . e((string) $snapshot['material']) : '' ?>
                                            </p>
                                        </div>
                                        <div class="order-item-price">
                                            <span>x<?= (int) $item['quantity'] ?></span>
                                            <strong><?= format_price($item['line_total']) ?></strong>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <aside class="admin-detail-side">
                    <div class="admin-panel sticky-card">
                        <div class="admin-panel-head compact">
                            <div>
                                <span class="admin-panel-kicker">Workflow</span>
                                <h2>Đổi trạng thái</h2>
                            </div>
                        </div>

                        <form action="<?= e(APP_URL) ?>/admin/orders/update-status.php" method="post" class="admin-status-form">
                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">

                            <div class="form-field">
                                <label for="status">Trạng thái mới</label>
                                <select id="status" name="status" required>
                                    <?php foreach ($allowedStatuses as $statusOption): ?>
                                        <option value="<?= e($statusOption) ?>" <?= $statusOption === $order['status'] ? 'selected' : '' ?>>
                                            <?= e(order_status_label($statusOption)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-field">
                                <label for="note">Ghi chú nội bộ</label>
                                <textarea id="note" name="note" rows="4" placeholder="Ví dụ: Đã gọi xác nhận khách, đang chờ đóng gói..."></textarea>
                            </div>

                            <div class="admin-detail-actions">
                                <button class="btn btn-primary" type="submit">Cập nhật trạng thái</button>
                                <a class="btn btn-secondary" href="<?= e(APP_URL) ?>/admin/orders/index.php">Hủy</a>
                            </div>
                        </form>
                    </div>

                    <div class="admin-panel">
                        <div class="admin-panel-head compact">
                            <div>
                                <span class="admin-panel-kicker">Activity log</span>
                                <h2>Lịch sử trạng thái</h2>
                            </div>
                        </div>

                        <?php if ($statusLogs === []): ?>
                            <div class="empty-mini-card">
                                <p>Chưa có log trạng thái nào.</p>
                            </div>
                        <?php else: ?>
                            <div class="status-log-list">
                                <?php foreach ($statusLogs as $log): ?>
                                    <article class="status-log-item">
                                        <div class="status-log-top">
                                            <strong><?= e(order_status_label((string) $log['new_status'])) ?></strong>
                                            <small><?= e(date('d/m/Y H:i', strtotime((string) $log['created_at']))) ?></small>
                                        </div>
                                        <p class="muted-small">
                                            Từ: <?= e(order_status_label((string) $log['old_status'])) ?> •
                                            Bởi: <?= e($log['changed_by_name'] ?: 'System') ?>
                                        </p>
                                        <?php if (!empty($log['note'])): ?>
                                            <p><?= nl2br(e((string) $log['note'])) ?></p>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </aside>
            </section>
        </main>
    </div>
</div>
</body>
</html>