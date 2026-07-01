<?php
require_once __DIR__ . '/../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

$db = Database::connect();
$currentUser = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$orderCode = trim((string) ($_POST['order_code'] ?? ''));
$action = trim((string) ($_POST['action'] ?? '')); // 'cancel' or 'request_cancel'
$reason = trim((string) ($_POST['cancel_reason'] ?? ''));

if ($orderCode === '' || !in_array($action, ['cancel', 'request_cancel'])) {
    add_flash('error', 'Yêu cầu không hợp lệ.');
    redirect_to('/orders.php');
}

// Lấy thông tin đơn hàng
$orderStmt = $db->prepare('SELECT id, user_id, status, payment_status, customer_email, customer_phone FROM orders WHERE order_code = :code LIMIT 1');
$orderStmt->execute(['code' => $orderCode]);
$order = $orderStmt->fetch();

if (!$order) {
    add_flash('error', 'Không tìm thấy đơn hàng.');
    redirect_to('/orders.php');
}

// Kiểm tra quyền (chỉ chủ đơn hoặc admin mới được thao tác)
$hasAccess = false;
if ($currentUser && ($currentUser['role_name'] === 'admin' || (int)$order['user_id'] === (int)$currentUser['id'])) {
    $hasAccess = true;
} else if (customer_can_access_order($orderCode)) {
    $hasAccess = true;
}

if (!$hasAccess) {
    add_flash('error', 'Bạn không có quyền thao tác trên đơn hàng này.');
    redirect_to('/orders.php');
}

$orderId = (int)$order['id'];
$actorName = $currentUser ? $currentUser['full_name'] : 'Khách hàng';

try {
    $db->beginTransaction();

    if ($action === 'cancel') {
        // Chỉ cho phép hủy nếu chưa thanh toán và đang ở trạng thái cho phép
        if ($order['payment_status'] !== 'unpaid' || !in_array($order['status'], ['pending', 'awaiting_stock', 'checking_prescription'])) {
            throw new Exception('Đơn hàng không thể hủy trực tiếp ở trạng thái hiện tại.');
        }

        // Cập nhật trạng thái hủy
        $updateStmt = $db->prepare('UPDATE orders SET status = "cancelled", updated_at = NOW() WHERE id = :id');
        $updateStmt->execute(['id' => $orderId]);

        // Ghi log
        $logStmt = $db->prepare('INSERT INTO order_status_logs (order_id, old_status, new_status, note, changed_by) VALUES (:id, :old, "cancelled", :note, :uid)');
        $logStmt->execute([
            'id' => $orderId,
            'old' => $order['status'],
            'note' => 'Khách hàng tự hủy đơn. Lý do: ' . ($reason ?: 'Không có'),
            'uid' => $currentUser['id'] ?? null
        ]);

        add_flash('success', 'Đã hủy đơn hàng thành công.');

    } elseif ($action === 'request_cancel') {
        // Chỉ cho phép yêu cầu hủy nếu đang ở trạng thái confirmed hoặc processing (tùy cài đặt)
        if (in_array($order['status'], ['completed', 'cancelled', 'refunded', 'shipping'])) {
            throw new Exception('Không thể yêu cầu hủy đơn hàng ở trạng thái hiện tại.');
        }

        $updateStmt = $db->prepare('UPDATE orders SET cancel_requested = 1, cancel_reason = :reason, updated_at = NOW() WHERE id = :id');
        $updateStmt->execute(['reason' => $reason, 'id' => $orderId]);

        // Ghi log (chỉ là ghi chú, trạng thái đơn không đổi)
        $logStmt = $db->prepare('INSERT INTO order_status_logs (order_id, old_status, new_status, note, changed_by) VALUES (:id, :old, :old, :note, :uid)');
        $logStmt->execute([
            'id' => $orderId,
            'old' => $order['status'],
            'note' => 'Khách hàng gửi yêu cầu hủy đơn. Lý do: ' . ($reason ?: 'Không có'),
            'uid' => $currentUser['id'] ?? null
        ]);

        add_flash('success', 'Đã gửi yêu cầu hủy đơn. Cửa hàng sẽ xác nhận sớm nhất.');
    }

    $db->commit();
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    add_flash('error', $e->getMessage());
}

redirect_to('/order-detail.php?code=' . urlencode($orderCode));
