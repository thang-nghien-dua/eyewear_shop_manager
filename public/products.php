<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';

$db = Database::connect();
$placeholderImage = APP_URL . '/assets/images/placeholder-glasses.svg';

if (!function_exists('lumina_atelier_img')) {
    function lumina_atelier_img(?string $url, string $placeholder): string
    {
        $url = trim((string) $url);
        if ($url === '') return $placeholder;
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '/')) return $url;
        return APP_URL . '/' . ltrim($url, '/');
    }
}

if (!function_exists('lumina_atelier_array')) {
    function lumina_atelier_array(string $key): array
    {
        $value = $_GET[$key] ?? [];
        if (!is_array($value)) $value = [$value];
        return array_values(array_filter(array_map(static fn($v) => trim((string) $v), $value), static fn($v) => $v !== ''));
    }
}

if (!function_exists('lumina_atelier_price')) {
    function lumina_atelier_price($value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        $value = preg_replace('/[^0-9.]/', '', $value);
        if ($value === '') return null;
        return max(0, (float) $value);
    }
}

if (!function_exists('lumina_atelier_url')) {
    function lumina_atelier_url(array $overrides = []): string
    {
        $query = $_GET;
        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($query[$key]);
            } else {
                $query[$key] = $value;
            }
        }
        $qs = http_build_query($query);
        return APP_URL . '/products.php' . ($qs ? '?' . $qs : '');
    }
}

if (!function_exists('lumina_atelier_dot_color')) {
    function lumina_atelier_dot_color(string $name): string
    {
        $lower = mb_strtolower($name, 'UTF-8');
        $map = [
            'đen' => '#141414', 'black' => '#141414',
            'trắng' => '#f8f8f8', 'white' => '#f8f8f8',
            'bạc' => '#c7c7c7', 'silver' => '#c7c7c7',
            'vàng' => '#d4af37', 'gold' => '#d4af37',
            'nâu' => '#7a4b2a', 'brown' => '#7a4b2a',
            'xanh' => '#2f6f68', 'green' => '#2f6f68', 'blue' => '#1f3f77',
            'hồng' => '#f3a4b8', 'pink' => '#f3a4b8',
            'đỏ' => '#9f403d', 'red' => '#9f403d',
            'xám' => '#71706e', 'gray' => '#71706e', 'grey' => '#71706e',
        ];
        foreach ($map as $needle => $color) {
            if (str_contains($lower, $needle)) return $color;
        }
        $palette = ['#D4AF37', '#C0C0C0', '#1A1A1A', '#4A3728', '#2F4F4F', '#0b6f62', '#0b1c6d'];
        return $palette[abs(crc32($lower)) % count($palette)];
    }
}

$keyword = trim((string) ($_GET['keyword'] ?? ''));
$categoryParam = trim((string) ($_GET['category'] ?? ''));
$shapeFilters = lumina_atelier_array('shape');
$materialFilters = lumina_atelier_array('material');
$genderFilters = lumina_atelier_array('gender');
$subcatFilters = array_values(array_filter(array_map('intval', lumina_atelier_array('subcat')), static fn($v) => $v > 0));
$minPrice = lumina_atelier_price($_GET['min_price'] ?? '');
$maxPrice = lumina_atelier_price($_GET['max_price'] ?? '');
$sort = (string) ($_GET['sort'] ?? 'latest');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Valid gender values
$validGenders = ['male', 'female', 'unisex', 'kids'];
$genderFilters = array_values(array_intersect($genderFilters, $validGenders));

$genderLabels = [
    'male'   => 'Nam',
    'female' => 'Nữ',
    'unisex' => 'Unisex',
    'kids'   => 'Trẻ em',
];

$category = null;
$categoryIds = [];
$categoryParentId = null;

