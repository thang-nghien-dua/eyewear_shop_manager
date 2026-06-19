<?php

require_once __DIR__ . '/../../../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

// Chỉ tài khoản admin thực sự mới được vào trang này
admin_only();
if (!is_admin()) {
    add_flash('warning', 'Bạn không có quyền truy cập chức năng Phân quyền.');
    redirect_to('/admin/');
}

$db = Database::connect();
$currentUser = auth_user();

// Xử lý POST update role hoặc status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $targetUserId = (int) ($_POST['user_id'] ?? 0);

    if ($targetUserId > 0) {
        // Không cho phép tự đổi quyền hoặc khóa tài khoản của chính mình
        if ($targetUserId === (int) $currentUser['id']) {
            add_flash('warning', 'Không thể tự thay đổi vai trò hoặc trạng thái của chính bạn.');
        } else {
            if ($action === 'update_role') {
                $newRoleId = (int) ($_POST['role_id'] ?? 0);
                if ($newRoleId > 0) {
                    $stmt = $db->prepare('UPDATE users SET role_id = :role_id WHERE id = :id');
                    $stmt->execute(['role_id' => $newRoleId, 'id' => $targetUserId]);
                    add_flash('success', 'Cập nhật vai trò người dùng thành công.');
                }
            } elseif ($action === 'update_status') {
                $newStatus = trim((string) ($_POST['status'] ?? ''));
                if (in_array($newStatus, ['active', 'inactive', 'banned'], true)) {
                    $stmt = $db->prepare('UPDATE users SET status = :status WHERE id = :id');
                    $stmt->execute(['status' => $newStatus, 'id' => $targetUserId]);
                    add_flash('success', 'Cập nhật trạng thái người dùng thành công.');
                }
            } elseif ($action === 'delete') {
                try {
                    $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
                    $stmt->execute(['id' => $targetUserId]);
                    add_flash('success', 'Xóa tài khoản người dùng thành công.');
                } catch (\PDOException $e) {
                    add_flash('warning', 'Không thể xóa tài khoản này vì đã có dữ liệu liên quan (giao dịch, đơn hàng...). Khuyên bạn nên khóa hoặc cấm tài khoản.');
                }
            }
        }
    }
    redirect_to('/admin/users/index.php');
}

// Lấy danh sách Roles để hiển thị trong select
$roles = $db->query('SELECT * FROM roles ORDER BY id ASC')->fetchAll();

// Bộ lọc & Tìm kiếm
$keyword = trim((string) ($_GET['keyword'] ?? ''));
$filterRoleId = (int) ($_GET['role_id'] ?? 0);
$filterStatus = trim((string) ($_GET['status'] ?? ''));

$where = [];
$params = [];

if ($keyword !== '') {
    $where[] = '(u.full_name LIKE :keyword_name OR u.email LIKE :keyword_email OR u.phone LIKE :keyword_phone)';
    $keywordVal = '%' . $keyword . '%';
    $params[':keyword_name'] = $keywordVal;
    $params[':keyword_email'] = $keywordVal;
    $params[':keyword_phone'] = $keywordVal;
}

if ($filterRoleId > 0) {
    $where[] = 'u.role_id = :role_id';
    $params[':role_id'] = $filterRoleId;
}

if ($filterStatus !== '') {
    $where[] = 'u.status = :status';
    $params[':status'] = $filterStatus;
}

$sql = '
    SELECT u.*, r.name AS role_name, r.description AS role_desc
    FROM users u
    JOIN roles r ON r.id = u.role_id
';

if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY u.id DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Admin - Quản lý tài khoản & Phân quyền';
$pageDescription = 'Xem danh sách và thiết lập phân quyền tài khoản.';
$adminActive = 'users';

