<?php

require_once __DIR__ . '/../../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

admin_only(); // Phải là admin/manager/sales/operations mới vào được panel admin chung

$db = Database::connect();
$currentUser = auth_user();

// Xử lý cập nhật yêu cầu đổi trả/bảo hành
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $action = trim((string) ($_POST['action'] ?? ''));
    $resolutionNote = trim((string) ($_POST['resolution_note'] ?? ''));

    if ($requestId > 0) {
        $validStatuses = [
            'approve' => 'approved',
            'reject' => 'rejected',
            'receive' => 'received',
            'resolve' => 'resolved'
        ];

        if (isset($validStatuses[$action])) {
            $newStatus = $validStatuses[$action];
            $stmt = $db->prepare('
                UPDATE return_requests
                SET status = :status, resolution_note = :resolution_note, handled_by = :handled_by
                WHERE id = :id
            ');
            $stmt->execute([
                'status' => $newStatus,
                'resolution_note' => $resolutionNote,
                'handled_by' => $currentUser['id'],
                'id' => $requestId
            ]);

            // Khi admin duyệt yêu cầu (approved), cập nhật đơn hàng thành đơn hoàn, đã thanh toán, trạng thái chờ xác nhận
            if ($newStatus === 'approved') {
                $reqStmt = $db->prepare('SELECT order_id FROM return_requests WHERE id = :id');
                $reqStmt->execute(['id' => $requestId]);
                $req = $reqStmt->fetch();
                if ($req) {
                    $db->prepare('
                        UPDATE orders 
                        SET order_type = "return_order", 
                            status = "pending", 
                            payment_status = "paid" 
                        WHERE id = :order_id
                    ')->execute(['order_id' => $req['order_id']]);
                }
            }

            // Nếu hoàn tiền thành công, có thể tự động cập nhật trạng thái đơn hàng sang Refunded
            if ($newStatus === 'resolved') {
                $reqStmt = $db->prepare('SELECT order_id, request_type FROM return_requests WHERE id = :id');
                $reqStmt->execute(['id' => $requestId]);
                $req = $reqStmt->fetch();
                if ($req && $req['request_type'] === 'refund') {
                    // Cập nhật trạng thái đơn hàng và thanh toán
                    $db->prepare('UPDATE orders SET status = "refunded", payment_status = "refunded" WHERE id = :order_id')
                       ->execute(['order_id' => $req['order_id']]);
                }
            }

            add_flash('success', 'Đã cập nhật trạng thái yêu cầu thành công.');
        }
    }
    redirect_to('/admin/return-requests/index.php');
}

// Bộ lọc
$filterType = trim((string) ($_GET['type'] ?? ''));
$filterStatus = trim((string) ($_GET['status'] ?? ''));

$where = [];
$params = [];

if ($filterType !== '') {
    $where[] = 'rr.request_type = :type';
    $params[':type'] = $filterType;
}

if ($filterStatus !== '') {
    $where[] = 'rr.status = :status';
    $params[':status'] = $filterStatus;
}

$sql = '
    SELECT rr.*, o.order_code, o.id AS order_id_val, o.total_amount, u.full_name AS customer_name, u.email AS customer_email, h.full_name AS handler_name
    FROM return_requests rr
    JOIN orders o ON o.id = rr.order_id
    JOIN users u ON u.id = rr.user_id
    LEFT JOIN users h ON h.id = rr.handled_by
';

if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY rr.id DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$pageTitle = 'Admin - Quản lý đổi trả & bảo hành';
$pageDescription = 'Duyệt các yêu cầu đổi hàng, trả hàng hoàn tiền, bảo hành từ khách hàng.';
$adminActive = 'return_requests';

include BASE_PATH . '/app/views/partials/admin-head.php';
?>
<div class="admin-shell">
    <?php include BASE_PATH . '/app/views/partials/admin-sidebar.php'; ?>

    <main class="admin-main">
        <?php include BASE_PATH . '/app/views/partials/admin-topbar.php'; ?>

        <section class="admin-dashboard">
            <div class="admin-hero-card">
                <div>
                    <span class="eyebrow">CHĂM SÓC KHÁCH HÀNG</span>
                    <h1>Yêu cầu Đổi trả & Bảo hành</h1>
                    <p>Duyệt, xử lý và theo dõi các yêu cầu đổi trả, bảo hành hoặc hoàn tiền từ khách hàng gửi lên hệ thống.</p>
                </div>
            </div>

            <?php if ($flash = get_flash('success')): ?>
                <div class="alert success"><?= e($flash) ?></div>
            <?php endif; ?>

            <!-- Bộ lọc -->
            <section class="admin-filter-card">
                <form method="get" class="form-grid admin-filter-grid" action="<?= e(APP_URL) ?>/admin/return-requests/index.php">
                    <div class="form-field">
                        <label for="type">Loại yêu cầu</label>
                        <select id="type" name="type">
                            <option value="">Tất cả loại yêu cầu</option>
                            <option value="return" <?= $filterType === 'return' ? 'selected' : '' ?>>Đổi hàng mới</option>
                            <option value="exchange" <?= $filterType === 'exchange' ? 'selected' : '' ?>>Đổi sản phẩm lỗi</option>
                            <option value="warranty" <?= $filterType === 'warranty' ? 'selected' : '' ?>>Bảo hành</option>
                            <option value="refund" <?= $filterType === 'refund' ? 'selected' : '' ?>>Trả hàng hoàn tiền</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="status">Trạng thái xử lý</label>
                        <select id="status" name="status">
                            <option value="">Tất cả trạng thái</option>
                            <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Chờ duyệt</option>
                            <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Đã duyệt (Chờ gửi/nhận hàng)</option>
                            <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Bị từ chối</option>
                            <option value="received" <?= $filterStatus === 'received' ? 'selected' : '' ?>>Đã nhận sản phẩm</option>
                            <option value="resolved" <?= $filterStatus === 'resolved' ? 'selected' : '' ?>>Đã giải quyết</option>
                        </select>
                    </div>

                    <div class="form-field form-field-actions" style="display: flex; gap: 1rem; align-items: flex-end;">
                        <button class="btn-primary" type="submit"><i class="fi fi-rr-search icon icon-sm"></i> Lọc yêu cầu</button>
                        <a class="btn-outline" href="<?= e(APP_URL) ?>/admin/return-requests/index.php">Đặt lại</a>
                    </div>
                </form>
            </section>

            <!-- Bảng hiển thị yêu cầu -->
            <section class="admin-table-card">
                <div class="admin-card-head">
                    <div>
                        <h2>Danh sách yêu cầu</h2>
                        <p class="summary-note">Tìm thấy <?= count($requests) ?> yêu cầu đổi trả/bảo hành.</p>
                    </div>
                </div>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Mã yêu cầu</th>
                                <th>Đơn hàng</th>
                                <th>Khách hàng</th>
                                <th>Loại</th>
                                <th>Lý do gửi</th>
                                <th>Trạng thái</th>
                                <th>Xử lý / Ghi chú phản hồi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($requests === []): ?>
                                <tr><td colspan="7"><div class="empty-mini-card">Chưa có yêu cầu nào phù hợp.</div></td></tr>
                            <?php else: ?>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td>#<?= (int) $r['id'] ?></td>
                                        <td>
                                            <a href="<?= e(APP_URL) ?>/admin/orders/detail.php?id=<?= (int) $r['order_id_val'] ?>" style="font-weight: 700; color: #1a2e4a;">
                                                <?= e($r['order_code']) ?>
                                            </a>
                                            <div class="muted-small"><?= format_price($r['total_amount']) ?></div>
                                        </td>
                                        <td>
                                            <strong><?= e($r['customer_name']) ?></strong>
                                            <div class="muted-small"><?= e($r['customer_email']) ?></div>
                                        </td>
                                        <td>
                                            <span style="font-size: 0.8rem; font-weight: bold; background: #e9eff2; color: #1a2e4a; padding: 0.2rem 0.6rem; border-radius: 99px;">
                                                <?= e(match ($r['request_type']) {
                                                    'return' => 'Đổi hàng mới',
                                                    'exchange' => 'Đổi sản phẩm lỗi',
                                                    'warranty' => 'Bảo hành',
                                                    'refund' => 'Trả hàng hoàn tiền',
                                                    default => $r['request_type']
                                                }) ?>
                                            </span>
                                        </td>
                                         <td>
                                             <div style="max-width: 250px; font-size: 0.88rem; white-space: normal; word-break: break-word; margin-bottom: 0.5rem;">
                                                 <?= nl2br(e($r['reason'])) ?>
                                             </div>
                                             <?php if (!empty($r['images'])): ?>
                                                 <?php $imgs = json_decode($r['images'], true); ?>
                                                 <?php if (is_array($imgs) && $imgs !== []): ?>
                                                     <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; flex-wrap: wrap;">
                                                         <?php foreach ($imgs as $imgUrl): ?>
                                                             <a href="<?= e(APP_URL . $imgUrl) ?>" target="_blank" title="Xem ảnh lớn">
                                                                 <img src="<?= e(APP_URL . $imgUrl) ?>" alt="Minh chứng" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid #edf2f7; background: #fff; padding: 2px;">
                                                             </a>
                                                         <?php endforeach; ?>
                                                     </div>
                                                 <?php endif; ?>
                                             <?php endif; ?>
                                             <small class="muted-small">Ngày gửi: <?= e(date('d/m/Y H:i', strtotime($r['created_at']))) ?></small>
                                         </td>
                                        <td>
                                            <span class="status-pill <?= e(match($r['status']) {
                                                'pending' => 'status-pending',
                                                'approved', 'resolved' => 'status-completed',
                                                'rejected' => 'status-cancelled',
                                                default => 'status-processing'
                                            }) ?>">
                                                <?= e(match ($r['status']) {
                                                    'pending' => 'Chờ duyệt',
                                                    'approved' => 'Đã duyệt',
                                                    'rejected' => 'Bị từ chối',
                                                    'received' => 'Đã nhận hàng',
                                                    'resolved' => 'Đã giải quyết',
                                                    default => $r['status']
                                                }) ?>
                                            </span>
                                            <?php if ($r['handler_name']): ?>
                                                <div class="muted-small" style="margin-top: 4px;">Duyệt bởi: <?= e($r['handler_name']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="post" style="max-width: 280px; display: flex; flex-direction: column; gap: 0.5rem;">
                                                <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                                                <textarea name="resolution_note" placeholder="Ghi chú phản hồi cho khách..." rows="2" style="font-size: 0.85rem; padding: 0.4rem; border: 1px solid #dbe4e7; border-radius: 6px; resize: vertical;"><?= e($r['resolution_note'] ?: '') ?></textarea>
                                                
                                                <div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
                                                    <?php if ($r['status'] === 'pending'): ?>
                                                        <button type="submit" name="action" value="approve" class="btn btn-secondary btn-sm" style="font-size:0.75rem; padding:2px 6px;">Duyệt</button>
                                                        <button type="submit" name="action" value="reject" class="btn btn-outline btn-sm" style="font-size:0.75rem; padding:2px 6px; border-color:#dc2626; color:#dc2626;">Từ chối</button>
                                                    <?php elseif ($r['status'] === 'approved'): ?>
                                                        <button type="submit" name="action" value="receive" class="btn btn-secondary btn-sm" style="font-size:0.75rem; padding:2px 6px;">Đã nhận hàng gửi lại</button>
                                                    <?php elseif ($r['status'] === 'received'): ?>
                                                        <button type="submit" name="action" value="resolve" class="btn btn-primary btn-sm" style="font-size:0.75rem; padding:2px 6px;">Hoàn tất giải quyết</button>
                                                    <?php else: ?>
                                                        <button type="submit" name="action" value="resolve" class="btn btn-outline btn-sm" style="font-size:0.75rem; padding:2px 6px;">Cập nhật ghi chú</button>
                                                    <?php endif; ?>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>
</div>
</body>
</html>
