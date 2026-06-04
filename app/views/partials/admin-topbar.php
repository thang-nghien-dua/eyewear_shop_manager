<?php
$adminPageTitle = $adminPageTitle ?? 'Dashboard';
$adminPageSubtitle = $adminPageSubtitle ?? 'Bảng điều khiển quản trị';
$adminPrimaryAction = $adminPrimaryAction ?? null;
$adminSecondaryAction = $adminSecondaryAction ?? null;
?>
<header class="admin-topbar">
    <div>
        <span class="admin-topbar-kicker">ADMIN PANEL</span>
        <h1><?= e($adminPageTitle) ?></h1>
        <p><?= e($adminPageSubtitle) ?></p>
    </div>

    <div class="admin-topbar-actions">
        <?php if (is_array($adminSecondaryAction) && !empty($adminSecondaryAction['href']) && !empty($adminSecondaryAction['label'])): ?>
            <a href="<?= e((string) $adminSecondaryAction['href']) ?>" class="btn btn-secondary">
                <?php if (!empty($adminSecondaryAction['icon'])): ?>
                    <i class="<?= e((string) $adminSecondaryAction['icon']) ?> icon"></i>
                <?php endif; ?>
                <span><?= e((string) $adminSecondaryAction['label']) ?></span>
            </a>
        <?php else: ?>
            <a href="<?= e(APP_URL) ?>/admin/orders/index.php" class="btn btn-secondary">
                <i class="fi fi-rr-list-check icon"></i>
                <span>Xem đơn hàng</span>
            </a>
        <?php endif; ?>

        <?php if (is_array($adminPrimaryAction) && !empty($adminPrimaryAction['href']) && !empty($adminPrimaryAction['label'])): ?>
            <a href="<?= e((string) $adminPrimaryAction['href']) ?>" class="btn btn-primary">
                <?php if (!empty($adminPrimaryAction['icon'])): ?>
                    <i class="<?= e((string) $adminPrimaryAction['icon']) ?> icon"></i>
                <?php endif; ?>
                <span><?= e((string) $adminPrimaryAction['label']) ?></span>
            </a>
        <?php else: ?>
            <a href="<?= e(APP_URL) ?>/" class="btn btn-primary">
                <i class="fi fi-rr-home icon"></i>
                <span>Về storefront</span>
            </a>
        <?php endif; ?>
    </div>
</header>