if ($categoryParam !== '') {
    if (ctype_digit($categoryParam)) {
        $catStmt = $db->prepare('SELECT * FROM categories WHERE id = :id AND is_active = 1 LIMIT 1');
        $catStmt->execute(['id' => (int) $categoryParam]);
    } else {
        $catStmt = $db->prepare('SELECT * FROM categories WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $catStmt->execute(['slug' => $categoryParam]);
    }
    $category = $catStmt->fetch() ?: null;
}



if ($category) {
    $categoryIds[] = (int) $category['id'];
    $categoryParentId = $category['parent_id'] ? (int) $category['parent_id'] : (int) $category['id'];

    $childStmt = $db->prepare('SELECT id FROM categories WHERE parent_id = :parent_id AND is_active = 1');
    $childStmt->execute(['parent_id' => (int) $category['id']]);
    foreach ($childStmt->fetchAll() as $child) {
        $categoryIds[] = (int) $child['id'];
    }
}

$sidebarParentId = $categoryParentId;
$subcatSql = 'SELECT c.id, c.name, c.slug, COUNT(p.id) AS products_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id AND p.status = "active"
     WHERE c.is_active = 1 ';
if ($sidebarParentId !== null) {
    $subcatSql .= ' AND c.parent_id = :parent_id';
} else {
    $subcatSql .= ' AND c.parent_id IS NULL';
}
$subcatSql .= ' GROUP BY c.id ORDER BY c.sort_order ASC, c.id ASC';
$subcatStmt = $db->prepare($subcatSql);
if ($sidebarParentId !== null) {
    $subcatStmt->execute(['parent_id' => (int) $sidebarParentId]);
} else {
    $subcatStmt->execute();
}
$subcategories = $subcatStmt->fetchAll();

$allowedCategoryIds = $categoryIds;
if ($subcatFilters !== []) {
    $validSubcats = array_map(static fn($row) => (int) $row['id'], $subcategories);
    $selectedValid = array_values(array_intersect($subcatFilters, $validSubcats));
    if ($selectedValid !== []) {
        $allowedCategoryIds = $selectedValid;
    }
}

$where = ['p.status = "active"'];
$params = [];

if ($allowedCategoryIds !== []) {
    $catPlaceholders = [];
    foreach ($allowedCategoryIds as $idx => $catId) {
        $key = 'cat' . $idx;
        $catPlaceholders[] = ':' . $key;
        $params[$key] = $catId;
    }
    $where[] = 'p.category_id IN (' . implode(',', $catPlaceholders) . ')';
}

if ($keyword !== '') {
    $where[] = '(p.name LIKE :kw1 OR p.brand LIKE :kw2 OR p.short_description LIKE :kw3 OR c.name LIKE :kw4)';
    $params['kw1'] = '%' . $keyword . '%';
    $params['kw2'] = '%' . $keyword . '%';
    $params['kw3'] = '%' . $keyword . '%';
    $params['kw4'] = '%' . $keyword . '%';
}

if ($shapeFilters !== []) {
    $shapePlaceholders = [];
    foreach ($shapeFilters as $idx => $shape) {
        $key = 'shape' . $idx;
        $shapePlaceholders[] = ':' . $key;
        $params[$key] = $shape;
    }
    $where[] = 'p.shape IN (' . implode(',', $shapePlaceholders) . ')';
}

if ($materialFilters !== []) {
    $materialPlaceholders = [];
    foreach ($materialFilters as $idx => $material) {
        $key = 'material' . $idx;
        $materialPlaceholders[] = ':' . $key;
        $params[$key] = $material;
    }
    $where[] = 'p.material IN (' . implode(',', $materialPlaceholders) . ')';
}

if ($genderFilters !== []) {
    $genderPlaceholders = [];
    foreach ($genderFilters as $idx => $gender) {
        $key = 'gender' . $idx;
        $genderPlaceholders[] = ':' . $key;
        $params[$key] = $gender;
    }
    $where[] = 'p.target_gender IN (' . implode(',', $genderPlaceholders) . ')';
}

if ($minPrice !== null) {
    $where[] = 'p.default_price >= :min_price';
    $params['min_price'] = $minPrice;
}
if ($maxPrice !== null) {
    $where[] = 'p.default_price <= :max_price';
    $params['max_price'] = $maxPrice;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$orderSql = match ($sort) {
    'price_asc' => 'p.default_price ASC, p.id DESC',
    'price_desc' => 'p.default_price DESC, p.id DESC',
    'name_asc' => 'p.name ASC',
    default => 'p.id DESC',
};

$countSql = "SELECT COUNT(DISTINCT p.id)
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             $whereSql";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalProducts / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$productSql = "SELECT p.id, p.name, p.slug, p.brand, p.default_price, p.compare_at_price,
                      COALESCE(NULLIF(p.thumbnail, ''), pi.image_url) AS thumbnail,
                      p.short_description, p.shape, p.material, p.target_gender,
                      c.name AS category_name, c.slug AS category_slug,
                      vc.variant_colors
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
               LEFT JOIN (
                  SELECT product_id, MIN(image_url) AS image_url
                  FROM product_images
                  WHERE image_url IS NOT NULL AND image_url <> ''
                  GROUP BY product_id
               ) pi ON pi.product_id = p.id
               LEFT JOIN (
                  SELECT product_id, GROUP_CONCAT(DISTINCT color ORDER BY color SEPARATOR '||') AS variant_colors
                  FROM product_variants
                  WHERE is_active = 1 AND color IS NOT NULL AND color <> ''
                  GROUP BY product_id
               ) vc ON vc.product_id = p.id
               $whereSql
               ORDER BY $orderSql
               LIMIT :limit OFFSET :offset";
$productStmt = $db->prepare($productSql);
foreach ($params as $key => $value) {
    $type = is_int($value) || is_float($value) ? PDO::PARAM_STR : PDO::PARAM_STR;
    $productStmt->bindValue(':' . $key, $value, $type);
}
$productStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$productStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$productStmt->execute();
$products = $productStmt->fetchAll();

function lumina_distinct_options(PDO $db, string $column, array $categoryIds): array
{
    if (!in_array($column, ['shape', 'material'], true)) return [];
    $params = [];
    $where = ['status = "active"', "$column IS NOT NULL", "$column <> ''"];
    if ($categoryIds !== []) {
        $holders = [];
        foreach ($categoryIds as $idx => $catId) {
            $key = 'catOpt' . $idx;
            $holders[] = ':' . $key;
            $params[$key] = $catId;
        }
        $where[] = 'category_id IN (' . implode(',', $holders) . ')';
    }
    $sql = 'SELECT DISTINCT ' . $column . ' AS value FROM products WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $column . ' ASC LIMIT 20';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return array_values(array_filter(array_map(static fn($row) => (string) $row['value'], $stmt->fetchAll())));
}

$shapeOptions = lumina_distinct_options($db, 'shape', $categoryIds);
$materialOptions = lumina_distinct_options($db, 'material', $categoryIds);

$categoryName = $category['name'] ?? 'Cửa hàng';
$categorySlug = $category['slug'] ?? 'cua-hang';
$defaultDescriptions = [
    'gong-kinh' => 'Khám phá bộ sưu tập gọng kính được chế tác với độ chính xác cao, kết hợp giữa phong cách cổ điển và công nghệ vật liệu hiện đại.',
    'kinh-mat' => 'Khám phá bộ sưu tập kính mát cao cấp với thiết kế tinh tế, bảo vệ mắt tốt và phù hợp nhiều phong cách.',
    'trong-kinh' => 'Lựa chọn tròng kính phù hợp với nhu cầu sử dụng hằng ngày: chống ánh sáng xanh, đổi màu, siêu mỏng và đa tròng.',
    'cua-hang' => 'Khám phá tất cả sản phẩm kính mắt thời trang và phụ kiện tại LUMINA.',
];
$description = trim((string) ($category['description'] ?? ''));
if ($description === '') {
    $description = $defaultDescriptions[$categorySlug] ?? 'Khám phá bộ sưu tập sản phẩm kính mắt LUMINA được chọn lọc theo danh mục.';
}

// Build active filter tags for display
$activeFilters = [];
foreach ($subcatFilters as $subcatId) {
    foreach ($subcategories as $subcat) {
        if ((int) $subcat['id'] === $subcatId) {
            $activeFilters[] = ['label' => $subcat['name'], 'remove' => lumina_atelier_url(['subcat' => array_values(array_filter($subcatFilters, fn($v) => $v !== $subcatId)), 'page' => null])];
        }
    }
}
foreach ($genderFilters as $g) {
    $activeFilters[] = ['label' => $genderLabels[$g] ?? $g, 'remove' => lumina_atelier_url(['gender' => array_values(array_filter($genderFilters, fn($v) => $v !== $g)), 'page' => null])];
}
foreach ($shapeFilters as $s) {
    $activeFilters[] = ['label' => $s, 'remove' => lumina_atelier_url(['shape' => array_values(array_filter($shapeFilters, fn($v) => $v !== $s)), 'page' => null])];
}
foreach ($materialFilters as $m) {
    $activeFilters[] = ['label' => $m, 'remove' => lumina_atelier_url(['material' => array_values(array_filter($materialFilters, fn($v) => $v !== $m)), 'page' => null])];
}
if ($minPrice !== null) {
    $activeFilters[] = ['label' => 'Từ ' . number_format((int)$minPrice) . 'đ', 'remove' => lumina_atelier_url(['min_price' => null, 'page' => null])];
}
if ($maxPrice !== null) {
    $activeFilters[] = ['label' => 'Đến ' . number_format((int)$maxPrice) . 'đ', 'remove' => lumina_atelier_url(['max_price' => null, 'page' => null])];
}

$pageTitle = $categoryName . ' - ' . APP_NAME;
$pageDescription = $description;
require_once BASE_PATH . '/app/views/partials/head.php';
require_once BASE_PATH . '/app/views/partials/header.php';
?>
<style>
/* Hero banner for product category pages */
.cat-hero {
  position: relative;
  width: 100%;
  overflow: hidden;
  max-height: 340px;
  background: #1a2e4a;
}
.cat-hero img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
  max-height: 340px;
}
.cat-hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, rgba(26,46,74,.55) 0%, rgba(26,46,74,.05) 60%);
  display: flex;
  align-items: center;
  padding: 0 clamp(1.5rem, 5vw, 5rem);
}
.cat-hero-text {
  color: #fff;
  text-shadow: 0 2px 8px rgba(0,0,0,.3);
}
.cat-hero-text h1 {
  font-size: clamp(2rem, 4vw, 3rem);
  font-weight: 900;
  margin: 0;
  letter-spacing: -.01em;
}
</style>

