<?php
/**
 * write-review.php
 * Trang viết đánh giá sản phẩm từ đơn hàng đã hoàn tất
 */
require_once __DIR__ . '/../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

require_login();
$user = auth_user();
$db   = Database::connect();

$orderId   = (int) ($_GET['order_id']   ?? 0);
$productId = (int) ($_GET['product_id'] ?? 0);

if ($orderId <= 0 || $productId <= 0) {
    add_flash('error', 'Thông tin không hợp lệ.');
    header('Location: ' . APP_URL . '/profile.php#reviews');
    exit;
}

// Kiểm tra đơn hàng thuộc user, đã completed
$orderStmt = $db->prepare("
    SELECT o.id, o.order_code, o.status
    FROM orders o
    WHERE o.id = :oid AND o.user_id = :uid AND o.status = 'completed'
    LIMIT 1
");
$orderStmt->execute(['oid' => $orderId, 'uid' => $user['id']]);
$order = $orderStmt->fetch();

if (!$order) {
    add_flash('error', 'Đơn hàng không hợp lệ hoặc chưa hoàn tất.');
    header('Location: ' . APP_URL . '/profile.php#reviews');
    exit;
}

// Kiểm tra sản phẩm thuộc đơn hàng đó
$prodStmt = $db->prepare("
    SELECT p.id, p.name, p.thumbnail, p.slug,
           pv.color, pv.size_label
    FROM order_items oi
    INNER JOIN product_variants pv ON pv.id = oi.product_variant_id
    INNER JOIN products p ON p.id = pv.product_id
    WHERE oi.order_id = :oid AND p.id = :pid
    LIMIT 1
");
$prodStmt->execute(['oid' => $orderId, 'pid' => $productId]);
$product = $prodStmt->fetch();

if (!$product) {
    add_flash('error', 'Sản phẩm không thuộc đơn hàng này.');
    header('Location: ' . APP_URL . '/profile.php#reviews');
    exit;
}

// Kiểm tra đã đánh giá chưa
try {
    $existStmt = $db->prepare('SELECT id FROM product_reviews WHERE user_id = :uid AND product_id = :pid');
    $existStmt->execute(['uid' => $user['id'], 'pid' => $productId]);
    if ($existStmt->fetchColumn()) {
        add_flash('error', 'Bạn đã đánh giá sản phẩm này rồi.');
        header('Location: ' . APP_URL . '/profile.php#reviews');
        exit;
    }
} catch (\Throwable $e) {
    add_flash('error', 'Hệ thống đánh giá chưa sẵn sàng. Vui lòng thử lại sau.');
    header('Location: ' . APP_URL . '/profile.php#reviews');
    exit;
}

$errors = [];

// Xử lý POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = max(1, min(5, (int) ($_POST['rating'] ?? 0)));
    $body   = trim((string) ($_POST['body'] ?? ''));

    if ($rating < 1 || $rating > 5) $errors[] = 'Vui lòng chọn số sao (1-5).';
    if ($body === '')                $errors[] = 'Vui lòng nhập nhận xét.';
    if (mb_strlen($body) < 5)       $errors[] = 'Nhận xét cần ít nhất 5 ký tự.';

    if (empty($errors)) {
        try {
            $insertStmt = $db->prepare("
                INSERT INTO product_reviews
                    (user_id, product_id, order_id, rating, title, body, status)
                VALUES
                    (:uid, :pid, :oid, :rating, NULL, :body, 'approved')
            ");
            $insertStmt->execute([
                'uid'    => $user['id'],
                'pid'    => $productId,
                'oid'    => $orderId,
                'rating' => $rating,
                'body'   => $body,
            ]);

            add_flash('success', 'Đánh giá của bạn đã được ghi nhận! Cảm ơn bạn đã chia sẻ.');
            header('Location: ' . APP_URL . '/profile.php#reviews');
            exit;
        } catch (\Throwable $e) {
            $errors[] = 'Có lỗi xảy ra khi lưu đánh giá. Vui lòng thử lại.';
        }
    }
}

$placeholderImg = APP_URL . '/assets/images/placeholder-glasses.png';
function wr_img(?string $url, string $placeholder): string {
    $url = trim((string) $url);
    if ($url === '') return $placeholder;
    if (str_starts_with($url, '/') || str_starts_with($url, 'http')) return $url;
    return '/' . ltrim($url, '/');
}

$pageTitle = 'Viết đánh giá — ' . $product['name'];
require_once BASE_PATH . '/app/views/partials/head.php';
require_once BASE_PATH . '/app/views/partials/header.php';
?>

<style>
.wr-page { background:#f8f9fa; min-height:calc(100vh - 80px); padding:2.5rem 0; }
.wr-container { max-width:640px; margin:0 auto; padding:0 1rem; }
.wr-back { display:inline-flex; align-items:center; gap:.5rem; color:#718096; font-size:.88rem; font-weight:600; text-decoration:none; margin-bottom:1.5rem; transition:.15s; }
.wr-back:hover { color:#1a2e4a; }

.wr-card { background:#fff; border-radius:16px; padding:2rem; box-shadow:0 4px 20px rgba(0,0,0,.06); border:1px solid #e4ebee; }
.wr-card h1 { font-size:1.4rem; font-weight:800; color:#1a2e4a; margin:0 0 1.5rem; display:flex; align-items:center; gap:.5rem; }

.wr-product { display:flex; align-items:center; gap:1rem; padding:1rem; background:#f4f7f9; border-radius:12px; margin-bottom:1.75rem; border:1px solid #e4ebee; }
.wr-product-img { width:70px; height:70px; object-fit:contain; border-radius:8px; background:#fff; padding:.25rem; flex:0 0 70px; }
.wr-product-info { flex:1; }
.wr-product-name { font-weight:800; font-size:.95rem; color:#1a2e4a; margin-bottom:.25rem; }
.wr-product-meta { font-size:.8rem; color:#9aa3a6; }

/* Star picker */
.wr-label { font-size:.85rem; font-weight:700; color:#4a5568; margin-bottom:.6rem; display:block; }
.star-row { display:flex; gap:.4rem; margin-bottom:1.5rem; }
.star-row input { display:none; }
.star-row label {
    font-size:2.4rem; cursor:pointer; color:#e2e8f0;
    transition:color .15s, transform .1s;
    line-height:1;
}
.star-row label:hover,
.star-row label:hover ~ label { color:#e2e8f0; }
.star-row input:checked ~ label { color:#e2e8f0; }
/* Forward star fill using flex-direction: row-reverse trick */
.star-row {
    flex-direction: row-reverse;
    justify-content: flex-end;
}
.star-row label:hover,
.star-row label:hover ~ label,
.star-row input:checked ~ label,
.star-row input:checked + label,
.star-row input:checked ~ label { color:#f59e0b; }
/* Simpler: highlight from checked star leftward via sibling combinator */
.star-row input:checked ~ label { color:#f59e0b !important; }
/* We need forward selection - using JS instead */

.wr-body-group { margin-bottom:1.5rem; }
.wr-textarea {
    width:100%; min-height:120px; padding:.85rem 1rem;
    border:1.5px solid #dbe4e7; border-radius:10px;
    font-size:.95rem; font-family:inherit; line-height:1.6;
    background:#f8fafc; outline:none; resize:vertical;
    transition:border-color .2s;
    box-sizing:border-box;
}
.wr-textarea:focus { border-color:#1a2e4a; background:#fff; }
.wr-char-count { font-size:.75rem; color:#a0aec0; text-align:right; margin-top:.3rem; }

.wr-submit {
    width:100%; padding:.9rem; border:none; border-radius:10px;
    background:linear-gradient(135deg,#1a2e4a,#2d4563);
    color:#f5b700; font-size:1rem; font-weight:800;
    cursor:pointer; transition:.2s; letter-spacing:.04em;
}
.wr-submit:hover { background:linear-gradient(135deg,#2d4563,#3a5a7a); }
.wr-submit:disabled { opacity:.6; cursor:not-allowed; }

.wr-errors { background:#fff5f5; border:1.5px solid #fed7d7; border-radius:10px; padding:1rem 1.25rem; margin-bottom:1.5rem; }
.wr-errors p { margin:.2rem 0; font-size:.88rem; color:#c53030; }

/* Star picker interactive via JS */
.star-btn { font-size:2.4rem; cursor:pointer; color:#e2e8f0; background:none; border:none; padding:0 .1rem; transition:color .1s, transform .1s; line-height:1; }
.star-btn:hover, .star-btn.active { color:#f59e0b; transform:scale(1.15); }
.star-row-js { display:flex; gap:.35rem; margin-bottom:1.5rem; }
</style>

<div class="wr-page">
    <div class="wr-container">
        <a href="<?= e(APP_URL) ?>/profile.php#reviews" class="wr-back">
            ← Quay lại đánh giá của tôi
        </a>

        <div class="wr-card">
            <h1>✍️ Viết đánh giá</h1>

            <!-- Thông tin sản phẩm -->
            <div class="wr-product">
                <img class="wr-product-img"
                     src="<?= e(wr_img($product['thumbnail'], $placeholderImg)) ?>"
                     alt="<?= e($product['name']) ?>">
                <div class="wr-product-info">
                    <div class="wr-product-name"><?= e($product['name']) ?></div>
                    <div class="wr-product-meta">
                        <?php $meta = array_filter([$product['color'], $product['size_label']]); ?>
                        Đơn <?= e($order['order_code']) ?>
                        <?php if ($meta): ?> · <?= e(implode(', ', $meta)) ?><?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="wr-errors">
                    <?php foreach ($errors as $err): ?>
                        <p>⚠️ <?= e($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="reviewForm">
                <!-- Chọn sao -->
                <span class="wr-label">Chấm điểm sản phẩm *</span>
                <div class="star-row-js" id="starRow" role="group" aria-label="Chọn số sao">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button" class="star-btn" data-val="<?= $i ?>" aria-label="<?= $i ?> sao">★</button>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" id="ratingInput" value="0">

                <!-- Nhận xét -->
                <div class="wr-body-group">
                    <label class="wr-label" for="reviewBody">Nhận xét của bạn *</label>
                    <textarea
                        id="reviewBody" name="body"
                        class="wr-textarea"
                        placeholder="Chia sẻ cảm nhận về chất lượng, kiểu dáng, độ vừa vặn..."
                        maxlength="1000"
                        oninput="document.getElementById('charCount').textContent = this.value.length"
                    ><?= e((string) ($_POST['body'] ?? '')) ?></textarea>
                    <div class="wr-char-count"><span id="charCount"><?= mb_strlen((string)($_POST['body'] ?? '')) ?></span> / 1000 ký tự</div>
                </div>

                <button type="submit" class="wr-submit" id="submitBtn">📤 Gửi đánh giá</button>
            </form>
        </div>
    </div>
</div>

<script>
// ── Star picker ──────────────────────────────────────────────────
const starBtns    = document.querySelectorAll('.star-btn');
const ratingInput = document.getElementById('ratingInput');
let currentRating = <?= (int)($_POST['rating'] ?? 0) ?>;

function renderStars(val) {
    starBtns.forEach(btn => {
        btn.classList.toggle('active', parseInt(btn.dataset.val) <= val);
    });
}

starBtns.forEach(btn => {
    btn.addEventListener('mouseover', () => renderStars(parseInt(btn.dataset.val)));
    btn.addEventListener('mouseleave', () => renderStars(currentRating));
    btn.addEventListener('click', () => {
        currentRating = parseInt(btn.dataset.val);
        ratingInput.value = currentRating;
        renderStars(currentRating);
    });
});

renderStars(currentRating);

// ── Submit guard ──────────────────────────────────────────────────
document.getElementById('reviewForm').addEventListener('submit', function(e) {
    if (parseInt(ratingInput.value) < 1) {
        e.preventDefault();
        alert('Vui lòng chọn số sao trước khi gửi!');
    }
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'Đang gửi...';
});
</script>

<?php require_once BASE_PATH . '/app/views/partials/footer.php'; ?>
