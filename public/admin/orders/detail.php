<?php

require_once __DIR__ . '/../../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

require_staff();

$db = Database::connect();

$orderId = max(0, (int) ($_GET['id'] ?? 0));
if ($orderId <= 0) {
    header('Location: ' . APP_URL . '/admin/orders/index.php?error=' . urlencode('Thiếu mã đơn hàng.'));
    exit;
}

$currentUser = auth_user();

// Xử lý Xét duyệt đơn kính thuốc
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_prescription') {
    $rxId = (int) ($_POST['prescription_id'] ?? 0);
    $rxType = trim((string) ($_POST['prescription_type'] ?? ''));
    $newRxStatus = trim((string) ($_POST['prescription_status'] ?? ''));
    $rxNote = trim((string) ($_POST['prescription_note'] ?? ''));
    $orderStatus = trim((string) ($_POST['order_status'] ?? ''));

    if (in_array($newRxStatus, ['approved', 'rejected', 'needs_clarification'], true)) {
        if ($rxType === 'standard' && $rxId > 0) {
            $updateRx = $db->prepare('
                UPDATE prescriptions
                SET verification_status = :status, verified_by = :uid, verified_at = NOW(), note = CONCAT(COALESCE(note, ""), "\n[Xét duyệt] ", :note)
                WHERE id = :id
            ');
            $updateRx->execute([
                'status' => $newRxStatus,
                'uid' => $currentUser['id'],
                'note' => $rxNote,
                'id' => $rxId
            ]);
        }

        $logMsg = "Xét duyệt đơn kính: " . e(match ($newRxStatus) {
            'approved' => 'Đã duyệt thông số',
            'rejected' => 'Bị từ chối',
            'needs_clarification' => 'Cần làm rõ thêm',
            default => $newRxStatus
        }) . ". Ghi chú: " . $rxNote;

        $insertLog = $db->prepare('
            INSERT INTO order_status_logs (order_id, changed_by, old_status, new_status, note)
            VALUES (:order_id, :uid, :status, :status, :note)
        ');
        $insertLog->execute([
            'order_id' => $orderId,
            'uid' => $currentUser['id'],
            'status' => $orderStatus,
            'note' => $logMsg
        ]);

        if ($newRxStatus === 'approved') {
            $db->prepare('UPDATE orders SET status = "confirmed" WHERE id = :id')->execute(['id' => $orderId]);
            $insertLog->execute([
                'order_id' => $orderId,
                'uid' => $currentUser['id'],
                'status' => 'confirmed',
                'note' => 'Đơn hàng tự động chuyển sang Đã xác nhận sau khi duyệt đơn kính.'
            ]);
        }

        header('Location: ' . APP_URL . '/admin/orders/detail.php?id=' . $orderId . '&updated=1');
        exit;
    }
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

$prescription = null;
$prescriptionType = null; // 'standard' or 'wallet'

if (!empty($order['prescription_id'])) {
    $rxStmt = $db->prepare('SELECT * FROM prescriptions WHERE id = :id LIMIT 1');
    $rxStmt->execute(['id' => $order['prescription_id']]);
    $prescription = $rxStmt->fetch();
    $prescriptionType = 'standard';
} elseif (!empty($order['prescription_wallet_id'])) {
    $rxStmt = $db->prepare('SELECT * FROM customer_prescriptions WHERE id = :id LIMIT 1');
    $rxStmt->execute(['id' => $order['prescription_wallet_id']]);
    $prescription = $rxStmt->fetch();
    $prescriptionType = 'wallet';
}

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
    <?php
    $_sRole = (function_exists('auth_user') ? auth_user() : null)['role_name'] ?? '';
    if (in_array($_sRole, ['manager', 'sales', 'operations'], true)) {
        include BASE_PATH . '/app/views/partials/staff-sidebar.php';
    } else {
        include BASE_PATH . '/app/views/partials/admin-sidebar.php';
    }
    ?>

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
                                <strong><?= e(order_type_label((string) $order['order_type'])) ?></strong>
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

                    <?php if ($prescription): ?>
                        <div class="admin-panel" style="margin-top: 1.5rem;">
                            <div class="admin-panel-head">
                                <div>
                                    <span class="admin-panel-kicker">Prescription review</span>
                                    <h2>Thông số đơn kính thuốc</h2>
                                    <p>Loại hồ sơ: <strong><?= $prescriptionType === 'wallet' ? 'Ví đơn kính cá nhân' : 'Đơn kính tải lên' ?></strong></p>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                                <!-- Mắt phải (OD) -->
                                <div style="background: #f8fafc; padding: 1.25rem; border-radius: 10px; border: 1.5px solid #edf2f7;">
                                    <h3 style="margin-top: 0; color: #1a2e4a; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;">👁️ Mắt Phải (OD)</h3>
                                    <table style="width: 100%; font-size: 0.9rem;">
                                        <tr>
                                            <td style="padding: 4px 0; color: #718096;">Cầu (SPH):</td>
                                            <td style="font-weight: 700; text-align: right;"><?= isset($prescription['od_sphere']) ? sprintf('%+.2f', $prescription['od_sphere']) : (isset($prescription['right_sphere']) ? sprintf('%+.2f', $prescription['right_sphere']) : '—') ?></td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 4px 0; color: #718096;">Trụ (CYL):</td>
                                            <td style="font-weight: 700; text-align: right;"><?= isset($prescription['od_cylinder']) ? sprintf('%+.2f', $prescription['od_cylinder']) : (isset($prescription['right_cylinder']) ? sprintf('%+.2f', $prescription['right_cylinder']) : '—') ?></td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 4px 0; color: #718096;">Trục (AXIS):</td>
                                            <td style="font-weight: 700; text-align: right;"><?= isset($prescription['od_axis']) ? $prescription['od_axis'] . '°' : (isset($prescription['right_axis']) ? $prescription['right_axis'] . '°' : '—') ?></td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 4px 0; color: #718096;">Cộng (ADD):</td>
                                            <td style="font-weight: 700; text-align: right;"><?= isset($prescription['od_addition']) ? sprintf('%+.2f', $prescription['od_addition']) : (isset($prescription['right_addition']) ? sprintf('%+.2f', $prescription['right_addition']) : '—') ?></td>
                                        </tr>
                                    </table>
                                </div>

                                <!-- Mắt trái (OS) -->
                                <div style="background: #f8fafc; padding: 1.25rem; border-radius: 10px; border: 1.5px solid #edf2f7;">
                                    <h3 style="margin-top: 0; color: #1a2e4a; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;">👁️ Mắt Trái (OS)</h3>
                                    <table style="width: 100%; font-size: 0.9rem;">
                                        <tr>
                                            <td style="padding: 4px 0; color: #718096;">Cầu (SPH):</td>
                                            <td style="font-weight: 700; text-align: right;"><?= isset($prescription['os_sphere']) ? sprintf('%+.2f', $prescription['os_sphere']) : (isset($prescription['left_sphere']) ? sprintf('%+.2f', $prescription['left_sphere']) : '—') ?></td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 4px 0; color: #718096;">Trụ (CYL):</td>
                                            <td style="font-weight: 700; text-align: right;"><?= isset($prescription['os_cylinder']) ? sprintf('%+.2f', $prescription['os_cylinder']) : (isset($prescription['left_cylinder']) ? sprintf('%+.2f', $prescription['left_cylinder']) : '—') ?></td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 4px 0; color: #718096;">Trục (AXIS):</td>
                                            <td style="font-weight: 700; text-align: right;"><?= isset($prescription['os_axis']) ? $prescription['os_axis'] . '°' : (isset($prescription['left_axis']) ? $prescription['left_axis'] . '°' : '—') ?></td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 4px 0; color: #718096;">Cộng (ADD):</td>
                                            <td style="font-weight: 700; text-align: right;"><?= isset($prescription['os_addition']) ? sprintf('%+.2f', $prescription['os_addition']) : (isset($prescription['left_addition']) ? sprintf('%+.2f', $prescription['left_addition']) : '—') ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <div style="background: #f8fafc; padding: 1.25rem; border-radius: 10px; border: 1.5px solid #edf2f7; margin-bottom: 1.5rem;">
                                <h3 style="margin-top: 0; color: #1a2e4a; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;">📏 Khoảng cách đồng tử (PD)</h3>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.9rem;">
                                    <div>PD Nhìn xa: <strong><?= e($prescription['pd_distance'] ?? '—') ?> mm</strong></div>
                                    <div>PD Nhìn gần: <strong><?= e($prescription['pd_near'] ?? '—') ?> mm</strong></div>
                                    <?php if (isset($prescription['pd_right'])): ?>
                                        <div>PD Mắt Phải: <strong><?= e($prescription['pd_right']) ?> mm</strong></div>
                                        <div>PD Mắt Trái: <strong><?= e($prescription['pd_left']) ?> mm</strong></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Form xét duyệt -->
                            <div style="background: #fffdf5; border: 1.5px solid #ffe8cc; padding: 1.5rem; border-radius: 12px;">
                                <h3 style="margin-top: 0; color: #b25e00;">Xét duyệt đơn kính thuốc</h3>
                                <form method="post" style="display: flex; flex-direction: column; gap: 1rem;">
                                    <input type="hidden" name="action" value="verify_prescription">
                                    <input type="hidden" name="prescription_id" value="<?= (int) $prescription['id'] ?>">
                                    <input type="hidden" name="prescription_type" value="<?= e($prescriptionType) ?>">
                                    <input type="hidden" name="order_status" value="<?= e($order['status']) ?>">

                                    <div class="form-field">
                                        <label for="prescription_status">Trạng thái xét duyệt</label>
                                        <select id="prescription_status" name="prescription_status" required style="padding: 0.5rem; border-radius: 6px; border: 1.5px solid #dbe4e7; width: 100%;">
                                            <option value="approved" <?= ($prescription['verification_status'] ?? '') === 'approved' ? 'selected' : '' ?>>Duyệt - Thông số chính xác</option>
                                            <option value="rejected" <?= ($prescription['verification_status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Không duyệt - Hủy đơn kính</option>
                                            <option value="needs_clarification" <?= ($prescription['verification_status'] ?? '') === 'needs_clarification' ? 'selected' : '' ?>>Cần làm rõ - Đang liên hệ khách hàng</option>
                                        </select>
                                    </div>

                                    <div class="form-field">
                                        <label for="prescription_note">Ghi chú xét duyệt</label>
                                        <textarea id="prescription_note" name="prescription_note" rows="3" placeholder="Nhập ghi chú phản hồi về đơn kính thuốc của khách hàng..." style="padding: 0.5rem; border-radius: 6px; border: 1.5px solid #dbe4e7; font-family: inherit; width: 100%;"></textarea>
                                    </div>

                                    <button class="btn btn-primary" type="submit" style="align-self: flex-start; padding: 0.5rem 2rem;">Lưu kết quả duyệt</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

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
                    <?php if (!empty($order['cancel_requested']) && $order['status'] !== 'cancelled'): ?>
                        <div class="admin-panel" style="border: 2px solid #ef4444; background: #fef2f2; margin-bottom: 1.5rem;">
                            <div class="admin-panel-head compact">
                                <div>
                                    <h2 style="color: #b91c1c; margin:0;">⚠️ Yêu cầu hủy đơn</h2>
                                </div>
                            </div>
                            <p style="margin-top: 0.5rem; font-size: 0.9rem; color: #991b1b;">
                                Khách hàng đã gửi yêu cầu hủy đơn hàng này.
                                <?php if (!empty($order['cancel_reason'])): ?>
                                    <br><br><strong>Lý do:</strong> <?= e($order['cancel_reason']) ?>
                                <?php endif; ?>
                            </p>
                            <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                                <form action="<?= e(APP_URL) ?>/admin/orders/update-status.php" method="post" style="flex:1;">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <input type="hidden" name="action_type" value="approve_cancel">
                                    <button type="submit" class="btn btn-primary" style="background: #ef4444; border-color: #ef4444; width: 100%; justify-content:center;">Chấp nhận hủy</button>
                                </form>
                                <form action="<?= e(APP_URL) ?>/admin/orders/update-status.php" method="post" style="flex:1;">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <input type="hidden" name="action_type" value="reject_cancel">
                                    <button type="submit" class="btn btn-secondary" style="width: 100%; justify-content:center;">Từ chối</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

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
