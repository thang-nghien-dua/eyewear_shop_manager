<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/helpers/functions.php';

ensure_cart_session();

if (empty($_SESSION['cart'])) {
    header('Location: ' . APP_URL . '/cart.php');
    exit;
}

$pageTitle = 'Checkout';
$pageDescription = 'Hoàn tất đơn hàng tại ' . APP_NAME;
$headerKeyword = '';
$errors = flash_get('checkout_errors', []);
$successMessage = flash_get('checkout_notice');
$cartItems = cart_items();
$placeholderImage = APP_URL . '/assets/images/placeholder-glasses.png';

include BASE_PATH . '/app/views/partials/head.php';
include BASE_PATH . '/app/views/partials/header.php';
?>

<main class="page-section">
    <div class="container checkout-page">
        <div class="section-heading-row cart-heading">
            <div>
                <h1>Checkout</h1>
                <p class="cart-subtitle">Điền thông tin nhận hàng để tạo đơn hàng.</p>
            </div>
            <a href="<?= e(APP_URL) ?>/cart.php" class="back-link">
                <i class="fi fi-rr-angle-left icon icon-sm"></i>
                Quay lại giỏ hàng
            </a>
        </div>

        <?php if (!empty($successMessage)): ?>
            <div class="alert success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert warning">
                <strong>Vui lòng kiểm tra lại thông tin:</strong>
                <ul class="form-error-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="checkout-layout">
            <form action="<?= e(APP_URL) ?>/place-order.php" method="post" class="checkout-form-card">
                <div class="checkout-card-section">
                    <div class="section-heading-row compact">
                        <h2>Thông tin khách hàng</h2>
                    </div>

                    <div class="form-grid two-cols">
                        <div class="form-field">
                            <label for="customer_name">Họ và tên *</label>
                            <input type="text" id="customer_name" name="customer_name" value="<?= e((string) old('customer_name')) ?>" required>
                        </div>
                        <div class="form-field">
                            <label for="customer_phone">Số điện thoại *</label>
                            <input type="text" id="customer_phone" name="customer_phone" value="<?= e((string) old('customer_phone')) ?>" required>
                        </div>
                        <div class="form-field full-width">
                            <label for="customer_email">Email *</label>
                            <input type="email" id="customer_email" name="customer_email" value="<?= e((string) old('customer_email')) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="checkout-card-section">
                    <div class="section-heading-row compact">
                        <h2>Địa chỉ giao hàng</h2>
                    </div>

                    <div class="form-grid two-cols">
                        <div class="form-field full-width">
                            <label for="shipping_address_line">Địa chỉ chi tiết *</label>
                            <input type="text" id="shipping_address_line" name="shipping_address_line" value="<?= e((string) old('shipping_address_line')) ?>" placeholder="Số nhà, tên đường..." required>
                        </div>
                        <div class="form-field">
                            <label for="shipping_ward">Phường / Xã</label>
                            <input type="text" id="shipping_ward" name="shipping_ward" value="<?= e((string) old('shipping_ward')) ?>">
                        </div>
                        <div class="form-field">
                            <label for="shipping_district">Quận / Huyện</label>
                            <input type="text" id="shipping_district" name="shipping_district" value="<?= e((string) old('shipping_district')) ?>">
                        </div>
                        <div class="form-field">
                            <label for="shipping_province">Tỉnh / Thành phố</label>
                            <input type="text" id="shipping_province" name="shipping_province" value="<?= e((string) old('shipping_province')) ?>">
                        </div>
                        <div class="form-field">
                            <label for="postal_code">Mã bưu điện</label>
                            <input type="text" id="postal_code" name="postal_code" value="<?= e((string) old('postal_code')) ?>">
                        </div>
                    </div>
                </div>

                <div class="checkout-card-section">
                    <div class="section-heading-row compact">
                        <h2>Kiểu đơn hàng và thanh toán</h2>
                    </div>

                    <div class="form-grid two-cols">
                        <div class="form-field">
                            <label for="order_type">Loại đơn hàng</label>
                            <?php $oldOrderType = (string) old('order_type', 'available'); ?>
                            <select id="order_type" name="order_type">
                                <option value="available" <?= $oldOrderType === 'available' ? 'selected' : '' ?>>Có sẵn</option>
                                <option value="preorder" <?= $oldOrderType === 'preorder' ? 'selected' : '' ?>>Pre-order</option>
                                <option value="prescription" <?= $oldOrderType === 'prescription' ? 'selected' : '' ?>>Cắt kính theo đơn</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="payment_method">Phương thức thanh toán</label>
                            <?php $oldPaymentMethod = (string) old('payment_method', 'cod'); ?>
                            <select id="payment_method" name="payment_method">
                                <option value="cod" <?= $oldPaymentMethod === 'cod' ? 'selected' : '' ?>>Thanh toán khi nhận hàng</option>
                                <option value="bank_transfer" <?= $oldPaymentMethod === 'bank_transfer' ? 'selected' : '' ?>>Chuyển khoản ngân hàng</option>
                                <option value="momo" <?= $oldPaymentMethod === 'momo' ? 'selected' : '' ?>>Ví MoMo</option>
                            </select>
                        </div>
                        <div class="form-field full-width">
                            <label for="note">Ghi chú</label>
                            <textarea id="note" name="note" rows="4" placeholder="Ví dụ: giao giờ hành chính, gọi trước khi giao..."><?= e((string) old('note')) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="checkout-submit-bar">
                    <button type="submit" class="btn btn-primary">
                        <i class="fi fi-rr-badge-check icon icon-sm"></i>
                        Xác nhận đặt hàng
                    </button>
                    <p class="summary-note">Sau khi đặt hàng, hệ thống sẽ tạo bản ghi trong bảng <code>orders</code> và <code>order_items</code>.</p>
                </div>
            </form>

            <aside class="checkout-summary-card">
                <h2>Đơn hàng của bạn</h2>
                <div class="checkout-mini-list">
                    <?php foreach ($cartItems as $item): ?>
                        <div class="checkout-mini-item">
                            <img
                                src="<?= e($item['thumbnail'] ?: $placeholderImage) ?>"
                                alt="<?= e($item['name']) ?>"
                                onerror="this.onerror=null;this.src='<?= e($placeholderImage) ?>';"
                            >
                            <div>
                                <h3><?= e($item['name']) ?></h3>
                                <p style="margin-bottom: 2px;"><?= e($item['brand'] ?: 'LUMINA') ?></p>
                                <?php if (!empty($item['color']) || !empty($item['size_label'])): ?>
                                    <p style="font-size: 12px; color: #64748b; margin-bottom: 6px;">
                                        Phân loại: 
                                        <strong>
                                            <?php
                                                $specs = [];
                                                if (!empty($item['color'])) $specs[] = $item['color'];
                                                if (!empty($item['size_label'])) $specs[] = 'Size ' . $item['size_label'];
                                                echo e(implode(' / ', $specs));
                                            ?>
                                        </strong>
                                    </p>
                                <?php endif; ?>
                                <div class="checkout-mini-meta">
                                    <span>x<?= (int) $item['quantity'] ?></span>
                                    <strong><?= format_price(((float) $item['price']) * ((int) $item['quantity'])) ?></strong>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

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
            </aside>
        </div>
    </div>
</main>

<?php
clear_old_input();
include BASE_PATH . '/app/views/partials/footer.php';
