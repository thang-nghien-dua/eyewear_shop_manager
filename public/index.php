<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';

$db = Database::connect();
$placeholderImage = APP_URL . '/assets/images/placeholder-glasses.png';

function lumina_img(?string $url, string $placeholder): string
{
    $url = trim((string) $url);
    if ($url === '') return $placeholder;
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '/')) return $url;
    return APP_URL . '/' . ltrim($url, '/');
}

$productStmt = $db->query(
    "SELECT p.id, p.name, p.slug, p.brand, p.default_price, p.compare_at_price, p.thumbnail,
            p.short_description, p.shape, p.material, c.name AS category_name, c.slug AS category_slug
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.status = 'active'
     ORDER BY p.id DESC
     LIMIT 8"
);
$products = $productStmt->fetchAll();

$heroProduct = $products[0] ?? null;
$categoryStmt = $db->query(
    "SELECT c.id, c.name, c.slug, c.description, COUNT(p.id) AS products_count
     FROM categories c
     LEFT JOIN categories child ON child.parent_id = c.id AND child.is_active = 1
     LEFT JOIN products p ON p.status = 'active' AND (p.category_id = c.id OR p.category_id = child.id)
     WHERE c.is_active = 1 AND c.parent_id IS NULL
     GROUP BY c.id
     ORDER BY c.sort_order ASC, c.id ASC
     LIMIT 3"
);
$categories = $categoryStmt->fetchAll();

$pageTitle = APP_NAME . ' - Bộ sưu tập kính mắt premium';
$pageDescription = 'LUMINA - Gọng kính, kính mát và tròng kính với giao diện premium.';
require_once BASE_PATH . '/app/views/partials/head.php';
require_once BASE_PATH . '/app/views/partials/header.php';
?>
<?php if ($message = get_flash('success')): ?>
  <div class="source-flash"><div class="source-flash-inner"><?= e($message) ?></div></div>
<?php endif; ?>

<section class="hero hero-source-dark">
  <div class="hero-container">
    <div class="hero-content">
      <span class="hero-label">Bộ sưu tập mới <?= date('Y') ?></span>
      <h1 class="hero-title">Nhìn Thế Giới<br>Với Phong Cách</h1>
      <p class="hero-description">Khám phá bộ sưu tập kính mắt cao cấp của LUMINA. Thiết kế tinh tế, công nghệ tròng kính hiện đại và trải nghiệm mua kính trực tuyến rõ ràng, nhanh, dễ quản lý.</p>
      <a href="<?= e(APP_URL) ?>/products.php" class="hero-btn">Khám phá ngay</a>
      <a href="<?= e(APP_URL) ?>/products.php?category=gong-kinh" class="hero-btn secondary">Xem gọng kính</a>
      <div class="source-feature-row">
        <span>✓ Kính mắt chính hãng</span>
        <span>✓ Tư vấn miễn phí</span>
        <span>✓ Có pre-order & prescription</span>
      </div>
    </div>
    <?php if ($heroProduct): ?>
      <div class="hero-image-wrapper">
        <img src="<?= e(lumina_img($heroProduct['thumbnail'] ?? '', $placeholderImage)) ?>" alt="<?= e($heroProduct['name']) ?>" class="hero-image" loading="eager">
      </div>
    <?php endif; ?>
  </div>
</section>



<?php require_once BASE_PATH . '/app/views/partials/footer.php'; ?>
