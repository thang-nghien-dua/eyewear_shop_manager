<?php
/**
 * submit-review.php
 * Xử lý POST submit đánh giá sản phẩm + upload ảnh
 * Chỉ cho phép khách đã mua & đơn hàng đã completed
 */

require_once __DIR__ . '/../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/products.php');
    exit;
}

$user      = auth_user();
$db        = Database::connect();
$productId = (int) ($_POST['product_id'] ?? 0);
$rating    = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
$title     = trim((string) ($_POST['title'] ?? ''));
$body      = trim((string) ($_POST['body'] ?? ''));
$errors    = [];

if ($productId <= 0) {
    add_flash('error', 'Sản phẩm không hợp lệ.');
    header('Location: ' . APP_URL . '/products.php');
    exit;
}

// Kiểm tra sản phẩm tồn tại
$prodStmt = $db->prepare("SELECT id, name FROM products WHERE id = :id AND status = 'active' LIMIT 1");
$prodStmt->execute(['id' => $productId]);
$product  = $prodStmt->fetch();
if (!$product) {
    add_flash('error', 'Sản phẩm không tồn tại.');
    header('Location: ' . APP_URL . '/products.php');
    exit;
}

// Bước 6: Kiểm tra điều kiện đánh giá (đã mua, đơn hoàn tất, chưa đánh giá)
$eligStmt = $db->prepare("
    SELECT o.id AS order_id
    FROM orders o
    INNER JOIN order_items oi ON oi.order_id = o.id
    INNER JOIN product_variants pv ON pv.id = oi.product_variant_id
    WHERE o.user_id = :uid
      AND pv.product_id = :pid
      AND o.status = 'completed'
    LIMIT 1
");
$eligStmt->execute(['uid' => $user['id'], 'pid' => $productId]);
$eligRow = $eligStmt->fetch();

// Bước 7-8: Không đủ điều kiện → thông báo lỗi
if (!$eligRow) {
    add_flash('error', 'Bạn chỉ có thể đánh giá sản phẩm đã mua và đơn hàng đã hoàn tất.');
    header('Location: ' . APP_URL . '/product-detail.php?id=' . $productId);
    exit;
}

// Kiểm tra đã đánh giá chưa (một phần bước 6)
$existStmt = $db->prepare('SELECT id FROM product_reviews WHERE user_id = :uid AND product_id = :pid');
$existStmt->execute(['uid' => $user['id'], 'pid' => $productId]);
if ($existStmt->fetchColumn()) {
    add_flash('error', 'Bạn đã đánh giá sản phẩm này rồi.');
    header('Location: ' . APP_URL . '/product-detail.php?id=' . $productId);
    exit;
}

// Bước 12: Kiểm tra dữ liệu đánh giá (ký tự, số sao, nội dung)
if ($body === '') {
    $errors[] = 'Vui lòng nhập nội dung đánh giá.';
} elseif (mb_strlen($body) < 10) {
    $errors[] = 'Nội dung đánh giá cần ít nhất 10 ký tự.';
}
if ($rating < 1 || $rating > 5) {
    $errors[] = 'Số sao không hợp lệ (phải từ 1 đến 5).';
}
if ($title !== '' && mb_strlen($title) > 200) {
    $errors[] = 'Tiêu đề không được vượt quá 200 ký tự.';
}

// Upload ảnh (tối đa 3)
$uploadedImages = [];
$uploadDir = PUBLIC_PATH . '/assets/uploads/reviews/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!empty($_FILES['review_images']['name'][0])) {
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxSize     = 5 * 1024 * 1024; // 5MB

    $uploadCount = 0;
    foreach ($_FILES['review_images']['tmp_name'] as $idx => $tmpName) {
        if ($uploadCount >= 3) break;
        if ($_FILES['review_images']['error'][$idx] !== UPLOAD_ERR_OK) continue;
        if ($_FILES['review_images']['size'][$idx] > $maxSize) {
            $errors[] = "Ảnh #" . ($idx + 1) . " vượt quá 5MB.";
            continue;
        }

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMime, true)) {
            $errors[] = "Ảnh #" . ($idx + 1) . " không đúng định dạng (JPG/PNG/WebP).";
            continue;
        }

        $ext      = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            default      => 'jpg',
        };
        $filename = 'rev_' . $user['id'] . '_' . $productId . '_' . time() . '_' . $uploadCount . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (move_uploaded_file($tmpName, $destPath)) {
            $uploadedImages[] = '/assets/uploads/reviews/' . $filename;
            $uploadCount++;
        }
    }
}

if (!empty($errors)) {
    flash_set('review_errors', $errors);
    flash_set('review_old', ['rating' => $rating, 'title' => $title, 'body' => $body]);
    header('Location: ' . APP_URL . '/product-detail.php?id=' . $productId . '#reviews');
    exit;
}

// Lưu review
$insertStmt = $db->prepare('
    INSERT INTO product_reviews
        (user_id, product_id, order_id, rating, title, body, images, status)
    VALUES
        (:uid, :pid, :oid, :rating, :title, :body, :images, \'approved\')
');
// Bước 13-14: Lưu đánh giá vào CSDL
$insertStmt->execute([
    'uid'    => $user['id'],
    'pid'    => $productId,
    'oid'    => $eligRow['order_id'],
    'rating' => $rating,
    'title'  => $title !== '' ? $title : null,
    'body'   => $body,
    'images' => !empty($uploadedImages) ? json_encode($uploadedImages) : null,
]);

// Bước 15: Thông báo gửi đánh giá thành công
// Bước 16: Redirect → hiển thị đánh giá vừa tạo
add_flash('success', 'Cảm ơn bạn đã đánh giá! Đánh giá của bạn đã được hiển thị trên sản phẩm.');
header('Location: ' . APP_URL . '/product-detail.php?id=' . $productId . '#reviews');
exit;
