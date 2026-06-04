<?php

require_once __DIR__ . '/../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

$db = Database::connect();

$input = [
    'order_code' => trim((string) ($_GET['order_code'] ?? '')),
    'email' => trim((string) ($_GET['email'] ?? '')),
    'phone' => trim((string) ($_GET['phone'] ?? '')),
];

$recentOrders = [];
$searchResults = [];
$searchErrors = [];
$searched = $input['order_code'] !== '' || $input['email'] !== '' || $input['phone'] !== '';
$currentUser = current_user();

$baseOrderSelect = "SELECT o.id, o.order_code, o.order_type, o.status, o.customer_name, o.customer_email, o.customer_phone,
                           o.total_amount, o.payment_method, o.payment_status, o.created_at,
                           COUNT(oi.id) AS items_count
                    FROM orders o
                    LEFT JOIN order_items oi ON oi.order_id = o.id";

if ($currentUser && !empty($currentUser['id'])) {
    $recentStmt = $db->prepare(
        $baseOrderSelect . "
         WHERE o.user_id = :user_id
         GROUP BY o.id
         ORDER BY o.created_at DESC"
    );
    $recentStmt->execute([
        ':user_id' => (int) $currentUser['id'],
    ]);
    $recentOrders = $recentStmt->fetchAll();
} else {
    $recentCodes = recent_order_codes();
    if ($recentCodes !== []) {
        $placeholders = implode(',', array_fill(0, count($recentCodes), '?'));
        $recentStmt = $db->prepare(
            $baseOrderSelect . "
             WHERE o.order_code IN ($placeholders)
             GROUP BY o.id
             ORDER BY o.created_at DESC"
        );
        $recentStmt->execute($recentCodes);
        $recentOrders = $recentStmt->fetchAll();

        foreach ($recentOrders as $order) {
            grant_order_access((string) $order['order_code']);
        }
    }
}

if ($searched) {
    if ($currentUser && !empty($currentUser['id'])) {
        $sql = $baseOrderSelect . "
            WHERE o.user_id = :user_id
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT 20";

        $searchStmt = $db->prepare($sql);
        $searchStmt->execute([
            ':user_id' => (int) $currentUser['id'],
        ]);
        $searchResults = $searchStmt->fetchAll();
    } else {
        if ($input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $searchErrors[] = 'Email tra cứu không hợp lệ.';
        }

        if ($input['order_code'] === '' && ($input['email'] === '' || $input['phone'] === '')) {
            $searchErrors[] = 'Bạn hãy nhập mã đơn, hoặc nhập đồng thời email và số điện thoại để tra cứu.';
        }

        if ($searchErrors === []) {
            $conditions = [];
            $params = [];

            if ($input['order_code'] !== '') {
                $conditions[] = 'o.order_code LIKE :order_code';
                $params['order_code'] = '%' . $input['order_code'] . '%';
            }

            if ($input['email'] !== '') {
                $conditions[] = 'o.customer_email = :email';
                $params['email'] = $input['email'];
            }

            if ($input['phone'] !== '') {
                $conditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(o.customer_phone, ' ', ''), '.', ''), '-', ''), '(', ''), ')', '') = :phone";
                $params['phone'] = normalize_phone($input['phone']);
            }

            $sql = $baseOrderSelect;
            if ($conditions !== []) {
                $sql .= ' WHERE ' . implode(' AND ', $conditions);
            }
            $sql .= ' GROUP BY o.id ORDER BY o.created_at DESC LIMIT 20';

            $searchStmt = $db->prepare($sql);
            $searchStmt->execute($params);
            $searchResults = $searchStmt->fetchAll();

            foreach ($searchResults as $order) {
                grant_order_access((string) $order['order_code']);
            }
        }
    }
}

$pageTitle = 'Tra cứu đơn hàng';
$pageDescription = 'Xem lịch sử đơn hàng và trạng thái xử lý tại LUMINA';
$headerKeyword = '';

