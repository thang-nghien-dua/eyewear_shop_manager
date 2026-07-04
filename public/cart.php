<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/helpers/functions.php';

ensure_cart_session();

if (isset($_GET['remove'])) {
    $removeId = (int) $_GET['remove'];
    $cartKey = 'v_' . $removeId;
    if (isset($_SESSION['cart'][$cartKey])) {
        unset($_SESSION['cart'][$cartKey]);
    }

    header('Location: ' . APP_URL . '/cart.php?removed=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $quantities = $_POST['quantities'] ?? [];

    foreach ($quantities as $variantId => $quantity) {
        $variantId = (int) $variantId;
        $quantity = (int) $quantity;
        $cartKey = 'v_' . $variantId;

        if (!isset($_SESSION['cart'][$cartKey])) {
            continue;
        }

        if ($quantity <= 0) {
            unset($_SESSION['cart'][$cartKey]);
            continue;
        }

        $_SESSION['cart'][$cartKey]['quantity'] = min(10, $quantity);
    }

    header('Location: ' . APP_URL . '/cart.php?updated=1');
    exit;
}

$pageTitle = 'Giỏ hàng';
$pageDescription = 'Giỏ hàng của bạn - ' . APP_NAME;
$headerKeyword = '';
$cartItems = cart_items();
$placeholderImage = APP_URL . '/assets/images/placeholder-glasses.png';

include BASE_PATH . '/app/views/partials/head.php';
include BASE_PATH . '/app/views/partials/header.php';
?>

<main class="page-section">
    <div class="container cart-page">
        <div class="section-heading-row cart-heading">
            <div>
                <h1>Giỏ hàng của bạn</h1>
                <p class="cart-subtitle">Xem lại sản phẩm trước khi checkout.</p>
            </div>
            <a href="<?= e(APP_URL) ?>/products.php" class="back-link">
                <i class="fi fi-rr-angle-left icon icon-sm"></i>
                Tiếp tục mua sắm
            </a>
        </div>

        <?php if (isset($_GET['added'])): ?>
            <div class="alert success">Đã thêm sản phẩm vào giỏ hàng.</div>
        <?php endif; ?>
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert info">Đã cập nhật giỏ hàng.</div>
        <?php endif; ?>
        <?php if (isset($_GET['removed'])): ?>
            <div class="alert warning">Đã xóa sản phẩm khỏi giỏ hàng.</div>
        <?php endif; ?>

        <?php if (empty($cartItems)): ?>
            <div class="empty-cart-card">
                <div class="empty-cart-icon">
                    <i class="fi fi-rr-shopping-bag icon icon-lg"></i>
                </div>
                <h2>Giỏ hàng đang trống</h2>
                <p>Hãy chọn thêm vài mẫu kính đẹp cho LUMINA nhé.</p>
                <a href="<?= e(APP_URL) ?>/products.php" class="btn btn-primary">Khám phá sản phẩm</a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <form action="<?= e(APP_URL) ?>/cart.php" method="post" class="cart-items-card">
                    <?php foreach ($cartItems as $item): ?>
                        <div class="cart-item-row">
                            <div class="cart-item-image">
                                <img
                                    src="<?= e($item['thumbnail'] ?: $placeholderImage) ?>"
                                    alt="<?= e($item['name']) ?>"
                                    onerror="this.onerror=null;this.src='<?= e($placeholderImage) ?>';"
                                >
                            </div>

                            <div class="cart-item-info">
                                <h3><?= e($item['name']) ?></h3>
                                <p class="product-brand" style="margin-bottom: 4px;"><?= e($item['brand']) ?></p>
                                <?php if (!empty($item['color']) || !empty($item['size_label'])): ?>
                                    <p class="product-variant-info" style="font-size: 13px; color: #64748b; margin-bottom: 8px;">
                                        Phân loại: 
                                        <strong>
                                            <?php
                                                $specs = [];
                                                if (!empty($item['color'])) $specs[] = 'Màu ' . $item['color'];
                                                if (!empty($item['size_label'])) $specs[] = 'Size ' . $item['size_label'];
                                                echo e(implode(' | ', $specs));
                                            ?>
                                        </strong>
                                    </p>
                                <?php endif; ?>
                                <p class="product-price"><?= format_price($item['price']) ?></p>
                            </div>

                            <div class="cart-item-qty">
                                <label for="qty-<?= (int) $item['id'] ?>">Số lượng</label>
                                <input
                                    type="number"
                                    id="qty-<?= (int) $item['id'] ?>"
                                    name="quantities[<?= (int) $item['id'] ?>]"
                                    min="0"
                                    max="10"
                                    value="<?= (int) $item['quantity'] ?>"
                                >
                            </div>

                            <div class="cart-item-total">
                                <span>Tạm tính</span>
                                <strong><?= format_price($item['price'] * $item['quantity']) ?></strong>
                            </div>

                            <div class="cart-item-remove">
                                <a href="<?= e(APP_URL) ?>/cart.php?remove=<?= (int) $item['id'] ?>" class="icon-btn" title="Xóa sản phẩm">
                                    <i class="fi fi-rr-trash icon icon-sm"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="cart-actions-bar">
                        <button type="submit" name="update_cart" value="1" class="btn btn-secondary">Cập nhật giỏ hàng</button>
                    </div>
                </form>

                <aside class="cart-summary-card">
                    <h2>Tóm tắt đơn hàng</h2>

                    <div class="summary-row">
                        <span>Số sản phẩm</span>
                        <strong><?= cart_count() ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Tạm tính</span>
                        <strong><?= format_price(cart_total()) ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Phí vận chuyển</span>
                        <strong>Miễn phí</strong>
                    </div>
                    <div class="summary-row total">
                        <span>Tổng cộng</span>
                        <strong><?= format_price(cart_total()) ?></strong>
                    </div>

                    <a href="<?= e(APP_URL) ?>/checkout.php" class="btn btn-primary btn-block cart-checkout-btn">
                        <i class="fi fi-rr-credit-card icon icon-sm"></i>
                        Tiến hành checkout
                    </a>
                    <p class="summary-note">Bước kế tiếp sẽ tạo dữ liệu vào bảng <code>orders</code> và <code>order_items</code>.</p>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include BASE_PATH . '/app/views/partials/footer.php'; ?>
