<?php
require_once BASE_PATH . '/app/helpers/functions.php';
$user = auth_user();
$dbForNav = null;
$navParents = [];
$navChildrenByParent = [];
try {
    $dbForNav = Database::connect();
    $stmt = $dbForNav->query("SELECT id, parent_id, name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    $allNavCategories = $stmt->fetchAll();
    foreach ($allNavCategories as $cat) {
        if ($cat['parent_id'] === null) {
            $navParents[(int) $cat['id']] = $cat;
        } else {
            $navChildrenByParent[(int) $cat['parent_id']][] = $cat;
        }
    }
} catch (Throwable $exception) {
    $navParents = [];
    $navChildrenByParent = [];
}

$preferredSlugs = ['gong-kinh', 'kinh-mat', 'trong-kinh'];
$menuParents = [];
foreach ($preferredSlugs as $slug) {
    foreach ($navParents as $parent) {
        if (($parent['slug'] ?? '') === $slug) {
            $menuParents[] = $parent;
            break;
        }
    }
}

function lumina_nav_category_url(array $category): string
{
    return APP_URL . '/products.php?category=' . urlencode((string) ($category['slug'] ?? $category['id']));
}
?>
<nav class="nav">
  <div class="nav-container">
    <div class="nav-content">
      <a href="<?= e(APP_URL) ?>/" class="nav-logo">LUMINA</a>

      <div class="nav-desktop-menu" id="desktop-menu">
        <div class="nav-menu-item">
          <a href="<?= e(APP_URL) ?>/" class="nav-menu-link <?= is_active_nav('/') || is_active_nav('/index.php') ? 'active' : '' ?>">Trang chủ</a>
        </div>
        <div class="nav-menu-item">
          <a href="<?= e(APP_URL) ?>/products.php" class="nav-menu-link <?= is_active_nav('/products.php') ? 'active' : '' ?>">Bộ sưu tập</a>
        </div>

        <?php foreach ($menuParents as $parent): ?>
          <?php $children = $navChildrenByParent[(int) $parent['id']] ?? []; ?>
          <div class="nav-menu-item">
            <a href="<?= e(lumina_nav_category_url($parent)) ?>" class="nav-menu-link">
              <span><?= e($parent['name']) ?></span>
              <?php if ($children): ?>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"></path></svg>
              <?php endif; ?>
            </a>
            <?php if ($children): ?>
              <div class="dropdown">
                <?php foreach ($children as $child): ?>
                  <div class="dropdown-item">
                    <a href="<?= e(lumina_nav_category_url($child)) ?>" class="dropdown-link">
                      <span><?= e($child['name']) ?></span>
                    </a>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <div class="nav-menu-item"><a href="#blog" class="nav-menu-link">Blog</a></div>
        <div class="nav-menu-item"><a href="#contact" class="nav-menu-link">Liên hệ</a></div>
        <?php if ($user && is_admin_user()): ?>
          <div class="nav-menu-item"><a href="<?= e(APP_URL) ?>/admin/" class="nav-menu-link">Admin</a></div>
        <?php endif; ?>
      </div>

      <div class="nav-icons">
        <a class="nav-icon-btn nav-icon-link nav-icon-desktop" href="<?= e(APP_URL) ?>/products.php" aria-label="Search">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
        </a>
        <a class="nav-icon-btn nav-icon-link nav-icon-desktop" href="<?= e($user ? APP_URL . '/profile.php' : APP_URL . '/login.php') ?>" aria-label="Account">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
        </a>
        <a class="nav-icon-btn nav-icon-link" href="<?= e(APP_URL) ?>/cart.php" aria-label="Shopping Cart">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
          <?php if (cart_count() > 0): ?><span class="cart-badge-source"><?= (int) cart_count() ?></span><?php endif; ?>
        </a>
        <button class="nav-hamburger" id="hamburger-btn" aria-label="Menu">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" x2="20" y1="12" y2="12"></line><line x1="4" x2="20" y1="6" y2="6"></line><line x1="4" x2="20" y1="18" y2="18"></line></svg>
        </button>
      </div>
    </div>

    <div class="mobile-menu" id="mobile-menu">
      <div id="mobile-menu-items">
        <div class="mobile-menu-item"><a href="<?= e(APP_URL) ?>/" class="mobile-menu-link">Trang chủ</a></div>
        <div class="mobile-menu-item"><a href="<?= e(APP_URL) ?>/products.php" class="mobile-menu-link">Bộ sưu tập</a></div>
        <?php foreach ($menuParents as $idx => $parent): ?>
          <?php $children = $navChildrenByParent[(int) $parent['id']] ?? []; $menuId = 'mobile-cat-' . (int) $parent['id']; ?>
          <div class="mobile-menu-item">
            <?php if ($children): ?>
              <button class="mobile-menu-link" data-toggle="<?= e($menuId) ?>">
                <span><?= e($parent['name']) ?></span>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 10 5 14 9"></polyline></svg>
              </button>
              <div class="mobile-submenu" id="<?= e($menuId) ?>">
                <a href="<?= e(lumina_nav_category_url($parent)) ?>" class="mobile-submenu-link">Tất cả <?= e($parent['name']) ?></a>
                <?php foreach ($children as $child): ?>
                  <a href="<?= e(lumina_nav_category_url($child)) ?>" class="mobile-submenu-link"><?= e($child['name']) ?></a>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <a href="<?= e(lumina_nav_category_url($parent)) ?>" class="mobile-menu-link"><?= e($parent['name']) ?></a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <div class="mobile-menu-item"><a href="<?= e(APP_URL) ?>/orders.php" class="mobile-menu-link">Đơn hàng</a></div>
        <?php if ($user): ?>
          <div class="mobile-menu-item"><a href="<?= e(APP_URL) ?>/profile.php" class="mobile-menu-link">Tài khoản</a></div>
          <div class="mobile-menu-item"><a href="<?= e(APP_URL) ?>/logout.php" class="mobile-menu-link">Đăng xuất</a></div>
        <?php else: ?>
          <div class="mobile-menu-item"><a href="<?= e(APP_URL) ?>/login.php" class="mobile-menu-link">Đăng nhập</a></div>
          <div class="mobile-menu-item"><a href="<?= e(APP_URL) ?>/register.php" class="mobile-menu-link">Đăng ký</a></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
