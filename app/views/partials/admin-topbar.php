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

<?php
$flashSuccess = get_flash('success');
$flashError = get_flash('error');
if ($flashSuccess || $flashError): ?>
    <div class="admin-flash-messages" style="padding: 1rem 2rem 0; margin-bottom: -1rem;">
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success" style="margin-bottom: 0.5rem; padding: 1rem; border-radius: 8px; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; background: #ecfdf5; border: 1px solid #a7f3d0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#047857" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <span style="color: #047857;"><?= e($flashSuccess) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-error" style="margin-bottom: 0.5rem; padding: 1rem; border-radius: 8px; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; background: #fef2f2; border: 1px solid #fecaca;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#b91c1c" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span style="color: #b91c1c;"><?= e($flashError) ?></span>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
