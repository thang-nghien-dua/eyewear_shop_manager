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


<section class="home-intro">
            <div class="container">
                <div class="intro-list">
                  <div class="intro-list-wrapper">
                  <div class="intro-item">
                 <div class="icon">
                       <img loading="lazy" src="https://hmkeyewear.com/wp-content/uploads/2023/11/support.svg" alt="">
                       </div>
                    <div class="content">
                  <p>Vệ Sinh Kính Miễn Phí</p>
                     <span>tại toàn bộ hệ thống mắt kính HMK</span>
                       </div>
           </div>
                                                                                                         <div class="intro-item">
                 <div class="icon">
                       <img loading="lazy" src="https://hmkeyewear.com/wp-content/uploads/2023/10/cashback.svg" alt="">
                   </div>
                          <div class="content">
                            <p>Giao Hàng Nhanh Chóng</p>
                             <span>chỉ từ 2 ngày trên toàn quốc</span>
                           </div>
                     </div>
                               </div>
                              <div class="intro-item">
                                    <div class="icon">
                                        <img loading="lazy" src="https://hmkeyewear.com/wp-content/uploads/2023/10/renew.svg" alt="">
                                    </div>
                                    <div class="content">
                                        <p>Thu Cũ Đổi Mới</p>
                                        <span>trợ giá lên đến 600.000đ</span>
                                    </div>
                                </div>
                                                                                                                            <div class="intro-item">
                                    <div class="icon">
                                        <img loading="lazy" src="https://hmkeyewear.com/wp-content/uploads/2023/10/eye-measurement.svg" alt="">
                                    </div>
                                    <div class="content">
                                        <p>Hỗ Trợ Đo Mắt</p>
                                        <span>tại toàn bộ hệ thống mắt kính HMK</span>
                                    </div>
                                </div>
                                                    </div>
                                                                                    </div>
            </div>
        </section>







<section class="category-cards-section">
  <div class="catalog-container">
    <h2 class="section-heading">Khám phá theo danh mục</h2>
    <div class="category-cards-grid">
      <?php foreach ($categories as $category): ?>
        <a class="category-card source-card-link" href="<?= e(APP_URL) ?>/products.php?category=<?= e($category['slug']) ?>" style="background: linear-gradient(135deg, #111827 0%, #475569 100%)">
          <h3 class="category-card-name"><?= e($category['name']) ?></h3>
          <p class="category-card-description"><?= e($category['description'] ?: 'Khám phá bộ sưu tập theo nhu cầu của bạn.') ?></p>
          <div class="category-card-cta">Xem <?= (int) $category['products_count'] ?> sản phẩm
            <svg class="category-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5l7 7-7 7"></path></svg>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="products">
  <div class="products-container">
    <div class="products-header">
      <h2 class="products-title">Mẫu mới tại LUMINA</h2>
      <a href="<?= e(APP_URL) ?>/products.php" class="products-view-all">Xem tất cả</a>
    </div>
    <div class="products-grid">
      <?php foreach ($products as $product): ?>
        <a href="<?= e(APP_URL) ?>/product-detail.php?id=<?= (int) $product['id'] ?>" class="product-card">
          <div class="product-image-wrapper">
            <?php if (!empty($product['compare_at_price']) && (float) $product['compare_at_price'] > (float) $product['default_price']): ?>
              <div class="product-badge">Sale</div>
            <?php endif; ?>
            <img src="<?= e(lumina_img($product['thumbnail'], $placeholderImage)) ?>" alt="<?= e($product['name']) ?>" class="product-image" loading="lazy">
          </div>
          <div class="product-info source-product-info-stacked">
            <div class="product-category"><?= e($product['category_name'] ?: 'LUMINA') ?></div>
            <div class="product-name"><?= e($product['name']) ?></div>
            <div class="source-price-line">
              <div class="product-price"><?= e(format_price($product['default_price'])) ?></div>
              <?php if (!empty($product['compare_at_price']) && (float) $product['compare_at_price'] > (float) $product['default_price']): ?>
                <span class="source-old-price"><?= e(format_price($product['compare_at_price'])) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="collections">
  <div class="collections-container">
    <a href="<?= e(APP_URL) ?>/products.php?category=kinh-mat" class="collection-card">
      <div class="collection-image" style="background: linear-gradient(135deg, #0f172a 0%, #334155 100%);"></div>
      <div class="collection-content">
        <h3 class="collection-title">Kính mát</h3>
        <span class="collection-link">Shop Collection</span>
      </div>
    </a>
    <a href="<?= e(APP_URL) ?>/products.php?category=trong-kinh" class="collection-card">
      <div class="collection-image" style="background: linear-gradient(135deg, #171717 0%, #737373 100%);"></div>
      <div class="collection-content">
        <h3 class="collection-title">Tròng kính</h3>
        <span class="collection-link">Shop Collection</span>
      </div>
    </a>
  </div>
</section>

<?php require_once BASE_PATH . '/app/views/partials/footer.php'; ?>