<?php
// Map category slug to banner image filename
$heroBannerMap = [
    'cua-hang'  => 'cua_hang.jpg',
    'gong-kinh' => 'gong-kinh.jpg',
    'kinh-mat'  => 'kinh-mat.png',
    'trong-kinh'=> 'trong-kinh.png',
];
$heroBanner = $heroBannerMap[$categorySlug] ?? null;
?>
<?php if ($heroBanner): ?>
<div class="cat-hero">
  <img src="<?= e(APP_URL) ?>/assets/images/<?= e($heroBanner) ?>" alt="<?= e($categoryName) ?>">
</div>
<?php endif; ?>

<main class="atelier-page">
  <div class="atelier-main">


    <!-- Horizontal Filter Bar -->
    <form class="filter-bar-form" action="<?= e(APP_URL) ?>/products.php" method="get" id="atelierFilterForm">
      <?php if ($category): ?>
        <input type="hidden" name="category" value="<?= e($categorySlug) ?>">
      <?php endif; ?>
      <?php if ($keyword !== ''): ?>
        <input type="hidden" name="keyword" value="<?= e($keyword) ?>">
      <?php endif; ?>
      <input type="hidden" name="sort" value="<?= e($sort) ?>">

      <div class="filter-bar">
        <span class="filter-bar-label">Bộ lọc</span>

        <?php if ($subcategories !== []): ?>
        <!-- Danh mục sản phẩm -->
        <div class="filter-dropdown" id="filterDropdownSubcat">
          <button type="button" class="filter-dropdown-btn <?= $subcatFilters !== [] ? 'is-active' : '' ?>" onclick="toggleFilterDropdown('filterDropdownSubcat')">
            Danh mục sản phẩm
            <?php if ($subcatFilters !== []): ?><span class="filter-count"><?= count($subcatFilters) ?></span><?php endif; ?>
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2.5 5L7 9.5L11.5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <div class="filter-dropdown-menu">
            <?php foreach ($subcategories as $subcat): ?>
              <label class="filter-dropdown-item">
                <input type="checkbox" name="subcat[]" value="<?= (int) $subcat['id'] ?>" <?= in_array((int) $subcat['id'], $subcatFilters, true) ? 'checked' : '' ?>>
                <span><?= e($subcat['name']) ?></span>
                <small><?= (int) $subcat['products_count'] ?></small>
              </label>
            <?php endforeach; ?>
            <div class="filter-dropdown-actions">
              <button type="submit" class="filter-apply-btn">Áp dụng</button>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Giới tính -->
        <div class="filter-dropdown" id="filterDropdownGender">
          <button type="button" class="filter-dropdown-btn <?= $genderFilters !== [] ? 'is-active' : '' ?>" onclick="toggleFilterDropdown('filterDropdownGender')">
            Giới tính
            <?php if ($genderFilters !== []): ?><span class="filter-count"><?= count($genderFilters) ?></span><?php endif; ?>
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2.5 5L7 9.5L11.5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <div class="filter-dropdown-menu">
            <?php foreach ($genderLabels as $gVal => $gLabel): ?>
              <label class="filter-dropdown-item">
                <input type="checkbox" name="gender[]" value="<?= e($gVal) ?>" <?= in_array($gVal, $genderFilters, true) ? 'checked' : '' ?>>
                <span><?= e($gLabel) ?></span>
              </label>
            <?php endforeach; ?>
            <div class="filter-dropdown-actions">
              <button type="submit" class="filter-apply-btn">Áp dụng</button>
            </div>
          </div>
        </div>

        <?php if ($shapeOptions !== []): ?>
        <!-- Kiểu dáng -->
        <div class="filter-dropdown" id="filterDropdownShape">
          <button type="button" class="filter-dropdown-btn <?= $shapeFilters !== [] ? 'is-active' : '' ?>" onclick="toggleFilterDropdown('filterDropdownShape')">
            Kiểu dáng
            <?php if ($shapeFilters !== []): ?><span class="filter-count"><?= count($shapeFilters) ?></span><?php endif; ?>
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2.5 5L7 9.5L11.5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <div class="filter-dropdown-menu">
            <?php foreach ($shapeOptions as $shape): ?>
              <label class="filter-dropdown-item">
                <input type="checkbox" name="shape[]" value="<?= e($shape) ?>" <?= in_array($shape, $shapeFilters, true) ? 'checked' : '' ?>>
                <span><?= e($shape) ?></span>
              </label>
            <?php endforeach; ?>
            <div class="filter-dropdown-actions">
              <button type="submit" class="filter-apply-btn">Áp dụng</button>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($materialOptions !== []): ?>
        <!-- Chất liệu -->
        <div class="filter-dropdown" id="filterDropdownMaterial">
          <button type="button" class="filter-dropdown-btn <?= $materialFilters !== [] ? 'is-active' : '' ?>" onclick="toggleFilterDropdown('filterDropdownMaterial')">
            Chất liệu
            <?php if ($materialFilters !== []): ?><span class="filter-count"><?= count($materialFilters) ?></span><?php endif; ?>
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2.5 5L7 9.5L11.5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <div class="filter-dropdown-menu">
            <?php foreach ($materialOptions as $material): ?>
              <label class="filter-dropdown-item">
                <input type="checkbox" name="material[]" value="<?= e($material) ?>" <?= in_array($material, $materialFilters, true) ? 'checked' : '' ?>>
                <span><?= e($material) ?></span>
              </label>
            <?php endforeach; ?>
            <div class="filter-dropdown-actions">
              <button type="submit" class="filter-apply-btn">Áp dụng</button>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Khoảng giá -->
        <div class="filter-dropdown" id="filterDropdownPrice">
          <button type="button" class="filter-dropdown-btn <?= ($minPrice !== null || $maxPrice !== null) ? 'is-active' : '' ?>" onclick="toggleFilterDropdown('filterDropdownPrice')">
            Khoảng giá
            <?php if ($minPrice !== null || $maxPrice !== null): ?><span class="filter-count">✓</span><?php endif; ?>
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2.5 5L7 9.5L11.5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <div class="filter-dropdown-menu filter-price-menu">
            <div class="filter-price-inputs">
              <div class="filter-price-field">
                <label>Từ (đ)</label>
                <input type="number" min="0" step="50000" name="min_price" value="<?= e($minPrice !== null ? (string) (int) $minPrice : '') ?>" placeholder="0">
              </div>
              <div class="filter-price-sep">—</div>
              <div class="filter-price-field">
                <label>Đến (đ)</label>
                <input type="number" min="0" step="50000" name="max_price" value="<?= e($maxPrice !== null ? (string) (int) $maxPrice : '') ?>" placeholder="5.000.000">
              </div>
            </div>
            <div class="filter-price-presets">
              <button type="button" class="filter-preset-btn" onclick="setPricePreset(0, 500000)">Dưới 500K</button>
              <button type="button" class="filter-preset-btn" onclick="setPricePreset(500000, 1000000)">500K – 1Tr</button>
              <button type="button" class="filter-preset-btn" onclick="setPricePreset(1000000, 2000000)">1Tr – 2Tr</button>
              <button type="button" class="filter-preset-btn" onclick="setPricePreset(2000000, '')">Trên 2Tr</button>
            </div>
            <div class="filter-dropdown-actions">
              <button type="submit" class="filter-apply-btn">Áp dụng</button>
            </div>
          </div>
        </div>

        <?php if ($activeFilters !== []): ?>
        <a class="filter-clear-all" href="<?= e(APP_URL) ?>/products.php<?= $category ? '?category=' . e($categorySlug) : '' ?>">Xóa tất cả</a>
        <?php endif; ?>
      </div><!-- /.filter-bar -->
    </form>

    <!-- Active filter tags -->
    <?php if ($activeFilters !== []): ?>
    <div class="filter-active-tags">
      <?php foreach ($activeFilters as $tag): ?>
        <a class="filter-tag" href="<?= e($tag['remove']) ?>">
          <?= e($tag['label']) ?> <span>×</span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Product listing -->
    <div class="atelier-layout atelier-layout--full">
      <section class="atelier-content" aria-label="Danh sách sản phẩm">
        <div class="atelier-content-top">
          <span class="atelier-count"><?= $totalProducts ?> sản phẩm</span>
          <form class="atelier-sort" action="<?= e(APP_URL) ?>/products.php" method="get">
            <?php foreach ($_GET as $key => $value): ?>
              <?php if ($key === 'sort' || $key === 'page') continue; ?>
              <?php if (is_array($value)): ?>
                <?php foreach ($value as $subValue): ?>
                  <input type="hidden" name="<?= e($key) ?>[]" value="<?= e((string) $subValue) ?>">
                <?php endforeach; ?>
              <?php else: ?>
                <input type="hidden" name="<?= e($key) ?>" value="<?= e((string) $value) ?>">
              <?php endif; ?>
            <?php endforeach; ?>
            <span>Sắp xếp:</span>
            <select name="sort" onchange="this.form.submit()">
              <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Mới nhất</option>
              <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Giá tăng dần</option>
              <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Giá giảm dần</option>
              <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Tên A-Z</option>
            </select>
          </form>
        </div>

        <?php if ($products !== []): ?>
          <div class="atelier-products-grid atelier-products-grid--wide">
            <?php foreach ($products as $product): ?>
              <?php
                $colors = [];
                if (!empty($product['variant_colors'])) {
                    $colors = array_values(array_filter(explode('||', (string) $product['variant_colors'])));
                }
                if ($colors === []) {
                    $colors = array_filter([(string) ($product['material'] ?? ''), (string) ($product['shape'] ?? ''), (string) ($product['category_name'] ?? '')]);
                }
                $colors = array_slice($colors, 0, 4);
              ?>
              <article class="atelier-product-card">
                <a href="<?= e(APP_URL) ?>/product-detail.php?id=<?= (int) $product['id'] ?>" aria-label="Xem <?= e($product['name']) ?>">
                  <div class="atelier-product-image">
                    <img src="<?= e(lumina_atelier_img($product['thumbnail'], $placeholderImage)) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
                    <span class="atelier-heart" aria-hidden="true">♡</span>
                  </div>
                  <div class="atelier-product-info">
                    <div class="atelier-product-main">
                      <div>
                        <h2 class="atelier-product-name"><?= e($product['name']) ?></h2>
                        <p class="atelier-product-sub"><?= e($product['brand'] ?: ($product['category_name'] ?: 'LUMINA')) ?></p>
                      </div>
                      <div class="atelier-product-price">
                        <?= e(format_price($product['default_price'])) ?>
                        <?php if (!empty($product['compare_at_price']) && (float) $product['compare_at_price'] > (float) $product['default_price']): ?>
                          <span class="atelier-old-price"><?= e(format_price($product['compare_at_price'])) ?></span>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="atelier-color-row" aria-label="Màu sản phẩm">
                      <?php foreach ($colors as $colorName): ?>
                        <span class="atelier-color-dot" title="<?= e($colorName) ?>" style="background: <?= e(lumina_atelier_dot_color($colorName)) ?>"></span>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </a>
              </article>
            <?php endforeach; ?>
          </div>

          <?php if ($totalPages > 1): ?>
            <nav class="atelier-pagination" aria-label="Phân trang sản phẩm">
              <a class="atelier-page-link <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= e(lumina_atelier_url(['page' => max(1, $page - 1)])) ?>">‹</a>
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i > 3 && $i < $totalPages && abs($i - $page) > 1): ?>
                  <?php if ($i === 4): ?><span class="atelier-page-link is-disabled">...</span><?php endif; ?>
                  <?php continue; ?>
                <?php endif; ?>
                <a class="atelier-page-link <?= $i === $page ? 'is-active' : '' ?>" href="<?= e(lumina_atelier_url(['page' => $i])) ?>"><?= $i ?></a>
              <?php endfor; ?>
              <a class="atelier-page-link <?= $page >= $totalPages ? 'is-disabled' : '' ?>" href="<?= e(lumina_atelier_url(['page' => min($totalPages, $page + 1)])) ?>">›</a>
            </nav>
          <?php endif; ?>
        <?php else: ?>
          <div class="atelier-empty">
            <p>Không có sản phẩm phù hợp với bộ lọc hiện tại.</p>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>
</main>

<script>
function toggleFilterDropdown(id) {
  var all = document.querySelectorAll('.filter-dropdown');
  all.forEach(function(el) {
    if (el.id !== id) {
      el.classList.remove('is-open');
    }
  });
  document.getElementById(id).classList.toggle('is-open');
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
  if (!e.target.closest('.filter-dropdown')) {
    document.querySelectorAll('.filter-dropdown').forEach(function(el) {
      el.classList.remove('is-open');
    });
  }
});

function setPricePreset(min, max) {
  var minInput = document.querySelector('input[name="min_price"]');
  var maxInput = document.querySelector('input[name="max_price"]');
  if (minInput) minInput.value = min;
  if (maxInput) maxInput.value = max;
}
</script>
<?php require_once BASE_PATH . '/app/views/partials/footer.php'; ?>