include BASE_PATH . '/app/views/partials/head.php';
include BASE_PATH . '/app/views/partials/header.php';
?>

<main class="page-section">
    <div class="container orders-page">
        <section class="orders-search-card">
            <div class="section-head">
                <div>
                    <h1>Lịch sử đơn hàng</h1>
                    <p>Tra cứu đơn bằng mã đơn, hoặc bằng email và số điện thoại đã dùng khi checkout.</p>
                </div>
                <a href="<?= e(APP_URL) ?>/products.php" class="back-link">
                    <i class="fi fi-rr-angle-left icon"></i>
                    Tiếp tục mua sắm
                </a>
            </div>

            <form method="GET" class="orders-search-grid">
                <div class="form-field">
                    <label for="order_code">Mã đơn hàng</label>
                    <input id="order_code" name="order_code" type="text" value="<?= e($input['order_code']) ?>" placeholder="Ví dụ: LM260420105142138">
                </div>
                <div class="form-field">
                    <label for="email">Email đặt hàng</label>
                    <input id="email" name="email" type="email" value="<?= e($input['email']) ?>" placeholder="ban@example.com">
                </div>
                <div class="form-field">
                    <label for="phone">Số điện thoại</label>
                    <input id="phone" name="phone" type="text" value="<?= e($input['phone']) ?>" placeholder="0901234567">
                </div>
                <div class="form-field">
                    <button class="btn-primary btn-block" type="submit">
                        <i class="fi fi-rr-search icon"></i>
                        Tra cứu đơn
                    </button>
                </div>
            </form>

            <div class="orders-summary-strip">
                <div class="order-summary-box">
                    <span>Đơn gần đây</span>
                    <strong><?= count($recentOrders) ?></strong>
                </div>
                <div class="order-summary-box">
                    <span>Kết quả tra cứu</span>
                    <strong><?= count($searchResults) ?></strong>
                </div>
                <div class="order-summary-box">
                    <span>Tổng đã truy cập</span>
                    <strong><?= count(array_unique(array_merge(array_column($recentOrders, 'order_code'), array_column($searchResults, 'order_code')))) ?></strong>
                </div>
            </div>

            <?php if ($searchErrors !== []): ?>
                <div class="alert warning" style="margin-top:16px;">
                    <ul class="form-error-list">
                        <?php foreach ($searchErrors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($recentOrders !== []): ?>
            <section class="orders-list-card">
                <div class="section-heading-row compact">
                    <h2>Đơn gần đây của bạn</h2>
                    <p>Các đơn vừa tạo trong phiên làm việc hiện tại.</p>
                </div>
                <div class="order-history-list">
                    <?php foreach ($recentOrders as $order): ?>
                        <article class="order-history-card">
                            <div class="order-history-head">
                                <div>
                                    <p class="eyebrow">Đơn gần đây</p>
                                    <h3 class="order-history-code"><?= e($order['order_code']) ?></h3>
                                    <p class="order-history-sub">Khách hàng: <strong><?= e($order['customer_name']) ?></strong></p>
                                </div>
                                <div class="order-tag-row">
                                    <span class="status-pill <?= e(order_status_class($order['status'])) ?>"><?= e(order_status_label($order['status'])) ?></span>
                                    <span class="order-type-pill"><?= e(order_type_label($order['order_type'])) ?></span>
                                </div>
                            </div>
                            <div class="order-history-stats">
                                <div class="order-history-stat"><span>Sản phẩm</span><strong><?= (int) $order['items_count'] ?></strong></div>
                                <div class="order-history-stat"><span>Tổng tiền</span><strong><?= format_price($order['total_amount']) ?></strong></div>
                                <div class="order-history-stat"><span>Thanh toán</span><strong><?= e(payment_status_label($order['payment_status'])) ?></strong></div>
                                <div class="order-history-stat"><span>Ngày tạo</span><strong><?= e(date('d/m/Y H:i', strtotime((string) $order['created_at']))) ?></strong></div>
                            </div>
                            <div class="order-history-foot">
                                <p class="order-mini-meta">
                                    <span><i class="fi fi-rr-envelope icon icon-sm"></i> <?= e($order['customer_email']) ?></span>
                                    <span><i class="fi fi-rr-phone-call icon icon-sm"></i> <?= e($order['customer_phone']) ?></span>
                                </p>
                                <a class="btn-secondary" href="<?= e(APP_URL) ?>/order-detail.php?code=<?= urlencode((string) $order['order_code']) ?>">Xem chi tiết</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($searched): ?>
            <section class="orders-list-card">
                <div class="section-heading-row compact">
                    <h2>Kết quả tra cứu</h2>
                    <p><?= count($searchResults) > 0 ? 'Chọn một đơn để xem chi tiết và theo dõi trạng thái.' : 'Không tìm thấy đơn hàng phù hợp.' ?></p>
                </div>

                <?php if ($searchResults === []): ?>
                    <div class="order-empty-card">
                        <div>
                            <div class="empty-cart-icon" style="margin:0 auto 12px;"><i class="fi fi-rr-search icon icon-lg"></i></div>
                            <h3>Chưa tìm thấy đơn hàng</h3>
                            <p>Kiểm tra lại mã đơn, email hoặc số điện thoại đã dùng khi đặt hàng.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="order-history-list">
                        <?php foreach ($searchResults as $order): ?>
                            <article class="order-history-card">
                                <div class="order-history-head">
                                    <div>
                                        <p class="eyebrow">Kết quả tra cứu</p>
                                        <h3 class="order-history-code"><?= e($order['order_code']) ?></h3>
                                        <p class="order-history-sub">Khách hàng: <strong><?= e($order['customer_name']) ?></strong></p>
                                    </div>
                                    <div class="order-tag-row">
                                        <span class="status-pill <?= e(order_status_class($order['status'])) ?>"><?= e(order_status_label($order['status'])) ?></span>
                                        <span class="order-type-pill"><?= e(order_type_label($order['order_type'])) ?></span>
                                    </div>
                                </div>
                                <div class="order-history-stats">
                                    <div class="order-history-stat"><span>Sản phẩm</span><strong><?= (int) $order['items_count'] ?></strong></div>
                                    <div class="order-history-stat"><span>Tổng tiền</span><strong><?= format_price($order['total_amount']) ?></strong></div>
                                    <div class="order-history-stat"><span>Thanh toán</span><strong><?= e(payment_method_label($order['payment_method'])) ?></strong></div>
                                    <div class="order-history-stat"><span>Ngày tạo</span><strong><?= e(date('d/m/Y H:i', strtotime((string) $order['created_at']))) ?></strong></div>
                                </div>
                                <div class="order-history-foot">
                                    <p class="order-mini-meta">
                                        <span><i class="fi fi-rr-envelope icon icon-sm"></i> <?= e($order['customer_email']) ?></span>
                                        <span><i class="fi fi-rr-phone-call icon icon-sm"></i> <?= e($order['customer_phone']) ?></span>
                                    </p>
                                    <a class="btn-secondary" href="<?= e(APP_URL) ?>/order-detail.php?code=<?= urlencode((string) $order['order_code']) ?>">Xem chi tiết</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php elseif ($recentOrders === []): ?>
            <section class="orders-list-card">
                <div class="order-empty-card">
                    <div>
                        <div class="empty-cart-icon" style="margin:0 auto 12px;"><i class="fi fi-rr-receipt icon icon-lg"></i></div>
                        <h3>Bạn chưa có đơn nào trong phiên hiện tại</h3>
                        <p>Sau khi checkout thành công, mã đơn mới sẽ hiện ở đây để bạn theo dõi dễ hơn.</p>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php include BASE_PATH . '/app/views/partials/footer.php'; ?>