include BASE_PATH . '/app/views/partials/admin-head.php';
?>
<div class="admin-shell">
    <?php include BASE_PATH . '/app/views/partials/admin-sidebar.php'; ?>

    <main class="admin-main">
        <?php include BASE_PATH . '/app/views/partials/admin-topbar.php'; ?>

        <section class="admin-dashboard">
            <div class="admin-hero-card">
                <div>
                    <span class="eyebrow">QUẢN TRỊ HỆ THỐNG</span>
                    <h1>Tài khoản & Phân quyền</h1>
                    <p>Phân chia vai trò (Admin, Manager, Sales, Operations, Customer) và quản lý trạng thái tài khoản nhân viên & khách hàng.</p>
                </div>
            </div>

            <?php if ($flash = get_flash('success')): ?>
                <div class="alert success"><?= e($flash) ?></div>
            <?php endif; ?>

            <?php if ($flash = get_flash('warning')): ?>
                <div class="alert warning"><?= e($flash) ?></div>
            <?php endif; ?>

            <!-- Bộ lọc -->
            <section class="admin-filter-card">
                <form method="get" class="form-grid admin-filter-grid" action="<?= e(APP_URL) ?>/admin/users/index.php">
                    <div class="form-field">
                        <label for="keyword">Tìm kiếm</label>
                        <input id="keyword" name="keyword" value="<?= e($keyword) ?>" placeholder="Tên, Email hoặc Số điện thoại...">
                    </div>

                    <div class="form-field">
                        <label for="role_id">Vai trò</label>
                        <select id="role_id" name="role_id">
                            <option value="">Tất cả vai trò</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= (int) $r['id'] ?>" <?= $filterRoleId === (int) $r['id'] ? 'selected' : '' ?>>
                                    <?= e(user_role_label($r['name'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="status">Trạng thái</label>
                        <select id="status" name="status">
                            <option value="">Tất cả trạng thái</option>
                            <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Hoạt động</option>
                            <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Tạm khóa</option>
                            <option value="banned" <?= $filterStatus === 'banned' ? 'selected' : '' ?>>Bị cấm</option>
                        </select>
                    </div>

                    <div class="form-field form-field-actions" style="display: flex; gap: 1rem; align-items: flex-end;">
                        <button class="btn-primary" type="submit"><i class="fi fi-rr-search icon icon-sm"></i> Lọc tài khoản</button>
                        <a class="btn-outline" href="<?= e(APP_URL) ?>/admin/users/index.php">Đặt lại</a>
                    </div>
                </form>
            </section>

            <!-- Bảng tài khoản -->
            <section class="admin-table-card">
                <div class="admin-card-head">
                    <div>
                        <h2>Danh sách người dùng</h2>
                        <p class="summary-note">Tổng số <?= count($users) ?> tài khoản khớp với bộ lọc.</p>
                    </div>
                </div>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Họ và tên</th>
                                <th>Email / SĐT</th>
                                <th>Vai trò hiện tại</th>
                                <th>Thay đổi vai trò</th>
                                <th>Trạng thái</th>
                                <th>Thay đổi trạng thái</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users === []): ?>
                                <tr><td colspan="8"><div class="empty-mini-card">Chưa có người dùng nào khớp với bộ lọc.</div></td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): ?>
                                    <?php $isSelf = ((int) $u['id'] === (int) $currentUser['id']); ?>
                                    <tr>
                                        <td>#<?= (int) $u['id'] ?></td>
                                        <td>
                                            <strong><?= e($u['full_name']) ?></strong>
                                            <?= $isSelf ? ' <span style="background:#1a2e4a; color:#f5b700; font-size:10px; padding:2px 6px; border-radius:99px; font-weight:bold;">BẠN</span>' : '' ?>
                                        </td>
                                        <td>
                                            <div><?= e($u['email']) ?></div>
                                            <small class="muted-small"><?= e($u['phone'] ?: 'Chưa cập nhật SĐT') ?></small>
                                        </td>
                                        <td>
                                            <span style="display:inline-block; background:#e9eff2; color:#1a2e4a; padding:0.25rem 0.75rem; border-radius:99px; font-size:0.8rem; font-weight:700;">
                                                <?= e(user_role_label($u['role_name'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($isSelf): ?>
                                                <span class="muted-small">Không thể tự chỉnh vai trò</span>
                                            <?php else: ?>
                                                <form method="post" style="display: flex; gap: 0.5rem; align-items: center;">
                                                    <input type="hidden" name="action" value="update_role">
                                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                    <select name="role_id" style="padding: 0.25rem 0.5rem; font-size: 0.85rem; border-radius: 6px;" onchange="this.form.submit()">
                                                        <?php foreach ($roles as $r): ?>
                                                            <option value="<?= (int) $r['id'] ?>" <?= (int) $u['role_id'] === (int) $r['id'] ? 'selected' : '' ?>>
                                                                <?= e(user_role_label($r['name'])) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-pill <?= $u['status'] === 'active' ? 'status-completed' : ($u['status'] === 'inactive' ? 'status-pending' : 'status-cancelled') ?>">
                                                <?= e($u['status'] === 'active' ? 'Hoạt động' : ($u['status'] === 'inactive' ? 'Tạm khóa' : 'Bị cấm')) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($isSelf): ?>
                                                <span class="muted-small">Không thể tự chỉnh trạng thái</span>
                                            <?php else: ?>
                                                <form method="post" style="display: flex; gap: 0.5rem; align-items: center;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                    <select name="status" style="padding: 0.25rem 0.5rem; font-size: 0.85rem; border-radius: 6px;" onchange="this.form.submit()">
                                                        <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>Hoạt động</option>
                                                        <option value="inactive" <?= $u['status'] === 'inactive' ? 'selected' : '' ?>>Tạm khóa</option>
                                                        <option value="banned" <?= $u['status'] === 'banned' ? 'selected' : '' ?>>Bị cấm</option>
                                                    </select>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($isSelf): ?>
                                                <span class="muted-small">—</span>
                                            <?php else: ?>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa tài khoản này không? Hành động này không thể hoàn tác.')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                    <button type="submit" class="btn btn-outline btn-sm" style="color: #dc2626; border-color: #dc2626; padding: 4px 10px; font-size: 0.78rem; font-weight: 700; cursor: pointer; border-radius: 6px; background: transparent; transition: all 0.2s;">🗑️ Xóa</button>
                                                </form>
                                            <?php endif; ?>
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
