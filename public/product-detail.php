<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';

$db = Database::connect();
$placeholderImage = APP_URL . '/assets/images/placeholder-glasses.png';

function lumina_detail_img(?string $url, string $placeholder): string
{
    $url = trim((string) $url);
    if ($url === '') return $placeholder;
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '/')) return $url;
    return APP_URL . '/' . ltrim($url, '/');
}

$id   = isset($_GET['id'])   ? (int) $_GET['id']         : 0;
$slug = trim((string) ($_GET['slug'] ?? ''));

if ($id > 0) {
    $stmt = $db->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = :id AND p.status = 'active' LIMIT 1");
    $stmt->execute(['id' => $id]);
} else {
    $stmt = $db->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.slug = :slug AND p.status = 'active' LIMIT 1");
    $stmt->execute(['slug' => $slug]);
}
$product = $stmt->fetch();
if (!$product) {
    http_response_code(404);
    $pageTitle = 'Không tìm thấy sản phẩm';
    require_once BASE_PATH . '/app/views/partials/head.php';
    require_once BASE_PATH . '/app/views/partials/header.php';
    echo '<section class="empty-state"><p>Không tìm thấy sản phẩm.</p></section>';
    require_once BASE_PATH . '/app/views/partials/footer.php';
    exit;
}

$relatedStmt = $db->prepare("SELECT p.id, p.name, p.default_price, p.thumbnail, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.status = 'active' AND p.category_id = :category_id AND p.id <> :id ORDER BY p.id DESC LIMIT 4");
$relatedStmt->execute(['category_id' => $product['category_id'], 'id' => $product['id']]);
$related = $relatedStmt->fetchAll();

$variantsStmt = $db->prepare("
    SELECT id, sku, color, size_label, width_mm, bridge_mm, temple_length_mm, price, stock_quantity, is_preorder_allowed, image_override
    FROM product_variants
    WHERE product_id = :product_id AND is_active = 1
    ORDER BY id ASC
");
$variantsStmt->execute(['product_id' => $product['id']]);
$variants = $variantsStmt->fetchAll();

// Lấy gallery ảnh của sản phẩm
$imagesStmt = $db->prepare("SELECT image_url, alt_text FROM product_images WHERE product_id = :product_id ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 6");
$imagesStmt->execute(['product_id' => $product['id']]);
$galleryImages = $imagesStmt->fetchAll();

// Nếu không có gallery, dùng thumbnail
if (empty($galleryImages)) {
    $galleryImages = [['image_url' => $product['thumbnail'], 'alt_text' => $product['name']]];
}

// ── Reviews data ────────────────────────────────────────────────────
$reviews        = [];
$reviewStats    = ['avg' => 0, 'count' => 0, 'dist' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]];
$canReview      = false;
$hasReviewed    = false;
$eligibleOrder  = null;

