<?php
require_once __DIR__ . '/../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

$db = Database::connect();
$orderCode = trim((string) ($_GET['code'] ?? ''));
$action = trim((string) ($_GET['action'] ?? ''));

if ($orderCode === '') {
    header('Location: ' . APP_URL . '/orders.php');
    exit;
}

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
$accessGranted = false;

if ($currentUser && !empty($currentUser['id'])) {
    $isAdmin = (($currentUser['role_name'] ?? '') === 'admin');
    if ($isAdmin || (int) $order['user_id'] === (int) $currentUser['id']) {
        $accessGranted = true;
    }
} else {
    $accessGranted = customer_can_access_order($orderCode);
}

if (!$accessGranted) {
    header('Location: ' . APP_URL . '/order-detail.php?code=' . urlencode($orderCode));
    exit;
}

$pageTitle = 'Hủy đơn hàng ' . $orderCode;
$pageDescription = 'Nhập lý do hủy đơn hàng ' . $orderCode;
$headerKeyword = '';

include BASE_PATH . '/app/views/partials/head.php';
include BASE_PATH . '/app/views/partials/header.php';
?>

<main class="page-section">
    <div class="container" style="max-width: 600px; margin: 0 auto;">
        <section class="order-detail-card" style="margin-top: 2rem;">
            <div class="section-heading-row compact">
                <h2>Hủy đơn hàng <?= e($order['order_code']) ?></h2>
                <a href="<?= e(APP_URL) ?>/order-detail.php?code=<?= e($orderCode) ?>" class="back-link">
                    <i class="fi fi-rr-angle-left icon"></i>
                    Quay lại đơn hàng
                </a>
            </div>

            <form action="<?= e(APP_URL) ?>/cancel-order-action.php" method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn thực hiện hành động này?');" style="margin-top: 1.5rem;">
                <input type="hidden" name="order_code" value="<?= e($order['order_code']) ?>">
                <input type="hidden" name="action" value="<?= e($action) ?>">
                
                <div style="margin-bottom: 1.5rem;">
                    <label for="cancel_reason" style="display:block; margin-bottom: 0.5rem; font-size: 0.95rem; font-weight: 600; color: #1a2e4a;">Lý do hủy (tùy chọn)</label>
                    <textarea id="cancel_reason" name="cancel_reason" rows="4" style="width: 100%; border: 1.5px solid #dbe4e7; border-radius: 6px; padding: 0.75rem; font-size: 0.9rem;" placeholder="Vui lòng cho chúng tôi biết lý do bạn muốn hủy đơn hàng này..."></textarea>
                </div>

                <?php if ($action === 'cancel'): ?>
                    <button type="submit" class="btn btn-primary" style="width: 100%; background: #ef4444; border-color: #ef4444; justify-content: center; padding: 0.75rem; font-size: 1rem;">Xác nhận hủy đơn hàng</button>
                <?php else: ?>
                    <button type="submit" class="btn btn-secondary" style="width: 100%; justify-content: center; padding: 0.75rem; font-size: 1rem;">Gửi yêu cầu hủy đơn</button>
                    <p style="font-size: 0.85rem; color: #718096; margin-top: 0.75rem; text-align: center;">Đơn hàng đã qua khâu xác nhận nên cần cửa hàng duyệt yêu cầu hủy.</p>
                <?php endif; ?>
            </form>
        </section>
    </div>
</main>

<?php include BASE_PATH . '/app/views/partials/footer.php'; ?>
