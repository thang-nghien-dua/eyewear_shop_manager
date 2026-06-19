<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/helpers/functions.php';

$db = Database::connect();

$vnp_SecureHash = $_GET['vnp_SecureHash'] ?? '';
$inputData = array();
foreach ($_GET as $key => $value) {
    if (substr($key, 0, 4) == "vnp_") {
        $inputData[$key] = $value;
    }
}

unset($inputData['vnp_SecureHash']);
ksort($inputData);
$i = 0;
$hashData = "";
foreach ($inputData as $key => $value) {
    if ($i == 1) {
        $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashData .= urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
}

$secureHash = hash_hmac('sha512', $hashData, VNP_HASH_SECRET);

$isValidSignature = ($secureHash === $vnp_SecureHash);
$responseCode = $_GET['vnp_ResponseCode'] ?? '';
$orderCode = $_GET['vnp_TxnRef'] ?? '';
$vnp_TransactionNo = $_GET['vnp_TransactionNo'] ?? '';
$vnp_Amount = $_GET['vnp_Amount'] ?? 0;

$order = null;
if ($orderCode !== '') {
    $orderStmt = $db->prepare("SELECT * FROM orders WHERE order_code = :order_code LIMIT 1");
    $orderStmt->execute(['order_code' => $orderCode]);
    $order = $orderStmt->fetch();
}

$paymentSuccess = false;
$errorMessage = '';

if (!$isValidSignature) {
    $errorMessage = 'Chữ ký kiểm tra VNPAY không hợp lệ (Sai checksum).';
} elseif (!$order) {
    $errorMessage = 'Không tìm thấy đơn hàng tương ứng với giao dịch này.';
} else {
    if ($responseCode === '00') {
        $paymentSuccess = true;
        
        try {
            $db->beginTransaction();
            
            // Cập nhật trạng thái thanh toán
            if ($order['payment_status'] !== 'paid') {
                $updateOrder = $db->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = :id");
                $updateOrder->execute(['id' => $order['id']]);
                
                // Ghi nhận lịch sử trạng thái đơn hàng
                $insertLog = $db->prepare(
                    "INSERT INTO order_status_logs (order_id, changed_by, old_status, new_status, note) 
                     VALUES (:order_id, null, :old_status, :new_status, :note)"
                );
                $insertLog->execute([
                    'order_id' => $order['id'],
                    'old_status' => $order['status'],
                    'new_status' => $order['status'],
                    'note' => 'Thanh toán trực tuyến thành công qua VNPAY. Mã giao dịch: ' . $vnp_TransactionNo
                ]);
            }
            
            $db->commit();
            
            // Xóa giỏ hàng khi thanh toán thành công
            $_SESSION['cart'] = [];
            
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $errorMessage = 'Lỗi hệ thống khi cập nhật trạng thái đơn hàng: ' . $e->getMessage();
            $paymentSuccess = false;
        }
    } else {
        $paymentSuccess = false;
        $errorMessage = 'Giao dịch thanh toán không thành công. Mã phản hồi từ VNPAY: ' . $responseCode;
    }
}

$pageTitle = $paymentSuccess ? 'Thanh toán thành công' : 'Thanh toán thất bại';
$pageDescription = 'Kết quả thanh toán đơn hàng ' . e($orderCode) . ' qua VNPAY';
$headerKeyword = '';

include BASE_PATH . '/app/views/partials/head.php';
include BASE_PATH . '/app/views/partials/header.php';
?>

<main class="page-section">
    <div class="container order-success-page">
        <?php if ($paymentSuccess): ?>
            <section class="success-hero-card">
                <div class="success-icon-wrap" style="background: #eefbf3; color: #24683c;">
                    <i class="fi fi-rr-badge-check icon"></i>
                </div>
                <p class="eyebrow" style="color: #24683c;">Thanh toán thành công</p>
                <h1>Cảm ơn bạn đã hoàn tất thanh toán!</h1>
                <p>Đơn hàng <strong><?= e($orderCode) ?></strong> đã được thanh toán thành công qua cổng VNPAY.</p>
                <div class="hero-actions">
                    <a href="<?= e(APP_URL) ?>/orders.php" class="btn btn-primary">Lịch sử đơn hàng</a>
                    <a href="<?= e(APP_URL) ?>/order-detail.php?code=<?= urlencode((string)$orderCode) ?>" class="btn btn-secondary">Chi tiết đơn này</a>
                </div>
            </section>
        <?php else: ?>
            <section class="success-hero-card" style="background: linear-gradient(135deg, #ffffff 0%, #fff0f0 100%);">
                <div class="success-icon-wrap" style="background: #fff0f0; color: #b42318;">
                    <i class="fi fi-rr-cross-circle icon"></i>
                </div>
                <p class="eyebrow" style="color: #b42318;">Thanh toán thất bại</p>
                <h1>Giao dịch chưa được hoàn tất</h1>
                <p style="color: #6a6a6a;"><?= e($errorMessage) ?></p>
                <div class="hero-actions">
                    <a href="<?= e(APP_URL) ?>/checkout.php" class="btn btn-primary">Thử thanh toán lại</a>
                    <a href="<?= e(APP_URL) ?>/order-detail.php?code=<?= urlencode((string)$orderCode) ?>" class="btn btn-secondary">Xem chi tiết đơn</a>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($order): ?>
            <div class="order-success-layout" style="margin-top: 2rem;">
                <section class="order-success-card">
                    <div class="section-heading-row compact">
                        <h2>Chi tiết giao dịch</h2>
                    </div>
                    <div class="detail-list-grid">
                        <div class="detail-box"><span>Mã đơn hàng</span><strong><?= e($orderCode) ?></strong></div>
                        <div class="detail-box"><span>Mã giao dịch VNPAY</span><strong><?= e($vnp_TransactionNo ?: 'N/A') ?></strong></div>
                        <div class="detail-box"><span>Số tiền giao dịch</span><strong><?= format_price($vnp_Amount / 100) ?></strong></div>
                        <div class="detail-box"><span>Phương thức thanh toán</span><strong>VNPAY (ATM/QR/Credit Card)</strong></div>
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
                        <span>Điện thoại</span>
                        <strong><?= e($order['customer_phone']) ?></strong>
                    </div>
                    <div class="summary-row summary-column">
                        <span>Địa chỉ giao hàng</span>
                        <strong>
                            <?= e($order['shipping_address_line']) ?><br>
                            <?= e(trim(($order['shipping_ward'] ? $order['shipping_ward'] . ', ' : '') . ($order['shipping_district'] ? $order['shipping_district'] . ', ' : '') . ($order['shipping_province'] ?: ''))) ?>
                        </strong>
                    </div>
                    <div class="summary-row total">
                        <span>Tổng tiền đơn</span>
                        <strong><?= format_price($order['total_amount']) ?></strong>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include BASE_PATH . '/app/views/partials/footer.php'; ?>