try {
    $revStmt = $db->prepare("
        SELECT r.*, u.full_name AS reviewer_name
        FROM product_reviews r
        INNER JOIN users u ON u.id = r.user_id
        WHERE r.product_id = :pid AND r.status = 'approved'
        ORDER BY r.created_at DESC
        LIMIT 20
    ");
    $revStmt->execute(['pid' => $product['id']]);
    $reviews = $revStmt->fetchAll();

    $statStmt = $db->prepare("
        SELECT AVG(rating) AS avg_rating, COUNT(*) AS total,
               SUM(rating = 5) AS r5, SUM(rating = 4) AS r4,
               SUM(rating = 3) AS r3, SUM(rating = 2) AS r2,
               SUM(rating = 1) AS r1
        FROM product_reviews
        WHERE product_id = :pid AND status = 'approved'
    ");
    $statStmt->execute(['pid' => $product['id']]);
    $statRow = $statStmt->fetch();
    if ($statRow && $statRow['total'] > 0) {
        $reviewStats = [
            'avg'   => round((float) $statRow['avg_rating'], 1),
            'count' => (int) $statRow['total'],
            'dist'  => [
                5 => (int) $statRow['r5'],
                4 => (int) $statRow['r4'],
                3 => (int) $statRow['r3'],
                2 => (int) $statRow['r2'],
                1 => (int) $statRow['r1'],
            ],
        ];
    }

    if (is_logged_in()) {
        $uid = auth_user()['id'];
        // Đã đánh giá chưa?
        $doneStmt = $db->prepare('SELECT id FROM product_reviews WHERE user_id = :uid AND product_id = :pid');
        $doneStmt->execute(['uid' => $uid, 'pid' => $product['id']]);
        $hasReviewed = (bool) $doneStmt->fetchColumn();

        // Có đơn completed chứa sản phẩm này không?
        if (!$hasReviewed) {
            $eligStmt = $db->prepare("
                SELECT o.id
                FROM orders o
                INNER JOIN order_items oi ON oi.order_id = o.id
                INNER JOIN product_variants pv ON pv.id = oi.product_variant_id
                WHERE o.user_id = :uid AND pv.product_id = :pid AND o.status = 'completed'
                LIMIT 1
            ");
            $eligStmt->execute(['uid' => $uid, 'pid' => $product['id']]);
            $eligibleOrder = $eligStmt->fetchColumn();
            $canReview = (bool) $eligibleOrder;
        }
    }
} catch (\Throwable $e) { /* Bảng chưa tồn tại */ }

$reviewErrors  = flash_get('review_errors', []);
$reviewOld     = flash_get('review_old', []);
$reviewSuccess = get_flash('success'); // Bước 15: thông báo gửi đánh giá thành công

$pageTitle = $product['name'] . ' - ' . APP_NAME;
$pageDescription = $product['short_description'] ?: 'Chi tiết sản phẩm LUMINA.';
require_once BASE_PATH . '/app/views/partials/head.php';
require_once BASE_PATH . '/app/views/partials/header.php';
?>

<style>
/* =========================================================
   Product Detail Page — LUMINA redesign
   ========================================================= */
.pd-page {
  background: #f8f9fa;
  padding: 0;
}

.pd-container {
  max-width: 1280px;
  margin: 0 auto;
  padding: clamp(1.5rem, 4vw, 3rem) clamp(1rem, 3vw, 2.5rem);
}

/* Breadcrumb */
.pd-breadcrumb {
  display: flex;
  align-items: center;
  gap: .5rem;
  margin-bottom: 2rem;
  font-size: .82rem;
  color: #737c7f;
}
.pd-breadcrumb a { color: #737c7f; transition: color .15s; }
.pd-breadcrumb a:hover { color: #1a2e4a; }
.pd-breadcrumb span { color: #2b3437; font-weight: 600; }

/* Main layout: image | info | services */
.pd-layout {
  display: grid;
  grid-template-columns: 1fr 1.15fr 280px;
  gap: 2.5rem;
  align-items: start;
}

/* ---- Left: Image gallery ---- */
.pd-gallery {
  position: sticky;
  top: 90px;
}

.pd-main-img-wrap {
  position: relative;
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
  aspect-ratio: 1 / 1;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
  box-shadow: 0 2px 16px rgba(26,46,74,.07);
}

.pd-main-img-wrap img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  transition: opacity .3s ease;
  padding: 1.5rem;
}

/* Prev/Next arrow buttons on image */
.pd-img-nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(255,255,255,.9);
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0,0,0,.12);
  transition: .15s ease;
  z-index: 2;
  color: #2b3437;
}
.pd-img-nav:hover { background: #fff; box-shadow: 0 4px 14px rgba(0,0,0,.16); }
.pd-img-nav.prev { left: .75rem; }
.pd-img-nav.next { right: .75rem; }

.pd-thumbnails {
  display: flex;
  gap: .5rem;
  flex-wrap: wrap;
}

.pd-thumb {
  width: 68px;
  height: 68px;
  border: 2px solid transparent;
  border-radius: 8px;
  background: #fff;
  overflow: hidden;
  cursor: pointer;
  transition: border-color .15s;
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pd-thumb img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  padding: .3rem;
}

.pd-thumb.is-active,
.pd-thumb:hover {
  border-color: #1a2e4a;
}

/* ---- Middle: Product info ---- */
.pd-info {
  background: transparent;
}

.pd-category-tag {
  display: inline-block;
  margin-bottom: .75rem;
  color: #016b5e;
  font-size: .75rem;
  font-weight: 800;
  letter-spacing: .14em;
  text-transform: uppercase;
}

.pd-title {
  font-size: clamp(1.4rem, 2.5vw, 1.85rem);
  font-weight: 800;
  line-height: 1.25;
  color: #1a2e4a;
  margin: 0 0 1.25rem;
}

/* Price block */
.pd-price-block {
  display: flex;
  align-items: baseline;
  gap: .9rem;
  background: #fff7e6;
  border: 1px solid #fce8b3;
  border-radius: 8px;
  padding: .85rem 1.1rem;
  margin-bottom: 1.25rem;
}

.pd-price {
  font-size: 1.7rem;
  font-weight: 800;
  color: #d4880a;
}

.pd-price-old {
  font-size: 1rem;
  color: #9b9d9e;
  text-decoration: line-through;
}

.pd-price-badge {
  margin-left: auto;
  padding: .22rem .75rem;
  border-radius: 999px;
  background: #e53e3e;
  color: #fff;
  font-size: .75rem;
  font-weight: 800;
}

/* Short description */
.pd-short-desc {
  color: #586064;
  font-size: .93rem;
  line-height: 1.65;
  margin: 0 0 1.1rem;
}

/* Stock / status */
.pd-stock-row {
  display: flex;
  align-items: center;
  gap: .6rem;
  margin-bottom: 1.25rem;
  font-size: .88rem;
}
.pd-stock-label { color: #586064; }
.pd-stock-instock { color: #16a34a; font-weight: 700; }
.pd-stock-outstock { color: #dc2626; font-weight: 700; }
.pd-stock-preorder { color: #d97706; font-weight: 700; }
.pd-stock-qty { color: #9aa3a6; font-size: .82rem; }

/* Variants */
.pd-variant-group {
  margin-bottom: 1.1rem;
}
.pd-variant-label {
  display: block;
  font-size: .8rem;
  font-weight: 800;
  color: #586064;
  letter-spacing: .1em;
  text-transform: uppercase;
  margin-bottom: .55rem;
}
.pd-variant-options {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
}
.pd-variant-pill {
  padding: .48rem 1rem;
  border: 1.5px solid #dbe4e7;
  border-radius: 6px;
  background: #fff;
  color: #2b3437;
  font-size: .88rem;
  font-weight: 600;
  cursor: pointer;
  transition: .18s ease;
}
.pd-variant-pill:hover:not(.active) {
  border-color: #1a2e4a;
  color: #1a2e4a;
}
.pd-variant-pill.active {
  border-color: #1a2e4a;
  background: #1a2e4a;
  color: #fff;
}

/* Quantity + actions */
.pd-action-row {
  display: flex;
  align-items: stretch;
  gap: .75rem;
  margin-top: 1.5rem;
  flex-wrap: wrap;
}

/* Qty stepper */
.pd-qty-stepper {
  display: inline-flex;
  align-items: center;
  border: 1.5px solid #dbe4e7;
  border-radius: 8px;
  overflow: hidden;
  background: #fff;
  flex: 0 0 auto;
}
.pd-qty-btn {
  width: 40px;
  height: 48px;
  border: none;
  background: transparent;
  color: #2b3437;
  font-size: 1.2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background .15s;
  user-select: none;
}
.pd-qty-btn:hover { background: #f1f4f6; }
.pd-qty-input {
  width: 48px;
  height: 48px;
  border: none;
  border-left: 1px solid #dbe4e7;
  border-right: 1px solid #dbe4e7;
  text-align: center;
  font-size: .95rem;
  font-weight: 700;
  color: #2b3437;
  background: #fff;
  outline: none;
  -moz-appearance: textfield;
}
.pd-qty-input::-webkit-outer-spin-button,
.pd-qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

/* Add to cart button */
.pd-btn-cart {
  flex: 1;
  min-height: 48px;
  padding: 0 1.5rem;
  border: 2px solid #1a2e4a;
  border-radius: 8px;
  background: #fff;
  color: #1a2e4a;
  font-size: .9rem;
  font-weight: 800;
  letter-spacing: .04em;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .5rem;
  cursor: pointer;
  transition: .2s ease;
}
.pd-btn-cart:hover:not(:disabled) {
  background: #1a2e4a;
  color: #fff;
}
.pd-btn-cart:disabled {
  border-color: #c5cdd1;
  color: #9aa3a6;
  cursor: not-allowed;
  background: #f4f7f9;
}

/* Buy now button */
.pd-btn-buy {
  flex: 1;
  min-height: 48px;
  padding: 0 1.5rem;
  border: none;
  border-radius: 8px;
  background: #1a2e4a;
  color: #f5b700;
  font-size: .9rem;
  font-weight: 800;
  letter-spacing: .04em;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .5rem;
  cursor: pointer;
  transition: .2s ease;
  text-decoration: none;
}
.pd-btn-buy:hover:not(:disabled) {
  background: #2d4563;
  color: #ffd24a;
}
.pd-btn-buy:disabled {
  background: #9ca3af;
  color: #fff;
  cursor: not-allowed;
}

/* Variant specs info */
.pd-specs-box {
  margin-top: 1.1rem;
  padding: .85rem 1rem;
  background: #f4f7f9;
  border-radius: 8px;
  font-size: .84rem;
  color: #586064;
  line-height: 1.65;
  border: 1px solid #e4ebee;
}
.pd-specs-box strong { color: #2b3437; }

/* Feature meta chips */
.pd-meta-chips {
  display: flex;
  flex-wrap: wrap;
  gap: .45rem;
  margin-top: 1.1rem;
}
.pd-meta-chip {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  padding: .28rem .75rem;
  border-radius: 999px;
  background: #e9eff2;
  color: #586064;
  font-size: .78rem;
  font-weight: 700;
}

/* ---- Right: Services panel ---- */
.pd-services {
  display: grid;
  gap: 0;
  border: 1px solid #e4ebee;
  border-radius: 12px;
  background: #fff;
  overflow: hidden;
}
.pd-service-item {
  display: flex;
  align-items: flex-start;
  gap: .9rem;
  padding: 1.1rem 1.1rem;
  border-bottom: 1px solid #f0f3f5;
  transition: background .15s;
}
.pd-service-item:last-child { border-bottom: none; }
.pd-service-item:hover { background: #f8fafb; }

.pd-service-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: #e8f0ed;
  color: #016b5e;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  flex: 0 0 40px;
}

.pd-service-body {}
.pd-service-title {
  font-size: .82rem;
  font-weight: 800;
  color: #1a2e4a;
  letter-spacing: .04em;
  text-transform: uppercase;
  margin-bottom: .2rem;
}
.pd-service-desc {
  font-size: .78rem;
  color: #737c7f;
  line-height: 1.5;
  margin: 0;
}

/* ---- Related products ---- */
.pd-related {
  margin-top: 4rem;
  padding-top: 2rem;
  border-top: 1px solid #e4ebee;
}
.pd-related-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
}
.pd-related-head h2 {
  margin: 0;
  font-size: 1.3rem;
  font-weight: 800;
  color: #1a2e4a;
}
.pd-related-head a {
  font-size: .85rem;
  font-weight: 700;
  color: #016b5e;
  text-decoration: underline;
}
.pd-related-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1.25rem;
}
.pd-related-card {
  background: #fff;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid #e8ecef;
  transition: .2s ease;
  text-decoration: none;
  color: inherit;
}
.pd-related-card:hover {
  box-shadow: 0 6px 20px rgba(26,46,74,.1);
  transform: translateY(-2px);
}
.pd-related-img {
  background: #f4f7f9;
  aspect-ratio: 4/3;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.pd-related-img img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.pd-related-body {
  padding: .85rem 1rem;
}
.pd-related-cat {
  font-size: .72rem;
  color: #9aa3a6;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  margin-bottom: .3rem;
}
.pd-related-name {
  font-size: .88rem;
  font-weight: 700;
  color: #2b3437;
  line-height: 1.35;
  margin-bottom: .4rem;
}
.pd-related-price {
  font-size: .95rem;
  font-weight: 800;
  color: #d4880a;
}

/* Toast notification */
.pd-toast {
  position: fixed;
  bottom: 2rem;
  right: 2rem;
  z-index: 9999;
  background: #1a2e4a;
  color: #fff;
  padding: .9rem 1.4rem;
  border-radius: 10px;
  font-size: .9rem;
  font-weight: 700;
  box-shadow: 0 8px 24px rgba(0,0,0,.2);
  display: flex;
  align-items: center;
  gap: .6rem;
  transform: translateY(20px);
  opacity: 0;
  transition: .3s ease;
  pointer-events: none;
}
.pd-toast.show {
  transform: translateY(0);
  opacity: 1;
}

/* Responsive */
@media (max-width: 1100px) {
  .pd-layout {
    grid-template-columns: 1fr 1.2fr;
  }
  .pd-services {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    border-radius: 10px;
  }
  .pd-service-item:nth-child(odd):last-child {
    grid-column: 1 / -1;
  }
  .pd-related-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 760px) {
  .pd-layout {
    grid-template-columns: 1fr;
  }
  .pd-gallery {
    position: static;
  }
  .pd-services {
    grid-template-columns: 1fr;
  }
  .pd-action-row {
    flex-direction: column;
  }
  .pd-btn-cart,
  .pd-btn-buy {
    width: 100%;
  }
  .pd-related-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>

<div class="pd-page">
  <div class="pd-container">
    <!-- Breadcrumb -->
    <nav class="pd-breadcrumb" aria-label="Breadcrumb">
      <a href="<?= e(APP_URL) ?>/">Trang chủ</a>
      <span>›</span>
      <a href="<?= e(APP_URL) ?>/products.php?category=<?= e($product['category_slug'] ?? '') ?>"><?= e($product['category_name'] ?? 'Sản phẩm') ?></a>
      <span>›</span>
      <span><?= e($product['name']) ?></span>
    </nav>

    <!-- Main layout -->
    <div class="pd-layout">

      <!-- LEFT: Gallery -->
      <div class="pd-gallery">
        <div class="pd-main-img-wrap">
          <?php if (count($galleryImages) > 1): ?>
          <button type="button" class="pd-img-nav prev" id="pdImgPrev" aria-label="Ảnh trước">
            <svg width="16" height="16" fill="none" viewBox="0 0 16 16"><path d="M10 3L6 8l4 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <button type="button" class="pd-img-nav next" id="pdImgNext" aria-label="Ảnh tiếp">
            <svg width="16" height="16" fill="none" viewBox="0 0 16 16"><path d="M6 3l4 5-4 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <?php endif; ?>
          <img id="pdMainImg" src="<?= e(lumina_detail_img($galleryImages[0]['image_url'], $placeholderImage)) ?>" alt="<?= e($product['name']) ?>">
        </div>

        <?php if (count($galleryImages) > 1): ?>
        <div class="pd-thumbnails" id="pdThumbs">
          <?php foreach ($galleryImages as $idx => $img): ?>
            <div class="pd-thumb <?= $idx === 0 ? 'is-active' : '' ?>" data-idx="<?= $idx ?>" data-src="<?= e(lumina_detail_img($img['image_url'], $placeholderImage)) ?>">
              <img src="<?= e(lumina_detail_img($img['image_url'], $placeholderImage)) ?>" alt="<?= e($img['alt_text'] ?: $product['name']) ?>" loading="lazy">
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- MIDDLE: Product Info -->
      <div class="pd-info">
        <span class="pd-category-tag"><?= e($product['category_name'] ?? 'LUMINA') ?></span>
        <h1 class="pd-title"><?= e($product['name']) ?></h1>

        <!-- Price block -->
        <div class="pd-price-block">
          <span class="pd-price" id="pdPrice"><?= e(format_price($product['default_price'])) ?></span>
          <?php if (!empty($product['compare_at_price']) && (float)$product['compare_at_price'] > (float)$product['default_price']): ?>
            <span class="pd-price-old"><?= e(format_price($product['compare_at_price'])) ?></span>
            <?php
              $discountPct = round((1 - $product['default_price'] / $product['compare_at_price']) * 100);
            ?>
            <span class="pd-price-badge">-<?= $discountPct ?>%</span>
          <?php endif; ?>
        </div>

        <!-- Short description -->
        <?php if (!empty($product['short_description'])): ?>
        <p class="pd-short-desc"><?= e($product['short_description']) ?></p>
        <?php endif; ?>

        <!-- Stock status (updated by JS) -->
        <div class="pd-stock-row" id="pdStockRow">
          <span class="pd-stock-label">Tình trạng:</span>
          <span id="pdStockStatus">
            <?php if (!empty($variants)): ?>
              <span class="pd-stock-instock">Còn hàng</span>
            <?php else: ?>
              <span class="pd-stock-outstock">Liên hệ shop</span>
            <?php endif; ?>
          </span>
          <span class="pd-stock-qty" id="pdStockQty"></span>
        </div>

        <!-- Variants form -->
        <form class="pd-action-form" method="post" action="<?= e(APP_URL) ?>/add-to-cart.php" id="pdAddToCartForm">
          <input type="hidden" name="variant_id" id="selectedVariantId" value="">

          <?php if (!empty($variants)): ?>
            <?php
              $colors = array_unique(array_filter(array_column($variants, 'color')));
              $sizes  = array_unique(array_filter(array_column($variants, 'size_label')));
            ?>

            <?php if (!empty($colors)): ?>
            <div class="pd-variant-group">
              <span class="pd-variant-label">Màu sắc</span>
              <div class="pd-variant-options">
                <?php foreach ($colors as $idx => $color): ?>
                  <button type="button" class="pd-variant-pill color-pill <?= $idx === 0 ? 'active' : '' ?>" data-color="<?= e($color) ?>">
                    <?= e($color) ?>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($sizes)): ?>
            <div class="pd-variant-group">
              <span class="pd-variant-label">Kích thước</span>
              <div class="pd-variant-options">
                <?php foreach ($sizes as $idx => $size): ?>
                  <button type="button" class="pd-variant-pill size-pill <?= $idx === 0 ? 'active' : '' ?>" data-size="<?= e($size) ?>">
                    <?= e($size) ?>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>

            <!-- Specs info box -->
            <div class="pd-specs-box" id="pdSpecsBox" style="display:none;"></div>
          <?php endif; ?>

          <!-- Qty + Add to cart + Buy now -->
          <div class="pd-action-row">
            <!-- Quantity stepper -->
            <div class="pd-qty-stepper">
              <button type="button" class="pd-qty-btn" id="pdQtyMinus" aria-label="Giảm số lượng">−</button>
              <input type="number" name="quantity" id="pdQtyInput" class="pd-qty-input" value="1" min="1" max="99">
              <button type="button" class="pd-qty-btn" id="pdQtyPlus" aria-label="Tăng số lượng">+</button>
            </div>

            <!-- Add to cart -->
            <button type="submit" class="pd-btn-cart" id="pdBtnCart" <?= empty($variants) ? 'disabled' : '' ?>>
              <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><line x1="3" y1="6" x2="21" y2="6" stroke="currentColor" stroke-width="2"/><path d="M16 10a4 4 0 01-8 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              Thêm vào giỏ hàng
            </button>
          </div>

          <!-- Buy now button (full width) -->
          <div style="margin-top:.65rem;">
            <button type="button" class="pd-btn-buy" id="pdBtnBuy" style="width:100%;" <?= empty($variants) ? 'disabled' : '' ?> onclick="document.getElementById('pdBuyNow').value='1'; document.getElementById('pdAddToCartForm').action='<?= e(APP_URL) ?>/add-to-cart.php?buynow=1'; document.getElementById('pdAddToCartForm').submit();">
              <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              MUA NGAY
            </button>
            <input type="hidden" name="buynow" id="pdBuyNow" value="0">
          </div>
        </form>

        <!-- Meta chips -->
        <div class="pd-meta-chips">
          <?php foreach ([
            $product['brand'] ?? null,
            $product['shape'] ?? null,
            $product['material'] ?? null,
            !empty($product['target_gender']) ? gender_label($product['target_gender']) : null,
            !empty($product['frame_type']) ? $product['frame_type'] : null,
          ] as $meta): ?>
            <?php if ($meta): ?>
              <span class="pd-meta-chip"><?= e($meta) ?></span>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- RIGHT: Services -->
      <div class="pd-services">
        <div class="pd-service-item">
          <div class="pd-service-icon">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11a2 2 0 012 2v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><rect x="9" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M16 11V9a2 2 0 00-4 0v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          </div>
          <div class="pd-service-body">
            <div class="pd-service-title">Miễn phí giao hàng toàn quốc</div>
            <p class="pd-service-desc">Với tất cả các đơn hàng có giá trị trên 400.000đ</p>
          </div>
        </div>

        <div class="pd-service-item">
          <div class="pd-service-icon">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path d="M21 10.5c0 5.523-9 13-9 13s-9-7.477-9-13a9 9 0 0118 0z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="10.5" r="3" stroke="currentColor" stroke-width="1.8"/></svg>
          </div>
          <div class="pd-service-body">
            <div class="pd-service-title">Chính sách đổi trả dễ dàng</div>
            <p class="pd-service-desc">Đổi hàng 7 ngày với gọng kính, bảo hành 1 năm về thay óc va, nắn chỉnh gọng</p>
          </div>
        </div>

        <div class="pd-service-item">
          <div class="pd-service-icon">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 10.8a19.79 19.79 0 01-3.07-8.63A2 2 0 012 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 7.91a16 16 0 006.11 6.11l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 14.92z" stroke="currentColor" stroke-width="1.8"/></svg>
          </div>
          <div class="pd-service-body">
            <div class="pd-service-title">Tổng đài hỗ trợ</div>
            <p class="pd-service-desc">0904.915.377 — Chúng tôi luôn sẵn lòng giải đáp mọi câu hỏi</p>
          </div>
        </div>

        <div class="pd-service-item">
          <div class="pd-service-icon">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v4l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          </div>
          <div class="pd-service-body">
            <div class="pd-service-title">Hướng dẫn mua hàng</div>
            <p class="pd-service-desc">Chọn kính – thử online – đặt hàng nhanh chóng trong 3 bước</p>
          </div>
        </div>
      </div>

    </div><!-- /.pd-layout -->

    <!-- Full description -->
    <?php if (!empty($product['description'])): ?>
    <div style="margin-top:2.5rem; padding:2rem; background:#fff; border-radius:12px; border:1px solid #e4ebee;">
      <h2 style="font-size:1.1rem;font-weight:800;color:#1a2e4a;margin:0 0 1rem;">Mô tả sản phẩm</h2>
      <div style="color:#586064;font-size:.92rem;line-height:1.8;"><?= nl2br(e($product['description'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════
         SECTION: Product Reviews & Ratings
         ══════════════════════════════════════════════════════════ -->
    <div id="reviews" style="margin-top:2.5rem; scroll-margin-top:90px;">
      <style>
      .rev-section { background:#fff; border-radius:16px; border:1px solid #e4ebee; overflow:hidden; }
      .rev-header { padding:1.75rem 2rem; border-bottom:1px solid #f0f3f5; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; }
      .rev-header h2 { font-size:1.15rem;font-weight:800;color:#1a2e4a;margin:0; }
      .rev-summary { display:flex; gap:2rem; align-items:center; }
      .rev-avg-block { text-align:center; }
      .rev-avg-num { font-size:3rem; font-weight:900; color:#1a2e4a; line-height:1; }
      .rev-avg-stars { display:flex; gap:2px; justify-content:center; margin:.35rem 0 .2rem; }
      .rev-avg-stars .s { font-size:1.1rem; color:#f59e0b; }
      .rev-avg-stars .s.empty { color:#e2e8f0; }
      .rev-avg-count { font-size:.8rem; color:#9aa3a6; }
      .rev-dist { flex:1; min-width:180px; }
      .rev-dist-row { display:flex; align-items:center; gap:.6rem; margin-bottom:.3rem; font-size:.78rem; }
      .rev-dist-bar-wrap { flex:1; height:6px; background:#f0f3f5; border-radius:3px; overflow:hidden; }
      .rev-dist-bar { height:100%; background:linear-gradient(90deg,#f59e0b,#fbbf24); border-radius:3px; transition:width .4s; }
      .rev-dist-label { min-width:18px; color:#718096; }
      .rev-dist-count { min-width:18px; color:#9aa3a6; text-align:right; }

      .rev-list { padding:1.5rem 2rem; }
      .rev-item { padding:1.25rem 0; border-bottom:1px solid #f0f3f5; }
      .rev-item:last-child { border-bottom:none; }
      .rev-item-header { display:flex; gap:.75rem; align-items:flex-start; margin-bottom:.75rem; }
      .rev-avatar { width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#1a2e4a,#2a4365);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.85rem;flex:0 0 38px; }
      .rev-meta { flex:1; }
      .rev-name { font-weight:700;font-size:.88rem;color:#1a2e4a; }
      .rev-date { font-size:.75rem;color:#9aa3a6; }
      .rev-stars { display:flex;gap:1px;margin:.2rem 0; }
      .rev-stars .s { font-size:.9rem;color:#f59e0b; }
      .rev-stars .s.empty { color:#e2e8f0; }
      .rev-title { font-weight:700;font-size:.9rem;color:#1a2e4a;margin-bottom:.35rem; }
      .rev-body { font-size:.88rem;color:#4a5568;line-height:1.65; }
      .rev-photos { display:flex;gap:.5rem;margin-top:.75rem;flex-wrap:wrap; }
      .rev-photo { width:72px;height:72px;border-radius:8px;object-fit:cover;border:2px solid #e2e8f0;cursor:pointer;transition:.15s; }
      .rev-photo:hover { border-color:#1a2e4a; }

      .btn-write-review {
        padding:.65rem 1.4rem; border:2px solid #d4880a; border-radius:8px;
        background:#fff; color:#d4880a; font-weight:800; font-size:.88rem;
        cursor:pointer; transition:all .2s; display:inline-flex; align-items:center; gap:.5rem;
      }
      .btn-write-review:hover { background:#d4880a; color:#fff; }

      /* Review form modal */
      .rev-modal-backdrop { display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:1rem;backdrop-filter:blur(3px); }
      .rev-modal-backdrop.open { display:flex; }
      .rev-modal { background:#fff;border-radius:16px;width:100%;max-width:580px;max-height:90vh;overflow-y:auto;padding:2rem;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:revModalIn .25s ease; }
      @keyframes revModalIn { from{transform:translateY(-20px);opacity:0} to{transform:translateY(0);opacity:1} }
      .rev-modal h3 { font-size:1.2rem;font-weight:800;color:#1a2e4a;margin:0 0 1.25rem; }

      .star-picker { display:flex;gap:.35rem;margin:.5rem 0 1rem;flex-direction:row-reverse;justify-content:flex-end; }
      .star-picker input { display:none; }
      .star-picker label { font-size:2rem;cursor:pointer;color:#e2e8f0;transition:color .15s; }
      .star-picker input:checked ~ label,
      .star-picker label:hover,
      .star-picker label:hover ~ label { color:#f59e0b; }

      .rev-form-group { margin-bottom:1rem; }
      .rev-form-group label { display:block;font-size:.82rem;font-weight:700;color:#4a5568;margin-bottom:.35rem; }
      .rev-form-group input, .rev-form-group textarea {
        width:100%;padding:.6rem .8rem;border:1.5px solid #dbe4e7;border-radius:8px;
        font-size:.9rem;outline:none;transition:border-color .2s;background:#f8fafc;
        box-sizing:border-box;
      }
      .rev-form-group input:focus, .rev-form-group textarea:focus { border-color:#1a2e4a;background:#fff; }
      .rev-photo-upload-area {
        border:2px dashed #dbe4e7;border-radius:8px;padding:1.25rem;text-align:center;
        cursor:pointer;transition:.2s;color:#9aa3a6;font-size:.85rem;
      }
      .rev-photo-upload-area:hover { border-color:#1a2e4a;color:#1a2e4a;background:#f0f7ff; }
      .rev-photo-previews { display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem; }
      .rev-preview-img { width:72px;height:72px;border-radius:8px;object-fit:cover;border:2px solid #e2e8f0;position:relative; }
      .rev-modal-actions { display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.25rem; }
      .btn-rev-cancel { padding:.6rem 1.25rem;border:1.5px solid #dbe4e7;border-radius:8px;background:#fff;color:#718096;font-weight:700;cursor:pointer; }
      .btn-rev-submit { padding:.6rem 1.5rem;border:none;border-radius:8px;background:#1a2e4a;color:#fff;font-weight:800;cursor:pointer;transition:.2s; }
      .btn-rev-submit:hover { background:#2d4563; }

      /* Lightbox */
      .rev-lightbox { display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center; }
      .rev-lightbox.show { display:flex; }
      .rev-lightbox img { max-width:90vw;max-height:85vh;border-radius:8px;object-fit:contain; }
      .rev-lightbox-close { position:absolute;top:1.5rem;right:1.5rem;color:#fff;font-size:2rem;cursor:pointer; }

      .empty-reviews { text-align:center;padding:3rem 1rem;color:#a0aec0; }
      </style>

      <div class="rev-section">
        <div class="rev-header">
          <div style="display:flex;flex-direction:column;gap:.35rem;">
            <h2>⭐ Đánh giá từ khách hàng</h2>
            <?php if ($reviewStats['count'] > 0): ?>
              <p style="margin:0;font-size:.83rem;color:#718096;"><?= $reviewStats['count'] ?> đánh giá từ khách đã mua</p>
            <?php endif; ?>
          </div>

          <?php if ($canReview): ?>
            <button class="btn-write-review" onclick="openRevModal()">
              ✍️ Viết đánh giá
            </button>
          <?php elseif ($hasReviewed): ?>
            <span style="font-size:.82rem;color:#10b981;font-weight:700;">✅ Bạn đã đánh giá sản phẩm này</span>
          <?php elseif (!is_logged_in()): ?>
            <a href="<?= e(APP_URL) ?>/login.php" style="font-size:.82rem;color:#d4880a;font-weight:700;text-decoration:none;">Đăng nhập để đánh giá →</a>
          <?php endif; ?>
        </div>

        <?php if ($reviewStats['count'] > 0): ?>
        <div style="padding:1.25rem 2rem;border-bottom:1px solid #f0f3f5;">
          <div class="rev-summary">
            <div class="rev-avg-block">
              <div class="rev-avg-num"><?= number_format($reviewStats['avg'], 1) ?></div>
              <div class="rev-avg-stars">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                  <span class="s <?= $s > round($reviewStats['avg']) ? 'empty' : '' ?>">★</span>
                <?php endfor; ?>
              </div>
              <div class="rev-avg-count"><?= $reviewStats['count'] ?> đánh giá</div>
            </div>
            <div class="rev-dist">
              <?php foreach (array_reverse([1,2,3,4,5]) as $star): ?>
                <?php $pct = $reviewStats['count'] > 0 ? round($reviewStats['dist'][$star] / $reviewStats['count'] * 100) : 0; ?>
                <div class="rev-dist-row">
                  <span class="rev-dist-label"><?= $star ?>★</span>
                  <div class="rev-dist-bar-wrap"><div class="rev-dist-bar" style="width:<?= $pct ?>%;"></div></div>
                  <span class="rev-dist-count"><?= $reviewStats['dist'][$star] ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($reviewErrors)): ?>
          <div class="alert warning" style="margin:1rem 2rem;border-radius:8px;">
            <?php foreach ($reviewErrors as $e): ?><p><?= e($e) ?></p><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($reviewSuccess): ?>
          <div class="alert success" style="margin:1rem 2rem;border-radius:8px;padding:.85rem 1.1rem;background:#f0fdf4;border:1.5px solid #86efac;color:#15803d;font-weight:700;display:flex;align-items:center;gap:.5rem;">
            ✅ <?= e($reviewSuccess) ?>
          </div>
        <?php endif; ?>

        <div class="rev-list">
          <?php if (empty($reviews)): ?>
            <div class="empty-reviews">
              <div style="font-size:3rem;margin-bottom:.75rem;">💬</div>
              <p style="font-size:.9rem;font-weight:600;">Chưa có đánh giá nào được phê duyệt.</p>
              <?php if ($canReview): ?>
                <p style="font-size:.83rem;">Hãy là người đầu tiên chia sẻ trải nghiệm của bạn!</p>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <?php foreach ($reviews as $rev): ?>
              <?php
                $revImgs   = !empty($rev['images']) ? (array) json_decode($rev['images'], true) : [];
                $initials  = mb_strtoupper(mb_substr($rev['reviewer_name'], 0, 2));
                $anonName  = mb_substr($rev['reviewer_name'], 0, 1) . str_repeat('*', max(1, mb_strlen($rev['reviewer_name']) - 2)) . mb_substr($rev['reviewer_name'], -1);
              ?>
              <div class="rev-item">
                <div class="rev-item-header">
                  <div class="rev-avatar"><?= e($initials) ?></div>
                  <div class="rev-meta">
                    <div class="rev-name"><?= e($anonName) ?></div>
                    <div class="rev-stars"><?php for ($s=1;$s<=5;$s++): ?><span class="s <?= $s > $rev['rating'] ? 'empty' : '' ?>">★</span><?php endfor; ?></div>
                    <div class="rev-date"><?= e(date('d/m/Y', strtotime((string)$rev['created_at']))) ?></div>
                  </div>
                </div>
                <?php if (!empty($rev['title'])): ?>
                  <div class="rev-title"><?= e($rev['title']) ?></div>
                <?php endif; ?>
                <?php if (!empty($rev['body'])): ?>
                  <div class="rev-body"><?= nl2br(e($rev['body'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($revImgs)): ?>
                  <div class="rev-photos">
                    <?php foreach ($revImgs as $imgPath): ?>
                      <img class="rev-photo" src="<?= e(APP_URL . $imgPath) ?>" alt="Review photo" onclick="openRevLightbox('<?= e(APP_URL . $imgPath) ?>')">
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Related products -->
    <?php if ($related): ?>
    <div class="pd-related">
      <div class="pd-related-head">
        <h2>Sản phẩm liên quan</h2>
        <a href="<?= e(APP_URL) ?>/products.php?category=<?= e($product['category_slug'] ?? '') ?>">Xem thêm →</a>
      </div>
      <div class="pd-related-grid">
        <?php foreach ($related as $item): ?>
          <a class="pd-related-card" href="<?= e(APP_URL) ?>/product-detail.php?id=<?= (int) $item['id'] ?>">
            <div class="pd-related-img">
              <img src="<?= e(lumina_detail_img($item['thumbnail'], $placeholderImage)) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
            </div>
            <div class="pd-related-body">
              <div class="pd-related-cat"><?= e($item['category_name']) ?></div>
              <div class="pd-related-name"><?= e($item['name']) ?></div>
              <div class="pd-related-price"><?= e(format_price($item['default_price'])) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /.pd-container -->
</div><!-- /.pd-page -->

<!-- Review Write Modal -->
<?php if ($canReview): ?>
<div class="rev-modal-backdrop" id="revModalBackdrop">
  <div class="rev-modal">
    <h3>✍️ Viết đánh giá của bạn</h3>
    <p style="font-size:.83rem;color:#718096;margin-bottom:1.25rem;margin-top:-.75rem;">Chia sẻ trải nghiệm thực tế của bạn với sản phẩm này.</p>

    <form method="POST" action="<?= e(APP_URL) ?>/submit-review.php" enctype="multipart/form-data">
      <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">

      <!-- Star rating picker -->
      <div class="rev-form-group">
        <label>Chấm điểm *</label>
        <div class="star-picker">
          <?php for ($i = 5; $i >= 1; $i--): ?>
            <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" <?= ((int)($reviewOld['rating'] ?? 5)) === $i ? 'checked' : '' ?>>
            <label for="star<?= $i ?>">★</label>
          <?php endfor; ?>
        </div>
      </div>

      <div class="rev-form-group">
        <label for="revTitle">Tiêu đề ngắn</label>
        <input type="text" id="revTitle" name="title" maxlength="200" value="<?= e((string)($reviewOld['title'] ?? '')) ?>" placeholder="Ví dụ: Kính rất đẹp và nhẹ!">
      </div>

      <div class="rev-form-group">
        <label for="revBody">Nhận xét chi tiết *</label>
        <textarea id="revBody" name="body" rows="4" required placeholder="Chia sẻ cảm nhận về chất lượng, độ vừa vặn, kiểu dáng..."><?= e((string)($reviewOld['body'] ?? '')) ?></textarea>
      </div>

      <div class="rev-form-group">
        <label>Ảnh thực tế (tối đa 3 ảnh, mỗi ảnh ≤ 5MB)</label>
        <div class="rev-photo-upload-area" onclick="document.getElementById('revImages').click()">
          <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="margin:0 auto .5rem;display:block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          Nhấp để chọn ảnh (JPG, PNG, WebP)
          <input type="file" id="revImages" name="review_images[]" accept="image/*" multiple style="display:none;" onchange="previewRevImages(this)">
        </div>
        <div class="rev-photo-previews" id="revPhotoPreviews"></div>
      </div>

      <div class="rev-modal-actions">
        <button type="button" class="btn-rev-cancel" onclick="closeRevModal()">Hủy</button>
        <button type="submit" class="btn-rev-submit">📤 Gửi đánh giá</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Lightbox for review images -->
<div class="rev-lightbox" id="revLightbox" onclick="closeRevLightbox()">
  <span class="rev-lightbox-close">×</span>
  <img id="revLightboxImg" src="" alt="">
</div>

<script>
function openRevModal() {
  document.getElementById('revModalBackdrop').classList.add('open');
}
function closeRevModal() {
  document.getElementById('revModalBackdrop').classList.remove('open');
}
document.getElementById('revModalBackdrop')?.addEventListener('click', function(e) {
  if (e.target === this) closeRevModal();
});

function previewRevImages(input) {
  const container = document.getElementById('revPhotoPreviews');
  container.innerHTML = '';
  const files = Array.from(input.files).slice(0, 3);
  files.forEach(file => {
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.createElement('img');
      img.src = e.target.result;
      img.className = 'rev-preview-img';
      container.appendChild(img);
    };
    reader.readAsDataURL(file);
  });
}

function openRevLightbox(src) {
  document.getElementById('revLightboxImg').src = src;
  document.getElementById('revLightbox').classList.add('show');
}
function closeRevLightbox() {
  document.getElementById('revLightbox').classList.remove('show');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeRevModal(); closeRevLightbox(); } });

// Auto-open review modal if redirected here after error
<?php if (!empty($reviewErrors)): ?>
document.addEventListener('DOMContentLoaded', () => openRevModal());
<?php endif; ?>
</script>

<!-- Toast notification -->
<div class="pd-toast" id="pdToast">
  <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" stroke="#f5b700" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
  <span id="pdToastMsg">Đã thêm vào giỏ hàng!</span>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const variants = <?= json_encode($variants) ?>;
  const placeholder = "<?= e(lumina_detail_img($product['thumbnail'], $placeholderImage)) ?>";
  const galleryImages = <?= json_encode(array_map(fn($img) => lumina_detail_img($img['image_url'], $placeholderImage), $galleryImages)) ?>;

  // ---- Gallery ----
  let currentImgIdx = 0;
  const mainImg = document.getElementById('pdMainImg');
  const thumbs  = document.querySelectorAll('.pd-thumb');

  function setGalleryImg(idx) {
    currentImgIdx = idx;
    if (mainImg) {
      mainImg.style.opacity = '0.5';
      setTimeout(() => {
        mainImg.src = galleryImages[idx] || placeholder;
        mainImg.style.opacity = '1';
      }, 150);
    }
    thumbs.forEach(t => t.classList.toggle('is-active', parseInt(t.dataset.idx) === idx));
  }

  thumbs.forEach(t => t.addEventListener('click', () => setGalleryImg(parseInt(t.dataset.idx))));

  const prevBtn = document.getElementById('pdImgPrev');
  const nextBtn = document.getElementById('pdImgNext');
  if (prevBtn) prevBtn.addEventListener('click', () => setGalleryImg((currentImgIdx - 1 + galleryImages.length) % galleryImages.length));
  if (nextBtn) nextBtn.addEventListener('click', () => setGalleryImg((currentImgIdx + 1) % galleryImages.length));

  // ---- Quantity stepper ----
  const qtyInput = document.getElementById('pdQtyInput');
  document.getElementById('pdQtyMinus')?.addEventListener('click', () => {
    const v = parseInt(qtyInput.value) || 1;
    qtyInput.value = Math.max(1, v - 1);
  });
  document.getElementById('pdQtyPlus')?.addEventListener('click', () => {
    const v = parseInt(qtyInput.value) || 1;
    const max = parseInt(qtyInput.max) || 99;
    qtyInput.value = Math.min(max, v + 1);
  });

  // ---- Variants ----
  if (variants.length === 0) return;

  const variantIdInput = document.getElementById('selectedVariantId');
  const priceDisplay   = document.getElementById('pdPrice');
  const stockStatus    = document.getElementById('pdStockStatus');
  const stockQty       = document.getElementById('pdStockQty');
  const specsBox       = document.getElementById('pdSpecsBox');
  const btnCart        = document.getElementById('pdBtnCart');
  const btnBuy         = document.getElementById('pdBtnBuy');

  function updateVariant() {
    const activeColor = document.querySelector('.color-pill.active')?.dataset.color || null;
    const activeSize  = document.querySelector('.size-pill.active')?.dataset.size  || null;

    let matched = variants.find(v => {
      const colorOk = !activeColor || v.color === activeColor;
      const sizeOk  = !activeSize  || v.size_label === activeSize;
      return colorOk && sizeOk;
    }) || (activeColor ? variants.find(v => v.color === activeColor) : null) || variants[0];

    if (!matched) {
      variantIdInput.value = '';
      stockStatus.innerHTML = '<span class="pd-stock-outstock">Không khả dụng</span>';
      stockQty.textContent  = '';
      if (specsBox) { specsBox.style.display = 'none'; specsBox.innerHTML = ''; }
      if (btnCart) { btnCart.disabled = true; }
      if (btnBuy)  { btnBuy.disabled  = true; }
      return;
    }

    variantIdInput.value = matched.id;

    // Price
    const fmtPrice = new Intl.NumberFormat('vi-VN').format(matched.price) + '₫';
    if (priceDisplay) priceDisplay.textContent = fmtPrice;

    // Update image if variant has override
    if (matched.image_override && mainImg) {
      mainImg.src = matched.image_override;
    }

    // Stock
    const stock    = parseInt(matched.stock_quantity) || 0;
    const preorder = parseInt(matched.is_preorder_allowed) || 0;

    if (stock > 0) {
      stockStatus.innerHTML = '<span class="pd-stock-instock">Còn hàng</span>';
      stockQty.textContent  = `(${stock} sản phẩm)`;
      if (btnCart) { btnCart.disabled = false; btnCart.textContent = ''; }
      if (btnBuy)  { btnBuy.disabled  = false; }
      if (qtyInput) qtyInput.max = stock;
    } else if (preorder) {
      stockStatus.innerHTML = '<span class="pd-stock-preorder">Pre-order</span>';
      stockQty.textContent  = '';
      if (btnCart) { btnCart.disabled = false; }
      if (btnBuy)  { btnBuy.disabled  = false; }
    } else {
      stockStatus.innerHTML = '<span class="pd-stock-outstock">Hết hàng</span>';
      stockQty.textContent  = '';
      if (btnCart) { btnCart.disabled = true; }
      if (btnBuy)  { btnBuy.disabled  = true; }
    }

    // Re-add icon + text to cart button (in case it was cleared)
    if (btnCart && !btnCart.disabled) {
      btnCart.innerHTML = `<svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><line x1="3" y1="6" x2="21" y2="6" stroke="currentColor" stroke-width="2"/><path d="M16 10a4 4 0 01-8 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg> Thêm vào giỏ hàng`;
    } else if (btnCart) {
      btnCart.textContent = 'Hết hàng';
    }
    if (btnBuy && !btnBuy.disabled) {
      btnBuy.innerHTML = `<svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> MUA NGAY`;
    }

    // Specs
    if (specsBox) {
      let html = `SKU: <strong>${matched.sku}</strong>`;
      const w = parseFloat(matched.width_mm), b = parseFloat(matched.bridge_mm), t = parseFloat(matched.temple_length_mm);
      if (w > 0 || b > 0 || t > 0) {
        html += `<br>Kích thước: <strong>${w||'—'} – ${b||'—'} – ${t||'—'} mm</strong> <small style="color:#9aa3a6">(Tròng – Cầu – Càng)</small>`;
      }
      specsBox.innerHTML = html;
      specsBox.style.display = 'block';
    }
  }

  document.querySelectorAll('.color-pill').forEach(p => p.addEventListener('click', function () {
    document.querySelectorAll('.color-pill').forEach(x => x.classList.remove('active'));
    this.classList.add('active');
    updateVariant();
  }));

  document.querySelectorAll('.size-pill').forEach(p => p.addEventListener('click', function () {
    document.querySelectorAll('.size-pill').forEach(x => x.classList.remove('active'));
    this.classList.add('active');
    updateVariant();
  }));

  updateVariant();

  // ---- Toast on add to cart (AJAX) ----
  const form  = document.getElementById('pdAddToCartForm');
  const toast = document.getElementById('pdToast');
  const toastMsg = document.getElementById('pdToastMsg');

  if (form) {
    form.addEventListener('submit', function (e) {
      const isBuyNow = document.getElementById('pdBuyNow')?.value === '1';
      if (isBuyNow) return; // Let buy now redirect normally

      e.preventDefault();

      const data = new FormData(form);

      // Validate variant selected
      if (!data.get('variant_id')) {
        showToast('Vui lòng chọn màu sắc / kích thước!', true);
        return;
      }

      fetch('<?= e(APP_URL) ?>/add-to-cart.php', {
        method: 'POST',
        body: data,
      }).then(res => {
        if (res.redirected || res.ok) {
          showToast('Đã thêm vào giỏ hàng! 🛒');
          // Update cart badge in header
          const badge = document.querySelector('.cart-badge, .cart-badge-source');
          if (badge) {
            const current = parseInt(badge.textContent) || 0;
            badge.textContent = current + parseInt(data.get('quantity') || 1);
          }
        } else {
          showToast('Có lỗi xảy ra, vui lòng thử lại!', true);
        }
      }).catch(() => showToast('Có lỗi xảy ra!', true));
    });
  }

  function showToast(msg, isError = false) {
    if (!toast) return;
    toastMsg.textContent = msg;
    toast.style.background = isError ? '#dc2626' : '#1a2e4a';
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
  }
});
</script>

<?php require_once BASE_PATH . '/app/views/partials/footer.php'; ?>
