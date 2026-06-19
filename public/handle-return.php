<?php
/**
 * handle-return.php
 * Nhận POST từ form đổi trả / bảo hành trong order-detail.php
 * Chỉ chấp nhận POST request, luôn redirect về lại order-detail sau khi xử lý.
 */

require_once __DIR__ . '/../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

// Chỉ xử lý POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/orders.php');
    exit;
}

$orderCode = trim((string) ($_POST['order_code'] ?? ''));
$backUrl   = APP_URL . '/order-detail.php?code=' . urlencode($orderCode);

// Phải đăng nhập
$user = current_user();
if (!$user) {
    add_flash('warning', 'Bạn cần đăng nhập để gửi yêu cầu đổi trả.');
    header('Location: ' . APP_URL . '/login.php?redirect=' . urlencode('/order-detail.php?code=' . $orderCode));
    exit;
}

if ($orderCode === '') {
    add_flash('warning', 'Mã đơn hàng không hợp lệ.');
    header('Location: ' . APP_URL . '/orders.php');
    exit;
}

$db = Database::connect();

// Admin/manager xem được mọi đơn; customer chỉ được đơn của mình
$isStaff = in_array($user['role_name'] ?? '', ['admin', 'manager', 'sales', 'operations'], true);
if ($isStaff) {
    $stmt = $db->prepare('SELECT * FROM orders WHERE order_code = :code LIMIT 1');
    $stmt->execute(['code' => $orderCode]);
} else {
    $stmt = $db->prepare('SELECT * FROM orders WHERE order_code = :code AND user_id = :uid LIMIT 1');
    $stmt->execute(['code' => $orderCode, 'uid' => $user['id']]);
}
$order = $stmt->fetch();

if (!$order) {
    add_flash('warning', 'Không tìm thấy đơn hàng hoặc đơn hàng không thuộc về bạn.');
    header('Location: ' . $backUrl);
    exit;
}

if ($order['status'] !== 'completed') {
    add_flash('warning', 'Chỉ đơn hàng đã Hoàn tất mới được gửi yêu cầu đổi trả / bảo hành.');
    header('Location: ' . $backUrl);
    exit;
}

// Kiểm tra đã có yêu cầu chưa
$chk = $db->prepare('SELECT id FROM return_requests WHERE order_id = :oid LIMIT 1');
$chk->execute(['oid' => $order['id']]);
if ($chk->fetch()) {
    add_flash('warning', 'Đơn hàng này đã có yêu cầu đổi trả rồi. Không thể gửi thêm.');
    header('Location: ' . $backUrl);
    exit;
}

// Validate input
$reqType = trim((string) ($_POST['request_type'] ?? ''));
$reason  = trim((string) ($_POST['reason'] ?? ''));
$errors  = [];

if (!in_array($reqType, ['return', 'exchange', 'warranty', 'refund'], true)) {
    $errors[] = 'Loại yêu cầu không hợp lệ.';
}
if ($reason === '') {
    $errors[] = 'Vui lòng nhập lý do chi tiết.';
}

if ($errors !== []) {
    // Lưu lỗi vào session để hiển thị trên order-detail
    $_SESSION['return_form_errors'] = $errors;
    $_SESSION['return_form_data']   = ['request_type' => $reqType, 'reason' => $reason];
    header('Location: ' . $backUrl . '&ret=1');
    exit;
}

// Insert vào DB
$ins = $db->prepare('
    INSERT INTO return_requests (order_id, user_id, request_type, reason, status)
    VALUES (:order_id, :user_id, :request_type, :reason, "pending")
');
$ins->execute([
    'order_id'     => $order['id'],
    'user_id'      => $user['id'],
    'request_type' => $reqType,
    'reason'       => $reason,
]);

add_flash('success', 'Gửi yêu cầu đổi trả / bảo hành thành công! Nhân viên sẽ liên hệ bạn sớm nhất.');
header('Location: ' . $backUrl . '&ret=ok');
exit;
