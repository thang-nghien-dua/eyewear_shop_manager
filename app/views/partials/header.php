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
          <a href="<?= e(APP_URL) ?>/products.php" class="nav-menu-link <?= is_active_nav('/products.php') ? 'active' : '' ?>">Cửa hàng</a>
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

        <div class="nav-menu-item"><a href="<?= e(APP_URL) ?>/sale.php" class="nav-menu-link <?= is_active_nav('/sale.php') ? 'active' : '' ?>">Sale</a></div>
        <div class="nav-menu-item"><a href="#contact" class="nav-menu-link">Liên hệ</a></div>
        <?php if ($user && is_admin_user()): ?>
          <div class="nav-menu-item"><a href="<?= e(APP_URL) ?>/admin/" class="nav-menu-link">Admin</a></div>
        <?php endif; ?>
      </div>

      <div class="nav-icons">
        <!-- Search toggle button -->
        <button class="nav-icon-btn nav-icon-desktop" id="searchToggleBtn" aria-label="Tìm kiếm" onclick="toggleSearchOverlay()">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
        </button>
        <a class="nav-icon-btn nav-icon-link nav-icon-desktop" href="<?= e($user ? APP_URL . '/profile.php' : APP_URL . '/login.php') ?>" aria-label="Tài khoản">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
        </a>
        <a class="nav-icon-btn nav-icon-link" href="<?= e(APP_URL) ?>/cart.php" aria-label="Giỏ hàng">
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
        <!-- Mobile search -->
        <div class="mobile-menu-item" style="padding:.75rem 1.25rem;">
          <form action="<?= e(APP_URL) ?>/products.php" method="get" style="display:flex;gap:.5rem;">
            <input type="text" name="keyword" placeholder="Tìm kiếm sản phẩm..." value="<?= e($_GET['keyword'] ?? '') ?>"
              style="flex:1;min-height:40px;border:1.5px solid #dbe4e7;border-radius:8px;padding:0 .85rem;font-size:.9rem;outline:none;">
            <button type="submit" style="min-height:40px;padding:0 1rem;background:#1a2e4a;color:#fff;border:none;border-radius:8px;cursor:pointer;">
              <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/><path d="m21 21-4.3-4.3" stroke="currentColor" stroke-width="2"/></svg>
            </button>
          </form>
        </div>
        <div class="mobile-menu-item"><a href="<?= e(APP_URL) ?>/" class="mobile-menu-link">Trang chủ</a></div>
        <div class="mobile-menu-item"><a href="<?= e(APP_URL) ?>/products.php" class="mobile-menu-link">Cửa hàng</a></div>
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

<!-- ===== Search Overlay ===== -->
<div id="searchOverlay" style="
  display:none; position:fixed; inset:0; z-index:9998;
  background:rgba(15,23,42,.55); backdrop-filter:blur(4px);
  align-items:flex-start; justify-content:center; padding-top:80px;
" onclick="if(event.target===this) closeSearchOverlay()">
  <div style="
    width:min(700px,94vw); background:#fff; border-radius:14px;
    box-shadow:0 20px 60px rgba(0,0,0,.22); overflow:hidden;
  ">
    <form action="<?= e(APP_URL) ?>/products.php" method="get" id="globalSearchForm">
      <div style="display:flex; align-items:center; gap:0; border-bottom:1px solid #e8ecef;">
        <label for="globalSearchInput" style="padding:0 1rem; color:#9aa3a6; flex:0 0 auto;">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8" stroke="#9aa3a6" stroke-width="2"/><path d="m21 21-4.3-4.3" stroke="#9aa3a6" stroke-width="2"/></svg>
        </label>
        <input id="globalSearchInput" name="keyword" type="text"
          placeholder="Tìm kiếm sản phẩm, thương hiệu, danh mục..."
          value="<?= e($_GET['keyword'] ?? '') ?>"
          autocomplete="off"
          style="flex:1;min-height:58px;border:none;outline:none;font-size:1.05rem;color:#2b3437;background:transparent;padding:0 .5rem;">
        <button type="button" onclick="closeSearchOverlay()" title="Đóng"
          style="padding:0 1.25rem;min-height:58px;border:none;background:transparent;cursor:pointer;color:#9aa3a6;font-size:1.3rem;line-height:1;">✕</button>
      </div>
      <!-- Quick filter chips -->
      <div style="display:flex;flex-wrap:wrap;gap:.4rem;padding:.75rem 1rem;background:#f8f9fa;border-bottom:1px solid #eef0f2;">
        <span style="font-size:.75rem;color:#9aa3a6;font-weight:700;align-self:center;margin-right:.25rem;">Nhanh:</span>
        <?php foreach ($menuParents as $parent): ?>
          <a href="<?= e(lumina_nav_category_url($parent)) ?>" style="display:inline-flex;align-items:center;height:28px;padding:0 .75rem;border-radius:999px;background:#e9eff2;color:#2b3437;font-size:.8rem;font-weight:700;text-decoration:none;transition:.15s;" onmouseover="this.style.background='#1a2e4a';this.style.color='#fff'" onmouseout="this.style.background='#e9eff2';this.style.color='#2b3437'"><?= e($parent['name']) ?></a>
        <?php endforeach; ?>
        <a href="<?= e(APP_URL) ?>/sale.php" style="display:inline-flex;align-items:center;height:28px;padding:0 .75rem;border-radius:999px;background:#fff7e6;color:#d4880a;font-size:.8rem;font-weight:700;text-decoration:none;border:1px solid #fce8b3;">🏷 Sale</a>
      </div>
      <div style="padding:.75rem 1rem; display:flex; justify-content:flex-end;">
        <button type="submit" style="height:40px;padding:0 1.5rem;background:#1a2e4a;color:#f5b700;border:none;border-radius:8px;font-size:.9rem;font-weight:800;cursor:pointer;letter-spacing:.04em;">
          Tìm kiếm
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleSearchOverlay() {
  var overlay = document.getElementById('searchOverlay');
  var isOpen = overlay.style.display === 'flex';
  if (isOpen) {
    closeSearchOverlay();
  } else {
    overlay.style.display = 'flex';
    setTimeout(function() { document.getElementById('globalSearchInput').focus(); }, 50);
  }
}
function closeSearchOverlay() {
  document.getElementById('searchOverlay').style.display = 'none';
}
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeSearchOverlay();
  // Ctrl+K or / shortcut
  if ((e.ctrlKey && e.key === 'k') || (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA')) {
    e.preventDefault();
    toggleSearchOverlay();
  }
});
</script>
