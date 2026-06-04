<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/products.php');
    exit;
}

$db       = Database::connect();
$variantId = isset($_POST['variant_id']) ? (int) $_POST['variant_id'] : 0;
$quantity  = isset($_POST['quantity'])   ? (int) $_POST['quantity']   : 1;
$quantity  = max(1, min(99, $quantity));

// Backwards compat: if only product_id was provided, grab first active variant
if ($variantId <= 0) {
    $productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    if ($productId > 0) {
        $findVariantStmt = $db->prepare("SELECT id FROM product_variants WHERE product_id = :product_id AND is_active = 1 ORDER BY id ASC LIMIT 1");
        $findVariantStmt->execute(['product_id' => $productId]);
        $variantId = (int) $findVariantStmt->fetchColumn();
    }
}

if ($variantId <= 0) {
    if (isAjax()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vui lòng chọn biến thể sản phẩm']);
        exit;
    }
    header('Location: ' . APP_URL . '/products.php');
    exit;
}

$stmt = $db->prepare(
    "SELECT pv.id AS variant_id, pv.sku, pv.color, pv.size_label, pv.price AS variant_price,
            pv.stock_quantity, pv.is_preorder_allowed, pv.image_override,
            p.id AS product_id, p.name AS product_name, p.brand, p.thumbnail AS product_thumbnail
     FROM product_variants pv
     INNER JOIN products p ON p.id = pv.product_id
     WHERE pv.id = :variant_id AND pv.is_active = 1 AND p.status = 'active'
     LIMIT 1"
);
$stmt->execute(['variant_id' => $variantId]);
$itemData = $stmt->fetch();

if (!$itemData) {
    if (isAjax()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại']);
        exit;
    }
    header('Location: ' . APP_URL . '/products.php');
    exit;
}

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize cart properly
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Clean up any malformed cart entries (legacy format without cart_key)
foreach ($_SESSION['cart'] as $key => $item) {
    if (!is_array($item) || !isset($item['variant_id'])) {
        unset($_SESSION['cart'][$key]);
    }
}

// Use variant_id as the unique cart key
$cartKey = 'v_' . $variantId;

if (isset($_SESSION['cart'][$cartKey])) {
    $_SESSION['cart'][$cartKey]['quantity'] += $quantity;
} else {
    $_SESSION['cart'][$cartKey] = [
        'cart_key'   => $cartKey,
        'id'         => (int) $itemData['variant_id'],
        'product_id' => (int) $itemData['product_id'],
        'variant_id' => (int) $itemData['variant_id'],
        'sku'        => $itemData['sku'],
        'name'       => $itemData['product_name'],
        'brand'      => $itemData['brand'] ?: 'LUMINA',
        'color'      => $itemData['color'],
        'size_label' => $itemData['size_label'],
        'price'      => (float) $itemData['variant_price'],
        'thumbnail'  => $itemData['image_override'] ?: $itemData['product_thumbnail'],
        'quantity'   => $quantity,
    ];
}

// Cart count for response
$cartCount = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartCount += (int) ($item['quantity'] ?? 0);
}

// If AJAX request — return JSON, no redirect
if (isAjax()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success'    => true,
        'message'    => 'Đã thêm vào giỏ hàng',
        'cart_count' => $cartCount,
    ]);
    exit;
}

// Check if buy now
$buyNow = isset($_POST['buynow']) && $_POST['buynow'] === '1';

if ($buyNow) {
    header('Location: ' . APP_URL . '/cart.php');
} else {
    header('Location: ' . APP_URL . '/cart.php?added=1');
}
exit;

function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}
