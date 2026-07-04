<?php

require_once __DIR__ . '/../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';
$db = Database::connect();
$orderCode = trim((string) ($_GET['code'] ?? ''));

if ($orderCode === '') {
    header('Location: ' . APP_URL . '/orders.php');
    exit;
}

// Lấy lỗi form đổi trả từ session (nếu có - do handle-return.php đặt vào)
$returnFormErrors = $_SESSION['return_form_errors'] ?? [];
$returnFormData   = $_SESSION['return_form_data']   ?? [];
unset($_SESSION['return_form_errors'], $_SESSION['return_form_data']);


$orderStmt = $db->prepare(
    "SELECT o.*, u.full_name AS account_name
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     WHERE o.order_code = :order_code
     LIMIT 1"
);
$orderStmt->execute(['order_code' => $orderCode]);
$order = $orderStmt->fetch();

if (!$order) {
    http_response_code(404);
    exit('Không tìm thấy đơn hàng.');
}

$currentUser = current_user();
$verifyErrors = [];

if ($currentUser && !empty($currentUser['id'])) {
    $isAdmin = (($currentUser['role_name'] ?? '') === 'admin');

    if ($isAdmin || (int) $order['user_id'] === (int) $currentUser['id']) {
        $accessGranted = true;
    } else {
        http_response_code(403);
        exit('403 - Bạn không có quyền xem đơn hàng này.');
    }
} else {
    $accessGranted = customer_can_access_order($orderCode);
}

if (!$accessGranted && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $verifyEmail = trim((string) ($_POST['email'] ?? ''));
    $verifyPhone = trim((string) ($_POST['phone'] ?? ''));

    if ($verifyEmail === '' || !filter_var($verifyEmail, FILTER_VALIDATE_EMAIL)) {
        $verifyErrors[] = 'Email xác thực không hợp lệ.';
    }
    if ($verifyPhone === '') {
        $verifyErrors[] = 'Bạn chưa nhập số điện thoại.';
    }

    if ($verifyErrors === []) {
        if (
            mb_strtolower($verifyEmail, 'UTF-8') === mb_strtolower((string) $order['customer_email'], 'UTF-8')
            && normalize_phone($verifyPhone) === normalize_phone((string) $order['customer_phone'])
        ) {
            grant_order_access($orderCode);
            $accessGranted = true;
        } else {
            $verifyErrors[] = 'Thông tin xác thực chưa khớp với đơn hàng này.';
        }
    }
}

$pageTitle = 'Chi tiết đơn hàng';
$pageDescription = 'Theo dõi đơn hàng ' . $orderCode;
$headerKeyword = '';

include BASE_PATH . '/app/views/partials/head.php';
include BASE_PATH . '/app/views/partials/header.php';
?>

