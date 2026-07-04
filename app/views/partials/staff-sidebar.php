<?php
$adminActive = $adminActive ?? '';
$currentUser = function_exists('auth_user') ? auth_user() : null;
$roleName = $currentUser['role_name'] ?? '';
$roleLabel = match($roleName) {
    'manager'    => 'Quản lý',
    'sales'      => 'Nhân viên bán hàng',
    'operations' => 'Nhân viên vận hành',
    default      => 'Nhân viên',
};
?>
<aside class="admin-sidebar">
    <div class="admin-sidebar-brand">
        <a class="brand-mark" href="<?= e(APP_URL) ?>/admin/orders/index.php">
            <span class="brand-icon"><i class="fi fi-rr-store-alt icon"></i></span>
            <span class="brand-text">
                <strong>LUMINA</strong>
                <small style="color: #a0aec0; display: block; font-weight: 500; margin-top: 2px; font-size: 0.78rem; text-transform: none;"><?= e($currentUser['full_name'] ?? 'Nhân viên') ?></small>
            </span>
        </a>
    </div>

    <!-- Role badge -->
    <div style="margin: 0 1rem 1rem; padding: 0.5rem 0.75rem; background: rgba(245, 183, 0, 0.12); border: 1px solid rgba(245, 183, 0, 0.3); border-radius: 8px; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fi fi-rr-id-badge icon" style="color: #f5b700; font-size: 0.85rem;"></i>
        <span style="font-size: 0.78rem; font-weight: 700; color: #f5b700;"><?= e($roleLabel) ?></span>
    </div>

    <nav class="admin-nav">
        <a class="admin-nav-link <?= $adminActive === 'orders' ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/orders/index.php">
            <i class="fi fi-rr-receipt icon"></i>
            <span>Đơn hàng</span>
        </a>
        <a class="admin-nav-link <?= $adminActive === 'return_requests' ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/return-requests/index.php">
            <i class="fi fi-rr-undo icon"></i>
            <span>Đổi trả / Bảo hành</span>
        </a>
        <a class="admin-nav-link" href="<?= e(APP_URL) ?>/">
            <i class="fi fi-rr-shop icon"></i>
            <span>Storefront</span>
        </a>
        <a class="admin-nav-link" href="<?= e(APP_URL) ?>/logout.php" style="margin-top: auto; color: #fc8181;">
            <i class="fi fi-rr-sign-out-alt icon"></i>
            <span>Đăng xuất</span>
        </a>
    </nav>

    <div class="admin-sidebar-foot">
        <div class="admin-support-card">
            <span class="admin-support-icon"><i class="fi fi-rr-user icon"></i></span>
            <div>
                <strong><?= e($currentUser['full_name'] ?? 'Nhân viên') ?></strong>
                <p style="margin: 0; font-size: 0.8rem; color: #4a5568;"><?= e($roleLabel) ?></p>
            </div>
        </div>
    </div>
</aside>
