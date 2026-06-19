<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/helpers/functions.php';

header('Content-Type: application/json');

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

if ($secureHash !== $vnp_SecureHash) {
    echo json_encode(["RspCode" => "97", "Message" => "Invalid signature"]);
    exit;
}

$orderCode = $_GET['vnp_TxnRef'] ?? '';
$responseCode = $_GET['vnp_ResponseCode'] ?? '';
$vnp_Amount = $_GET['vnp_Amount'] ?? 0;
$vnp_TransactionNo = $_GET['vnp_TransactionNo'] ?? '';

$orderStmt = $db->prepare("SELECT * FROM orders WHERE order_code = :order_code LIMIT 1");
$orderStmt->execute(['order_code' => $orderCode]);
$order = $orderStmt->fetch();

if (!$order) {
    echo json_encode(["RspCode" => "01", "Message" => "Order not found"]);
    exit;
}

// Kiểm tra số tiền (VNPAY gửi số tiền nhân với 100)
$orderAmountInCents = (int)($order['total_amount'] * 100);
if ($orderAmountInCents !== (int)$vnp_Amount) {
    echo json_encode(["RspCode" => "04", "Message" => "Invalid amount"]);
    exit;
}

// Kiểm tra trạng thái đơn hàng (Đã thanh toán rồi hay chưa)
if ($order['payment_status'] === 'paid') {
    echo json_encode(["RspCode" => "02", "Message" => "Order already confirmed"]);
    exit;
}

try {
    $db->beginTransaction();

    if ($responseCode === '00') {
        // Cập nhật trạng thái thanh toán là Đã thanh toán (paid)
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
            'note' => 'IPN: Xác nhận thanh toán trực tuyến thành công qua VNPAY. Mã GD VNPAY: ' . $vnp_TransactionNo
        ]);
        
        $message = "Confirm Success";
    } else {
        // Nếu không thành công, có thể cập nhật trạng thái giao dịch thất bại
        $updateOrder = $db->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = :id");
        $updateOrder->execute(['id' => $order['id']]);
        
        $insertLog = $db->prepare(
            "INSERT INTO order_status_logs (order_id, changed_by, old_status, new_status, note) 
             VALUES (:order_id, null, :old_status, :new_status, :note)"
        );
        $insertLog->execute([
            'order_id' => $order['id'],
            'old_status' => $order['status'],
            'new_status' => $order['status'],
            'note' => 'IPN: Giao dịch thanh toán VNPAY không thành công. Mã lỗi: ' . $responseCode
        ]);

        $message = "Payment Failed";
    }

    $db->commit();
    echo json_encode(["RspCode" => "00", "Message" => $message]);
    exit;
} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(["RspCode" => "99", "Message" => "System error: " . $e->getMessage()]);
    exit;
}
