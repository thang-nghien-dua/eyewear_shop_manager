<?php
$adminActive = $adminActive ?? '';
?>
<aside class="admin-sidebar">
    <div class="admin-sidebar-brand">
        <a class="brand-mark" href="<?= e(APP_URL) ?>/admin/">
            <span class="brand-icon"><i class="fi fi-rr-apps icon"></i></span>
            <span class="brand-text">
                <strong>LUMINA Admin</strong>
                <small>Eyewear Control Center</small>
            </span>
        </a>
    </div>

    <nav class="admin-nav">
        <a class="admin-nav-link <?= $adminActive === 'dashboard' ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/"><i class="fi fi-rr-dashboard icon"></i><span>Dashboard</span></a>
        <a class="admin-nav-link <?= $adminActive === 'orders' ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/orders/index.php"><i class="fi fi-rr-receipt icon"></i><span>Đơn hàng</span></a>
        <a class="admin-nav-link <?= $adminActive === 'products' ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/products/index.php"><i class="fi fi-rr-box-open-full icon"></i><span>Sản phẩm</span></a>
        <a class="admin-nav-link <?= $adminActive === 'categories' ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/categories/index.php"><i class="fi fi-rr-apps-sort icon"></i><span>Danh mục</span></a>
        <a class="admin-nav-link" href="<?= e(APP_URL) ?>/"><i class="fi fi-rr-shop icon"></i><span>Storefront</span></a>
    </nav>

    <div class="admin-sidebar-foot">
        <div class="admin-support-card">
            <span class="admin-support-icon"><i class="fi fi-rr-settings icon"></i></span>
            <div>
                <strong>Quản trị nhanh</strong>
                <p>Theo dõi đơn mới, danh mục và catalog chỉ trong một nơi.</p>
            </div>
        </div>
    </div>
</aside>