<main class="page-section">
    <div class="container order-detail-page">
        <section class="orders-search-card">
            <div class="order-detail-top">
                <div>
                    <p class="eyebrow">Theo dõi đơn hàng</p>
                    <h1 class="no-margin">Chi tiết đơn <?= e($order['order_code']) ?></h1>
                    <p class="summary-note">Xem thông tin sản phẩm, trạng thái xử lý và lịch sử cập nhật của đơn hàng.</p>
                </div>
                <div class="order-tag-row">
                    <span class="status-pill <?= e(order_status_class($order['status'])) ?>"><?= e(order_status_label($order['status'])) ?></span>
                    <span class="order-type-pill"><?= e(order_type_label($order['order_type'])) ?></span>
                </div>
            </div>
        </section>

        <?php if (!$accessGranted): ?>
            <section class="order-verify-card">
                <div class="section-heading-row compact">
                    <h2>Xác thực để xem đơn hàng</h2>
                    <p>Nhập đúng email và số điện thoại đã dùng khi đặt hàng.</p>
                </div>

                <?php if ($verifyErrors !== []): ?>
                    <div class="alert warning">
                        <ul class="form-error-list">
                            <?php foreach ($verifyErrors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" class="form-grid two-cols" style="margin-top:18px;">
                    <div class="form-field">
                        <label for="verify_email">Email đặt hàng</label>
                        <input id="verify_email" name="email" type="email" placeholder="ban@example.com" required>
                    </div>
                    <div class="form-field">
                        <label for="verify_phone">Số điện thoại</label>
                        <input id="verify_phone" name="phone" type="text" placeholder="0901234567" required>
                    </div>
                    <div class="form-field full-width">
                        <button class="btn-primary" type="submit">Xác thực và xem đơn</button>
                    </div>
                </form>

                <p class="verify-note" style="margin-top:14px;">Mẹo: Nếu đây là đơn bạn vừa tạo xong, bạn cũng có thể mở từ trang <a class="link-inline" href="<?= e(APP_URL) ?>/orders.php">Lịch sử đơn hàng</a>.</p>
            </section>
        <?php else: ?>
            <?php
            $itemStmt = $db->prepare(
                'SELECT product_name, variant_sku, quantity, unit_price, lens_price, line_total, variant_snapshot
                 FROM order_items
                 WHERE order_id = :order_id
                 ORDER BY id ASC'
            );
            $itemStmt->execute(['order_id' => $order['id']]);
            $orderItems = $itemStmt->fetchAll();

            $logStmt = $db->prepare(
                'SELECT osl.old_status, osl.new_status, osl.note, osl.created_at, u.full_name AS changed_by_name
                 FROM order_status_logs osl
                 LEFT JOIN users u ON u.id = osl.changed_by
                 WHERE osl.order_id = :order_id
                 ORDER BY osl.id ASC'
            );
            $logStmt->execute(['order_id' => $order['id']]);
            $statusLogs = $logStmt->fetchAll();

            // Lấy yêu cầu đổi trả/bảo hành nếu có
            $returnRequestStmt = $db->prepare('SELECT * FROM return_requests WHERE order_id = :order_id LIMIT 1');
            $returnRequestStmt->execute(['order_id' => $order['id']]);
            $returnRequest = $returnRequestStmt->fetch();

            // Lấy thông tin sản phẩm trong đơn để hiển thị nút đánh giá
            $reviewableItems = [];
            if ($order['status'] === 'completed' && is_logged_in()) {
                $uid = auth_user()['id'];
                try {
                    $reviewItemStmt = $db->prepare("
                        SELECT DISTINCT
                            oi.product_name,
                            pv.product_id,
                            p.id AS pid,
                            p.slug AS product_slug,
                            (SELECT COUNT(*) FROM product_reviews pr
                             WHERE pr.user_id = :uid AND pr.product_id = pv.product_id) AS already_reviewed
                        FROM order_items oi
                        INNER JOIN product_variants pv ON pv.id = oi.product_variant_id
                        INNER JOIN products p ON p.id = pv.product_id
                        WHERE oi.order_id = :order_id
                    ");
                    $reviewItemStmt->execute(['uid' => $uid, 'order_id' => $order['id']]);
                    $reviewableItems = $reviewItemStmt->fetchAll();
                } catch (\Throwable $e) { /* Bảng chưa tồn tại */ }
            }
            ?>

            <div class="order-detail-layout">
                <div class="order-detail-side">
                    <section class="order-detail-card">
                        <div class="section-heading-row compact">
                            <h2>Thông tin đơn hàng</h2>
                            <a href="<?= e(APP_URL) ?>/orders.php" class="back-link">
                                <i class="fi fi-rr-angle-left icon"></i>
                                Quay lại danh sách đơn
                            </a>
                        </div>

                        <div class="detail-list-grid">
                            <div class="detail-box"><span>Mã đơn</span><strong><?= e($order['order_code']) ?></strong></div>
                            <div class="detail-box"><span>Loại đơn</span><strong><?= e(order_type_label($order['order_type'])) ?></strong></div>
                            <div class="detail-box"><span>Thanh toán</span><strong><?= e(payment_method_label($order['payment_method'])) ?></strong></div>
                            <div class="detail-box"><span>Trạng thái thanh toán</span><strong><?= e(payment_status_label($order['payment_status'])) ?></strong></div>
                            <div class="detail-box"><span>Ngày tạo</span><strong><?= e(date('d/m/Y H:i', strtotime((string) $order['created_at']))) ?></strong></div>
                            <div class="detail-box"><span>Cập nhật gần nhất</span><strong><?= e(date('d/m/Y H:i', strtotime((string) $order['updated_at']))) ?></strong></div>
                        </div>

                        <div class="order-table-wrap">
                            <table class="order-items-table">
                                <thead>
                                    <tr>
                                        <th>Sản phẩm</th>
                                        <th>SKU</th>
                                        <th>SL</th>
                                        <th>Đơn giá</th>
                                        <th>Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderItems as $item): ?>
                                        <tr>
                                            <td><?= e($item['product_name']) ?></td>
                                            <td><?= e($item['variant_sku']) ?></td>
                                            <td><?= (int) $item['quantity'] ?></td>
                                            <td><?= format_price($item['unit_price']) ?></td>
                                            <td><?= format_price($item['line_total']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="order-detail-card">
                        <div class="section-heading-row compact">
                            <h2>Nhật ký trạng thái</h2>
                            <p><?= count($statusLogs) ?> mốc cập nhật</p>
                        </div>

                        <?php if ($statusLogs === []): ?>
                            <div class="order-empty-card">
                                <div>
                                    <div class="empty-cart-icon" style="margin:0 auto 12px;"><i class="fi fi-rr-time-forward icon icon-lg"></i></div>
                                    <h3>Chưa có log trạng thái</h3>
                                    <p>Hệ thống sẽ hiển thị các mốc cập nhật khi đơn bắt đầu được xử lý.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="order-timeline-list">
                                <?php foreach ($statusLogs as $log): ?>
                                    <div class="order-timeline-item">
                                        <span class="order-timeline-dot"></span>
                                        <div>
                                            <strong><?= e(order_status_label($log['new_status'])) ?></strong>
                                            <p>
                                                <?= $log['old_status'] ? e(order_status_label($log['old_status'])) . ' → ' : '' ?>
                                                <?= e(order_status_label($log['new_status'])) ?>
                                            </p>
                                            <?php if (!empty($log['note'])): ?>
                                                <p><?= e($log['note']) ?></p>
                                            <?php endif; ?>
                                            <small>
                                                <?= e($log['changed_by_name'] ?: 'System') ?> ·
                                                <?= e(date('d/m/Y H:i', strtotime((string) $log['created_at']))) ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <aside class="order-detail-side">
                    <section class="order-detail-card">
                        <div class="section-heading-row compact">
                            <h2>Thông tin nhận hàng</h2>
                        </div>
                        <div class="summary-row">
                            <span>Khách hàng</span>
                            <strong><?= e($order['customer_name']) ?></strong>
                        </div>
                        <div class="summary-row">
                            <span>Email</span>
                            <strong><?= e($order['customer_email']) ?></strong>
                        </div>
                        <div class="summary-row">
                            <span>Số điện thoại</span>
                            <strong><?= e($order['customer_phone']) ?></strong>
                        </div>
                        <div class="summary-row summary-column">
                            <span>Địa chỉ giao hàng</span>
                            <strong><?= e(format_order_address($order)) ?></strong>
                        </div>
                        <?php if (!empty($order['note'])): ?>
                            <div class="summary-row summary-column">
                                <span>Ghi chú</span>
                                <strong><?= nl2br(e((string) $order['note'])) ?></strong>
                            </div>
                        <?php endif; ?>
                    </section>

                     <section class="order-detail-card order-side-card">
                         <div class="section-heading-row compact">
                             <h2>Tổng thanh toán</h2>
                         </div>
                         <div class="summary-row"><span>Tạm tính</span><strong><?= format_price($order['subtotal']) ?></strong></div>
                         <div class="summary-row"><span>Tiền tròng</span><strong><?= format_price($order['lens_total']) ?></strong></div>
                         <div class="summary-row"><span>Phí giao hàng</span><strong><?= format_price($order['shipping_fee']) ?></strong></div>
                         <div class="summary-row"><span>Giảm giá</span><strong><?= format_price($order['discount_amount']) ?></strong></div>
                         <div class="summary-row total"><span>Tổng cộng</span><strong><?= format_price($order['total_amount']) ?></strong></div>
                     </section>

                     <?php if (!in_array($order['status'], ['cancelled', 'completed', 'refunded'])): ?>
                         <section class="order-detail-card order-side-card" style="margin-top: 1.5rem;">
                             <div class="section-heading-row compact">
                                 <h2>Quản lý đơn hàng</h2>
                             </div>
                             
                             <?php if (!empty($order['cancel_requested'])): ?>
                                 <div class="alert warning" style="margin-bottom: 0;">
                                     <p><strong>Đã gửi yêu cầu hủy đơn</strong></p>
                                     <p style="font-size: 0.85rem; margin-top: 0.25rem;">Đang chờ cửa hàng xác nhận.</p>
                                 </div>
                             <?php else: ?>
                                 <div style="margin-top: 1rem;">
                                     <?php if ($order['payment_status'] === 'unpaid' && in_array($order['status'], ['pending', 'awaiting_stock', 'checking_prescription'])): ?>
                                         <a href="<?= e(APP_URL) ?>/cancel-order.php?code=<?= e($order['order_code']) ?>&action=cancel" class="btn btn-primary" style="width: 100%; background: #ef4444; border-color: #ef4444; justify-content: center; padding: 0.75rem; text-align: center; display: block; text-decoration: none;">Hủy đơn hàng ngay</a>
                                     <?php else: ?>
                                         <a href="<?= e(APP_URL) ?>/cancel-order.php?code=<?= e($order['order_code']) ?>&action=request_cancel" class="btn btn-secondary" style="width: 100%; justify-content: center; padding: 0.75rem; text-align: center; display: block; text-decoration: none;">Yêu cầu hủy đơn</a>
                                         <p style="font-size: 0.8rem; color: #718096; margin-top: 0.5rem; text-align: center;">Đơn hàng đã qua khâu xác nhận nên cần cửa hàng duyệt yêu cầu hủy.</p>
                                     <?php endif; ?>
                                 </div>
                             <?php endif; ?>
                         </section>
                     <?php endif; ?>
                 </aside>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include BASE_PATH . '/app/views/partials/footer.php'; ?>

<?php if (($_GET['scrollto'] ?? '') === 'return'): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('return-section');
    if (el) {
        setTimeout(function () { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 300);
    }
});
</script>
<?php endif; ?>
<?php if (!empty($_GET['scrollto'])): ?>
<script>
// Hiện flash success nếu có
(function () {
    var flash = document.querySelector('.alert.success, .flash-success');
    if (flash) { flash.scrollIntoView({ behavior: 'smooth' }); }
})();
</script>
<?php endif; ?>
