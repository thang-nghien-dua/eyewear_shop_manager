<?php
$adminActive = $adminActive ?? '';
$currentUser = function_exists('auth_user') ? auth_user() : null;
?>
<aside class="admin-sidebar">
    <div class="admin-sidebar-brand">
        <a class="brand-mark" href="<?= e(APP_URL) ?>/admin/">
            <span class="brand-icon"><i class="fi fi-rr-apps icon"></i></span>
            <span class="brand-text">
                <strong>LUMIA</strong>
                <small style="color: #a0aec0; display: block; font-weight: 500; margin-top: 2px; font-size: 0.78rem; text-transform: none;"><?= e($currentUser['full_name'] ?? 'Tài khoản Admin') ?></small>
            </span>
        </a>
    </div>

    <nav class="admin-nav">
        <a class="admin-nav-link <?= $adminActive === 'dashboard' ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/"><i class="fi fi-rr-dashboard icon"></i><span>Dashboard</span></a>
        <a class="admin-nav-link <?= $adminActive === 'reports' ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/reports/index.php"><i class="fi fi-rr-chart-histogram icon"></i><span>Báo cáo doanh thu</span></a>
        <a class="admin-nav-link <?= $adminActive === 'orders' ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/orders/index.php"><i class="fi fi-rr-receipt icon"></i><span>Đơn hàng</span></a>
        <a class="admin-nav-link <?= $adminActive === 'products' ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/products/index.php"><i class="fi fi-rr-box-open-full icon"></i><span>Sản phẩm</span></a>
        <a class="admin-nav-link <?= $adminActive === 'categories' ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/categories/index.php"><i class="fi fi-rr-apps-sort icon"></i><span>Danh mục</span></a>
        <a class="admin-nav-link <?= $adminActive === 'reviews' ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/reviews/index.php">
            <i class="fi fi-rr-star icon"></i>
            <span>Đánh giá</span>
        </a>
        <a class="admin-nav-link <?= $adminActive === 'return_requests' ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/return-requests/index.php">
            <i class="fi fi-rr-undo icon"></i>
            <span>Đổi trả / BH</span>
        </a>
        <?php if (is_admin()): ?>
        <a class="admin-nav-link <?= $adminActive === 'users' ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/users/index.php">
            <i class="fi fi-rr-users icon"></i>
            <span>Tài khoản / Phân quyền</span>
        </a>
        <?php endif; ?>
        <a class="admin-nav-link" href="<?= e(APP_URL) ?>/"><i class="fi fi-rr-shop icon"></i><span>Storefront</span></a>
    </nav>

    <div class="admin-sidebar-foot">
        <div class="admin-support-card">
            <span class="admin-support-icon"><i class="fi fi-rr-settings icon"></i></span>
            <div>
                <strong>LUMIA</strong>
                <p style="margin: 0; font-size: 0.8rem; color: #4a5568;"><?= e($currentUser['full_name'] ?? 'Tài khoản Admin') ?></p>
            </div>
        </div>
    </div>
</aside>
