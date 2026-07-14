<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/checkout.php');
    exit;
}

ensure_cart_session();

if (empty($_SESSION['cart'])) {
    flash_set('checkout_errors', ['Giỏ hàng đang trống.']);
    header('Location: ' . APP_URL . '/cart.php');
    exit;
}

$input = [
    'customer_name' => trim((string) ($_POST['customer_name'] ?? '')),
    'customer_phone' => trim((string) ($_POST['customer_phone'] ?? '')),
    'customer_email' => trim((string) ($_POST['customer_email'] ?? '')),
    'shipping_address_line' => trim((string) ($_POST['shipping_address_line'] ?? '')),
    'shipping_ward' => trim((string) ($_POST['shipping_ward'] ?? '')),
    'shipping_district' => trim((string) ($_POST['shipping_district'] ?? '')),
    'shipping_province' => trim((string) ($_POST['shipping_province'] ?? '')),
    'postal_code' => trim((string) ($_POST['postal_code'] ?? '')),
    'order_type' => trim((string) ($_POST['order_type'] ?? 'available')),
    'payment_method' => trim((string) ($_POST['payment_method'] ?? 'cod')),
    'note' => trim((string) ($_POST['note'] ?? '')),
];

$errors = [];
if ($input['customer_name'] === '') {
    $errors[] = 'Bạn chưa nhập họ và tên.';
}
if ($input['customer_phone'] === '') {
    $errors[] = 'Bạn chưa nhập số điện thoại.';
}
if ($input['customer_email'] === '' || !filter_var($input['customer_email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email không hợp lệ.';
}
if ($input['shipping_address_line'] === '') {
    $errors[] = 'Bạn chưa nhập địa chỉ giao hàng.';
}

$allowedOrderTypes = ['available', 'preorder', 'prescription'];
$allowedPaymentMethods = ['cod', 'bank_transfer', 'momo', 'vnpay', 'other'];

if (!in_array($input['order_type'], $allowedOrderTypes, true)) {
    $errors[] = 'Loại đơn hàng không hợp lệ.';
}
if (!in_array($input['payment_method'], $allowedPaymentMethods, true)) {
    $errors[] = 'Phương thức thanh toán không hợp lệ.';
}

if ($errors !== []) {
    set_old_input($input);
    flash_set('checkout_errors', $errors);
    header('Location: ' . APP_URL . '/checkout.php');
    exit;
}

$db = Database::connect();
$cartItems = $_SESSION['cart'];
$variantIds = array_map('intval', array_keys($cartItems));

if ($variantIds === []) {
    flash_set('checkout_errors', ['Giỏ hàng đang trống.']);
    header('Location: ' . APP_URL . '/cart.php');
    exit;
}

// Lấy danh sách product_id từ giỏ hàng
$productIds = array_unique(array_filter(array_column($cartItems, 'product_id')));

if ($productIds === []) {
    flash_set('checkout_errors', ['Giỏ hàng chứa thông tin không hợp lệ.']);
    header('Location: ' . APP_URL . '/cart.php');
    exit;
}

$productIdPlaceholders = implode(',', array_fill(0, count($productIds), '?'));
$productStmt = $db->prepare(
    "SELECT p.id, p.name, p.slug, p.brand, p.default_price, p.material, p.shape, p.frame_type, p.thumbnail,
            p.is_prescription_supported, p.status, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.id IN ($productIdPlaceholders) AND p.status = 'active'"
);
$productStmt->execute($productIds);
$products = [];
foreach ($productStmt->fetchAll() as $row) {
    $products[(int) $row['id']] = $row;
}

$missingProducts = [];
foreach ($productIds as $productId) {
    if (!isset($products[$productId])) {
        $missingProducts[] = $productId;
    }
}

if ($missingProducts !== []) {
    flash_set('checkout_errors', ['Có sản phẩm trong giỏ hàng không còn tồn tại hoặc đã ngừng bán.']);
    header('Location: ' . APP_URL . '/cart.php');
    exit;
}

try {
    $db->beginTransaction();

    $roleStmt = $db->prepare("SELECT id FROM roles WHERE name = 'customer' LIMIT 1");
    $roleStmt->execute();
    $customerRoleId = (int) $roleStmt->fetchColumn();
    if ($customerRoleId <= 0) {
        throw new RuntimeException('Không tìm thấy role customer trong database.');
    }

    $userStmt = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $userStmt->execute(['email' => $input['customer_email']]);
    $userId = (int) $userStmt->fetchColumn();

    if ($userId > 0) {
        $updateUserStmt = $db->prepare(
            'UPDATE users
             SET full_name = :full_name,
                 phone = :phone,
                 address_line = :address_line,
                 ward = :ward,
                 district = :district,
                 province = :province,
                 postal_code = :postal_code,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $updateUserStmt->execute([
            'full_name' => $input['customer_name'],
            'phone' => $input['customer_phone'],
            'address_line' => $input['shipping_address_line'],
            'ward' => $input['shipping_ward'] ?: null,
            'district' => $input['shipping_district'] ?: null,
            'province' => $input['shipping_province'] ?: null,
            'postal_code' => $input['postal_code'] ?: null,
            'id' => $userId,
        ]);
    } else {
        $insertUserStmt = $db->prepare(
            'INSERT INTO users (
                role_id, full_name, email, phone, password_hash,
                address_line, ward, district, province, postal_code, status
             ) VALUES (
                :role_id, :full_name, :email, :phone, :password_hash,
                :address_line, :ward, :district, :province, :postal_code, :status
             )'
        );
        $insertUserStmt->execute([
            'role_id' => $customerRoleId,
            'full_name' => $input['customer_name'],
            'email' => $input['customer_email'],
            'phone' => $input['customer_phone'],
            'password_hash' => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
            'address_line' => $input['shipping_address_line'],
            'ward' => $input['shipping_ward'] ?: null,
            'district' => $input['shipping_district'] ?: null,
            'province' => $input['shipping_province'] ?: null,
            'postal_code' => $input['postal_code'] ?: null,
            'status' => 'active',
        ]);
        $userId = (int) $db->lastInsertId();
    }

    $subtotal = 0.0;
    foreach ($cartItems as $cartItem) {
        $subtotal += ((float) ($cartItem['price'] ?? 0)) * ((int) ($cartItem['quantity'] ?? 0));
    }

    $shippingFee = 0.0;
    $discountAmount = 0.0;
    $lensTotal = 0.0;
    $totalAmount = $subtotal + $shippingFee + $lensTotal - $discountAmount;

    $status = match ($input['order_type']) {
        'preorder' => 'awaiting_stock',
        'prescription' => 'checking_prescription',
        default => 'pending',
    };

    do {
        $orderCode = generate_order_code();
        $checkOrderCodeStmt = $db->prepare('SELECT COUNT(*) FROM orders WHERE order_code = :order_code');
        $checkOrderCodeStmt->execute(['order_code' => $orderCode]);
        $orderCodeExists = (int) $checkOrderCodeStmt->fetchColumn() > 0;
    } while ($orderCodeExists);

    $insertOrderStmt = $db->prepare(
        'INSERT INTO orders (
            user_id, order_code, order_type, status,
            customer_name, customer_email, customer_phone,
            shipping_address_line, shipping_ward, shipping_district, shipping_province, postal_code,
            note, subtotal, lens_total, shipping_fee, discount_amount, total_amount,
            payment_method, payment_status
         ) VALUES (
            :user_id, :order_code, :order_type, :status,
            :customer_name, :customer_email, :customer_phone,
            :shipping_address_line, :shipping_ward, :shipping_district, :shipping_province, :postal_code,
            :note, :subtotal, :lens_total, :shipping_fee, :discount_amount, :total_amount,
            :payment_method, :payment_status
         )'
    );
    $insertOrderStmt->execute([
        'user_id' => $userId,
        'order_code' => $orderCode,
        'order_type' => $input['order_type'],
        'status' => $status,
        'customer_name' => $input['customer_name'],
        'customer_email' => $input['customer_email'],
        'customer_phone' => $input['customer_phone'],
        'shipping_address_line' => $input['shipping_address_line'],
        'shipping_ward' => $input['shipping_ward'] ?: null,
        'shipping_district' => $input['shipping_district'] ?: null,
        'shipping_province' => $input['shipping_province'] ?: null,
        'postal_code' => $input['postal_code'] ?: null,
        'note' => $input['note'] ?: null,
        'subtotal' => $subtotal,
        'lens_total' => $lensTotal,
        'shipping_fee' => $shippingFee,
        'discount_amount' => $discountAmount,
        'total_amount' => $totalAmount,
        'payment_method' => $input['payment_method'],
        'payment_status' => 'unpaid',
    ]);
    $orderId = (int) $db->lastInsertId();

    $findVariantByIdStmt = $db->prepare(
        'SELECT id, sku, color, size_label, material, price, stock_quantity, is_preorder_allowed
         FROM product_variants
         WHERE id = :variant_id AND is_active = 1
         LIMIT 1'
    );

    $insertOrderItemStmt = $db->prepare(
        'INSERT INTO order_items (
            order_id, product_variant_id, lens_option_id, product_name, variant_sku,
            variant_snapshot, lens_snapshot, quantity, unit_price, lens_price, line_total
         ) VALUES (
            :order_id, :product_variant_id, :lens_option_id, :product_name, :variant_sku,
            :variant_snapshot, :lens_snapshot, :quantity, :unit_price, :lens_price, :line_total
         )'
    );

    $decreaseStockStmt = $db->prepare(
        'UPDATE product_variants
         SET stock_quantity = stock_quantity - :quantity
         WHERE id = :variant_id
           AND stock_quantity >= :quantity'
    );

    $hasPreorderItem = false;

    foreach ($cartItems as $cartItem) {
        $productId = (int) ($cartItem['product_id'] ?? 0);
        $variantId = (int) ($cartItem['variant_id'] ?? 0);
        $product = $products[$productId];

        $findVariantByIdStmt->execute(['variant_id' => $variantId]);
        $variant = $findVariantByIdStmt->fetch();

        if (!$variant) {
            throw new RuntimeException('Không tìm thấy biến thể sản phẩm mang ID: ' . $variantId);
        }

        // Kiểm tra xem biến thể này có phải là hàng preorder không
        $quantity = max(1, (int) ($cartItem['quantity'] ?? 1));
        $stockQuantity = (int) ($variant['stock_quantity'] ?? 0);
        $isPreorderAllowed = (int) ($variant['is_preorder_allowed'] ?? 0) === 1;
        $stockDecreased = false;

        if ($stockQuantity >= $quantity) {
            $decreaseStockStmt->execute([
                'quantity' => $quantity,
                'variant_id' => (int) $variant['id'],
            ]);

            if ($decreaseStockStmt->rowCount() !== 1) {
                throw new RuntimeException('San pham "' . $product['name'] . '" khong du so luong ton kho.');
            }

            $stockDecreased = true;
        } elseif ($isPreorderAllowed) {
            $hasPreorderItem = true;
        } else {
            throw new RuntimeException('San pham "' . $product['name'] . '" chi con ' . $stockQuantity . ' san pham trong kho.');
        }

        $unitPrice = (float) ($cartItem['price'] ?? $variant['price'] ?? $product['default_price']);
        $lineTotal = $unitPrice * $quantity;

        $variantSnapshot = json_encode([
            'product_id' => (int) $product['id'],
            'product_slug' => $product['slug'],
            'brand' => $product['brand'],
            'category_name' => $product['category_name'],
            'shape' => $product['shape'],
            'frame_type' => $product['frame_type'],
            'thumbnail' => $product['thumbnail'],
            'variant_id' => (int) $variant['id'],
            'sku' => $variant['sku'],
            'color' => $variant['color'],
            'size_label' => $variant['size_label'],
            'material' => $variant['material'],
            'stock_decreased' => $stockDecreased,
        ], JSON_UNESCAPED_UNICODE);

        $insertOrderItemStmt->execute([
            'order_id' => $orderId,
            'product_variant_id' => (int) $variant['id'],
            'lens_option_id' => null,
            'product_name' => $product['name'],
            'variant_sku' => $variant['sku'],
            'variant_snapshot' => $variantSnapshot,
            'lens_snapshot' => null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'lens_price' => 0,
            'line_total' => $lineTotal,
        ]);
    }

    // Tự động chuyển trạng thái đơn hàng nếu chứa mặt hàng pre-order
    if ($hasPreorderItem) {
        $updateOrderStmt = $db->prepare(
            "UPDATE orders 
             SET status = 'awaiting_stock', order_type = 'preorder' 
             WHERE id = :id"
        );
        $updateOrderStmt->execute(['id' => $orderId]);

        $updateLogStmt = $db->prepare(
            "UPDATE order_status_logs 
             SET new_status = 'awaiting_stock', 
                 note = 'Đơn hàng tự động chuyển sang chờ nhập hàng do có sản phẩm đặt trước (preorder).' 
             WHERE order_id = :id AND old_status IS NULL"
        );
        $updateLogStmt->execute(['id' => $orderId]);
    }

    $insertStatusLogStmt = $db->prepare(
        'INSERT INTO order_status_logs (order_id, changed_by, old_status, new_status, note)
         VALUES (:order_id, :changed_by, :old_status, :new_status, :note)'
    );
    $insertStatusLogStmt->execute([
        'order_id' => $orderId,
        'changed_by' => null,
        'old_status' => null,
        'new_status' => $status,
        'note' => 'Đơn hàng được tạo từ checkout công khai.',
    ]);

    $db->commit();

    remember_recent_order_code($orderCode);
    grant_order_access($orderCode);

    if ($input['payment_method'] === 'vnpay') {
        $vnp_TxnRef = $orderCode;
        $vnp_OrderInfo = 'Thanh toan don hang ' . $orderCode;
        $vnp_OrderType = 'billpayment';
        $vnp_Amount = (int)($totalAmount * 100);
        $vnp_Locale = 'vn';
        $vnp_IpAddr = $_SERVER['REMOTE_ADDR'] ?: '127.0.0.1';

        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => VNP_TMN_CODE,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => VNP_RETURN_URL,
            "vnp_TxnRef" => $vnp_TxnRef,
        );

        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $vnp_Url = VNP_URL . "?" . $hashdata;
        if (defined('VNP_HASH_SECRET')) {
            $vnpSecureHash = hash_hmac('sha512', $hashdata, VNP_HASH_SECRET);
            $vnp_Url .= '&vnp_SecureHash=' . $vnpSecureHash;
        }

        header('Location: ' . $vnp_Url);
        exit;
    }

    $_SESSION['cart'] = [];
    clear_old_input();
    flash_set('checkout_notice', 'Đặt hàng thành công. Hệ thống đã tạo đơn hàng cho bạn.');

    header('Location: ' . APP_URL . '/order-success.php?code=' . urlencode($orderCode));
    exit;
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    set_old_input($input);
    flash_set('checkout_errors', ['Không thể tạo đơn hàng: ' . $exception->getMessage()]);
    header('Location: ' . APP_URL . '/checkout.php');
    exit;
}
