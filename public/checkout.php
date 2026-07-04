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

// Lấy ví đơn kính của khách hàng đã đăng nhập
$userPrescriptions = [];
$loggedInUser = null;
if (is_logged_in()) {
    try {
        $db = Database::connect();
        $rxStmt = $db->prepare('SELECT * FROM customer_prescriptions WHERE user_id = :uid ORDER BY is_default DESC, id DESC');
        $rxStmt->execute(['uid' => auth_user()['id']]);
        $userPrescriptions = $rxStmt->fetchAll();
        
        $uStmt = $db->prepare('SELECT * FROM users WHERE id = :uid LIMIT 1');
        $uStmt->execute(['uid' => auth_user()['id']]);
        $loggedInUser = $uStmt->fetch();
    } catch (\Throwable $e) { /* bảng chưa tồn tại */ }
}

include BASE_PATH . '/app/views/partials/head.php';
include BASE_PATH . '/app/views/partials/header.php';
?>

<style>
/* ── Prescription Wallet in Checkout ── */
.rx-select-section {
    background: linear-gradient(135deg, #f0f7ff 0%, #e8f4ff 100%);
    border: 1.5px solid #c8dff7;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.25rem;
}
.rx-select-header {
    display: flex; align-items: center; gap: .6rem;
    font-weight: 800; color: #1a2e4a; font-size: .95rem;
    margin-bottom: .85rem;
}
.rx-select-header svg { flex: 0 0 20px; }
.rx-dropdown-wrap { display: flex; gap: .75rem; align-items: flex-start; flex-wrap: wrap; }
.rx-dropdown-wrap select {
    flex: 1; padding: .65rem .9rem;
    border: 1.5px solid #c8dff7; border-radius: 8px;
    font-size: .9rem; color: #1a2e4a; background: #fff;
    cursor: pointer; outline: none; transition: border-color 0.2s;
}
.rx-dropdown-wrap select:focus { border-color: #1a2e4a; }
.btn-apply-rx {
    padding: .65rem 1.25rem; border: none; border-radius: 8px;
    background: #1a2e4a; color: #fff; font-weight: 800; font-size: .88rem;
    cursor: pointer; white-space: nowrap; transition: background 0.2s;
}
.btn-apply-rx:hover { background: #2d4563; }

.rx-preview-box {
    display: none; margin-top: 1rem; padding: .85rem 1rem;
    background: #fff; border-radius: 8px; border: 1px solid #c8dff7;
}
.rx-preview-box.show { display: block; }
.rx-preview-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: .5rem 1.5rem; font-size: .83rem;
}
.rx-preview-row { display: flex; justify-content: space-between; padding: .2rem 0; border-bottom: 1px dashed #edf2f7; }
.rx-preview-row:last-child { border-bottom: none; }
.rx-preview-label { color: #718096; }
.rx-preview-value { font-weight: 700; color: #1a2e4a; }
.rx-wallet-link { font-size: .78rem; color: #d4880a; text-decoration: none; font-weight: 600; }
.rx-wallet-link:hover { text-decoration: underline; }
.rx-badge {
    background: #1a2e4a; color: #f5b700; font-size: .65rem;
    padding: .1rem .4rem; border-radius: 99px; font-weight: 800;
}
</style>

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
                <!-- Hidden fields for prescription data -->
                <input type="hidden" name="prescription_wallet_id" id="checkoutPrescriptionId" value="">

                <div class="checkout-card-section">
                    <div class="section-heading-row compact">
                        <h2>Thông tin khách hàng</h2>
                    </div>

                    <div class="form-grid two-cols">
                        <div class="form-field">
                            <label for="customer_name">Họ và tên *</label>
                            <input type="text" id="customer_name" name="customer_name" value="<?= e((string) old('customer_name', $loggedInUser['full_name'] ?? '')) ?>" required>
                        </div>
                        <div class="form-field">
                            <label for="customer_phone">Số điện thoại *</label>
                            <input type="text" id="customer_phone" name="customer_phone" value="<?= e((string) old('customer_phone', $loggedInUser['phone'] ?? '')) ?>" required>
                        </div>
                        <div class="form-field full-width">
                            <label for="customer_email">Email *</label>
                            <input type="email" id="customer_email" name="customer_email" value="<?= e((string) old('customer_email', $loggedInUser['email'] ?? '')) ?>" required>
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
                            <input type="text" id="shipping_address_line" name="shipping_address_line" value="<?= e((string) old('shipping_address_line', $loggedInUser['address_line'] ?? '')) ?>" placeholder="Số nhà, tên đường..." required>
                        </div>
                        <div class="form-field">
                            <label for="shipping_ward">Phường / Xã</label>
                            <input type="text" id="shipping_ward" name="shipping_ward" value="<?= e((string) old('shipping_ward', $loggedInUser['ward'] ?? '')) ?>">
                        </div>
                        <div class="form-field">
                            <label for="shipping_district">Quận / Huyện</label>
                            <input type="text" id="shipping_district" name="shipping_district" value="<?= e((string) old('shipping_district', $loggedInUser['district'] ?? '')) ?>">
                        </div>
                        <div class="form-field">
                            <label for="shipping_province">Tỉnh / Thành phố</label>
                            <input type="text" id="shipping_province" name="shipping_province" value="<?= e((string) old('shipping_province', $loggedInUser['province'] ?? '')) ?>">
                        </div>
                        <div class="form-field">
                            <label for="postal_code">Mã bưu điện</label>
                            <input type="text" id="postal_code" name="postal_code" value="<?= e((string) old('postal_code', $loggedInUser['postal_code'] ?? '')) ?>">
                        </div>
                    </div>
                </div>

                <!-- ── Prescription Wallet Section ── -->
                <?php if (!empty($userPrescriptions)): ?>
                <div class="checkout-card-section">
                    <div class="rx-select-section">
                        <div class="rx-select-header">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            Vui lòng chọn hoặc tạo hồ sơ thông số kính để cửa hàng điều chỉnh phù hợp với kích thước kính của bạn

                            <a href="<?= e(APP_URL) ?>/profile.php#wallet" target="_blank" class="rx-wallet-link" style="margin-left:auto;">
                                Quản lý ví →
                            </a>
                        </div>
                        <div class="rx-dropdown-wrap">
                            <select id="rxWalletSelect" onchange="previewPrescription(this.value)">
                                <option value="">-- Chọn hồ sơ đơn kính đã lưu --</option>
                                <?php foreach ($userPrescriptions as $rx): ?>
                                    <option value="<?= (int) $rx['id'] ?>"
                                            <?= $rx['is_default'] ? 'selected' : '' ?>
                                            data-rx='<?= htmlspecialchars(json_encode($rx), ENT_QUOTES) ?>'>
                                        <?= e($rx['profile_name']) ?><?= $rx['is_default'] ? ' ⭐' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-apply-rx" onclick="applyPrescription()">
                                ✅ Áp dụng
                            </button>
                        </div>

                        <!-- Preview box -->
                        <div class="rx-preview-box" id="rxPreviewBox">
                            <div style="font-weight:800;font-size:.82rem;color:#1a2e4a;margin-bottom:.65rem;">
                                📋 Xem trước thông số khúc xạ:
                            </div>
                            <div class="rx-preview-grid">
                                <div>
                                    <div style="font-size:.72rem;font-weight:800;color:#016b5e;margin-bottom:.25rem;">MẮT PHẢI (OD)</div>
                                    <div class="rx-preview-row"><span class="rx-preview-label">SPH</span><span class="rx-preview-value" id="pOdSphere">—</span></div>
                                    <div class="rx-preview-row"><span class="rx-preview-label">CYL</span><span class="rx-preview-value" id="pOdCylinder">—</span></div>
                                    <div class="rx-preview-row"><span class="rx-preview-label">AXIS</span><span class="rx-preview-value" id="pOdAxis">—</span></div>
                                    <div class="rx-preview-row"><span class="rx-preview-label">ADD</span><span class="rx-preview-value" id="pOdAddition">—</span></div>
                                </div>
                                <div>
                                    <div style="font-size:.72rem;font-weight:800;color:#016b5e;margin-bottom:.25rem;">MẮT TRÁI (OS)</div>
                                    <div class="rx-preview-row"><span class="rx-preview-label">SPH</span><span class="rx-preview-value" id="pOsSphere">—</span></div>
                                    <div class="rx-preview-row"><span class="rx-preview-label">CYL</span><span class="rx-preview-value" id="pOsCylinder">—</span></div>
                                    <div class="rx-preview-row"><span class="rx-preview-label">AXIS</span><span class="rx-preview-value" id="pOsAxis">—</span></div>
                                    <div class="rx-preview-row"><span class="rx-preview-label">ADD</span><span class="rx-preview-value" id="pOsAddition">—</span></div>
                                </div>
                            </div>
                            <div style="margin-top:.65rem;padding-top:.65rem;border-top:1px dashed #e2e8f0;font-size:.78rem;">
                                <strong>PD:</strong>
                                <span id="pPdSummary" style="color:#4a5568;">—</span>
                            </div>
                        </div>

                        <!-- Applied indicator -->
                        <div id="rxAppliedBanner" style="display:none;margin-top:.75rem;padding:.6rem .9rem;background:#d1fae5;border-radius:8px;font-size:.83rem;font-weight:700;color:#065f46;display:none;align-items:center;gap:.5rem;">
                            ✅ Đã áp dụng hồ sơ: <span id="rxAppliedName" style="font-weight:800;"></span>
                        </div>
                    </div>
                </div>
                <?php elseif (is_logged_in()): ?>
                <div class="checkout-card-section">
                    <div style="background:#f0f7ff;border:1.5px dashed #c8dff7;border-radius:12px;padding:1rem 1.25rem;display:flex;align-items:center;gap:.75rem;font-size:.88rem;color:#4a5568;">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#1a2e4a"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <div>
                            Bạn chưa lưu hồ sơ đơn kính nào.
                            <a href="<?= e(APP_URL) ?>/profile.php#wallet" target="_blank" style="color:#d4880a;font-weight:700;text-decoration:none;">
                                Thêm hồ sơ vào Ví đơn kính →
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="checkout-card-section">
                    <div class="section-heading-row compact">
                        <h2>Kiểu đơn hàng và thanh toán</h2>
                    </div>

                    <div class="form-grid two-cols">
                        <div class="form-field">
                            <label for="order_type">Loại đơn hàng</label>
                            <input type="hidden" name="order_type" value="available">
                            <select id="order_type" disabled style="background-color: #f1f5f9; cursor: not-allowed;">
                                <option value="available" selected>Có sẵn</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="payment_method">Phương thức thanh toán</label>
                            <?php $oldPaymentMethod = (string) old('payment_method', 'cod'); ?>
                            <select id="payment_method" name="payment_method">
                                <option value="cod" <?= $oldPaymentMethod === 'cod' ? 'selected' : '' ?>>Thanh toán khi nhận hàng</option>
                                <option value="bank_transfer" <?= $oldPaymentMethod === 'bank_transfer' ? 'selected' : '' ?>>Chuyển khoản ngân hàng</option>
                                <option value="momo" <?= $oldPaymentMethod === 'momo' ? 'selected' : '' ?>>Ví MoMo</option>
                                <option value="vnpay" <?= $oldPaymentMethod === 'vnpay' ? 'selected' : '' ?>>Thanh toán trực tuyến qua VNPAY (ATM/QR/Credit Card)</option>
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

<?php if (!empty($userPrescriptions)): ?>
<script>
const rxData = <?= json_encode(array_column($userPrescriptions, null, 'id')) ?>;

function fmt(v, unit = '') {
    if (v === null || v === undefined || v === '') return '—';
    const n = parseFloat(v);
    if (unit === 'deg') return n + '°';
    if (unit === 'mm') return n.toFixed(1) + ' mm';
    return (n >= 0 ? '+' : '') + n.toFixed(2);
}

function previewPrescription(id) {
    const box = document.getElementById('rxPreviewBox');
    if (!id) { box.classList.remove('show'); return; }
    const rx = rxData[id];
    if (!rx) return;

    document.getElementById('pOdSphere').textContent   = fmt(rx.od_sphere);
    document.getElementById('pOdCylinder').textContent = fmt(rx.od_cylinder);
    document.getElementById('pOdAxis').textContent     = fmt(rx.od_axis, 'deg');
    document.getElementById('pOdAddition').textContent = fmt(rx.od_addition);
    document.getElementById('pOsSphere').textContent   = fmt(rx.os_sphere);
    document.getElementById('pOsCylinder').textContent = fmt(rx.os_cylinder);
    document.getElementById('pOsAxis').textContent     = fmt(rx.os_axis, 'deg');
    document.getElementById('pOsAddition').textContent = fmt(rx.os_addition);

    const pdParts = [];
    if (rx.pd_right)    pdParts.push('Phải: ' + fmt(rx.pd_right, 'mm'));
    if (rx.pd_left)     pdParts.push('Trái: ' + fmt(rx.pd_left, 'mm'));
    if (rx.pd_distance) pdParts.push('Xa: '   + fmt(rx.pd_distance, 'mm'));
    if (rx.pd_near)     pdParts.push('Gần: '  + fmt(rx.pd_near, 'mm'));
    document.getElementById('pPdSummary').textContent = pdParts.join('  |  ') || '—';

    box.classList.add('show');
}

function applyPrescription() {
    const sel = document.getElementById('rxWalletSelect');
    const id  = sel.value;
    if (!id) { alert('Vui lòng chọn một hồ sơ đơn kính.'); return; }

    document.getElementById('checkoutPrescriptionId').value = id;

    const rx = rxData[id];
    const banner = document.getElementById('rxAppliedBanner');
    document.getElementById('rxAppliedName').textContent = rx?.profile_name || '';
    banner.style.display = 'flex';

    // Thêm tóm tắt vào note textarea
    const noteArea = document.getElementById('note');
    const rxSummary = [
        '--- Đơn kính: ' + (rx?.profile_name || ''),
        'OD: SPH ' + fmt(rx?.od_sphere) + ' CYL ' + fmt(rx?.od_cylinder) + ' AXIS ' + fmt(rx?.od_axis, 'deg'),
        'OS: SPH ' + fmt(rx?.os_sphere) + ' CYL ' + fmt(rx?.os_cylinder) + ' AXIS ' + fmt(rx?.os_axis, 'deg'),
    ];
    if (rx?.pd_distance) rxSummary.push('PD: ' + fmt(rx.pd_distance, 'mm'));
    const noteVal = noteArea.value.replace(/\n?--- Đơn kính:[\s\S]*$/m, '').trim();
    noteArea.value = (noteVal ? noteVal + '\n' : '') + rxSummary.join('\n');
}

// Preview on page load if default selected
window.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('rxWalletSelect');
    if (sel.value) previewPrescription(sel.value);
});
</script>
<?php endif; ?>

<?php
clear_old_input();
include BASE_PATH . '/app/views/partials/footer.php';
?>
