<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/helpers/functions.php';

$db = Database::connect();
$orderCode = trim((string) ($_GET['code'] ?? ''));

if ($orderCode === '') {
    header('Location: ' . APP_URL . '/products.php');
    exit;
}

$orderStmt = $db->prepare(
    "SELECT id, order_code, order_type, status, customer_name, customer_email, customer_phone,
            shipping_address_line, shipping_ward, shipping_district, shipping_province,
            subtotal, shipping_fee, discount_amount, total_amount, payment_method, created_at
     FROM orders
     WHERE order_code = :order_code
     LIMIT 1"
);
$orderStmt->execute(['order_code' => $orderCode]);
$order = $orderStmt->fetch();

if (!$order) {
    http_response_code(404);
    exit('Không tìm thấy đơn hàng.');
}

$itemStmt = $db->prepare(
    'SELECT product_name, quantity, unit_price, lens_price, line_total, variant_sku, variant_snapshot
     FROM order_items
     WHERE order_id = :order_id
     ORDER BY id ASC'
);
$itemStmt->execute(['order_id' => $order['id']]);
$orderItems = $itemStmt->fetchAll();

$pageTitle = 'Đặt hàng thành công';
$pageDescription = 'Đơn hàng ' . $order['order_code'] . ' đã được tạo';
$headerKeyword = '';

include BASE_PATH . '/app/views/partials/head.php';
include BASE_PATH . '/app/views/partials/header.php';
?>

<main class="page-section">
    <div class="container order-success-page">
        <section class="success-hero-card">
            <div class="success-icon-wrap">
                <i class="fi fi-rr-badge-check icon"></i>
            </div>
            <p class="eyebrow">Đặt hàng thành công</p>
            <h1>Cảm ơn bạn đã mua hàng tại LUMINA</h1>
            <p>Đơn hàng <strong><?= e($order['order_code']) ?></strong> đã được tạo thành công. Bạn có thể dùng mã này để demo luồng quản lý đơn ở phần admin sau.</p>
            <div class="hero-actions">
                <a href="<?= e(APP_URL) ?>/orders.php" class="btn btn-primary">Xem lịch sử đơn</a>
                <a href="<?= e(APP_URL) ?>/order-detail.php?code=<?= urlencode((string) $order['order_code']) ?>" class="btn btn-secondary">Chi tiết đơn này</a>
            </div>
        </section>

        <div class="order-success-layout">
            <section class="order-success-card">
                <div class="section-heading-row compact">
                    <h2>Thông tin đơn hàng</h2>
                </div>
                <div class="detail-list-grid">
                    <div class="detail-box"><span>Mã đơn</span><strong><?= e($order['order_code']) ?></strong></div>
                    <div class="detail-box"><span>Loại đơn</span><strong><?= e($order['order_type']) ?></strong></div>
                    <div class="detail-box"><span>Trạng thái</span><strong><?= e($order['status']) ?></strong></div>
                    <div class="detail-box"><span>Thanh toán</span><strong><?= e($order['payment_method']) ?></strong></div>
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

            <aside class="order-success-card order-side-card">
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
                    <span>Điện thoại</span>
                    <strong><?= e($order['customer_phone']) ?></strong>
                </div>
                <div class="summary-row summary-column">
                    <span>Địa chỉ</span>
                    <strong>
                        <?= e($order['shipping_address_line']) ?><br>
                        <?= e(trim(($order['shipping_ward'] ? $order['shipping_ward'] . ', ' : '') . ($order['shipping_district'] ? $order['shipping_district'] . ', ' : '') . ($order['shipping_province'] ?: ''))) ?>
                    </strong>
                </div>
                <div class="summary-row">
                    <span>Tạm tính</span>
                    <strong><?= format_price($order['subtotal']) ?></strong>
                </div>
                <div class="summary-row">
                    <span>Vận chuyển</span>
                    <strong><?= format_price($order['shipping_fee']) ?></strong>
                </div>
                <div class="summary-row">
                    <span>Giảm giá</span>
                    <strong><?= format_price($order['discount_amount']) ?></strong>
                </div>
                <div class="summary-row total">
                    <span>Tổng cộng</span>
                    <strong><?= format_price($order['total_amount']) ?></strong>
                </div>
            </aside>
        </div>
    </div>
</main>

<?php include BASE_PATH . '/app/views/partials/footer.php'; ?>
