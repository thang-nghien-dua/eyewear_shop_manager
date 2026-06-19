<?php
/**
 * admin/reviews/index.php
 * Quản lý đánh giá sản phẩm – phê duyệt / ẩn / thống kê
 */

require_once __DIR__ . '/../.././../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

admin_only();

$db = Database::connect();

// ── Handle actions ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reviewId = (int) ($_POST['review_id'] ?? 0);
    $action   = trim((string) ($_POST['action'] ?? ''));

    if ($reviewId > 0 && in_array($action, ['approve', 'hide', 'delete'], true)) {
        if ($action === 'delete') {
            // Xóa ảnh nếu có
            $imgStmt = $db->prepare('SELECT images FROM product_reviews WHERE id = :id');
            $imgStmt->execute(['id' => $reviewId]);
            $imgData = $imgStmt->fetchColumn();
            if ($imgData) {
                $imgs = json_decode($imgData, true);
                if (is_array($imgs)) {
                    foreach ($imgs as $imgPath) {
                        $fullPath = PUBLIC_PATH . $imgPath;
                        if (file_exists($fullPath)) @unlink($fullPath);
                    }
                }
            }
            $db->prepare('DELETE FROM product_reviews WHERE id = :id')->execute(['id' => $reviewId]);
            add_flash('success', 'Đã xóa đánh giá.');
        } else {
            $newStatus = $action === 'approve' ? 'approved' : 'hidden';
            $adminNote = trim((string) ($_POST['admin_note'] ?? ''));
            $stmt = $db->prepare('UPDATE product_reviews SET status = :s, admin_note = :n WHERE id = :id');
            $stmt->execute(['s' => $newStatus, 'n' => $adminNote ?: null, 'id' => $reviewId]);
            add_flash('success', $action === 'approve' ? 'Đã duyệt đánh giá.' : 'Đã ẩn đánh giá.');
        }
    }

    header('Location: ' . APP_URL . '/admin/reviews/index.php?' . http_build_query(array_filter([
        'status'  => $_POST['_filter_status'] ?? '',
        'page'    => $_POST['_filter_page'] ?? '',
    ])));
    exit;
}

// ── Filters ────────────────────────────────────────────────────
$filterStatus = in_array($_GET['status'] ?? '', ['pending', 'approved', 'hidden']) ? $_GET['status'] : '';
$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;

$where  = $filterStatus !== '' ? 'WHERE r.status = :status' : '';
$params = $filterStatus !== '' ? ['status' => $filterStatus] : [];

$countStmt = $db->prepare("SELECT COUNT(*) FROM product_reviews r $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $limit));

$reviewsStmt = $db->prepare("
    SELECT r.*,
           u.full_name AS reviewer_name, u.email AS reviewer_email,
           p.name AS product_name, p.id AS product_id
    FROM product_reviews r
    INNER JOIN users u ON u.id = r.user_id
    INNER JOIN products p ON p.id = r.product_id
    $where
    ORDER BY
        CASE r.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,
        r.created_at DESC
    LIMIT $limit OFFSET $offset
");
$reviewsStmt->execute($params);
$reviews = $reviewsStmt->fetchAll();

// Stats
$statsStmt = $db->query("
    SELECT
        SUM(status = 'pending')  AS pending_count,
        SUM(status = 'approved') AS approved_count,
        SUM(status = 'hidden')   AS hidden_count,
        COUNT(*)                 AS total_count,
        AVG(rating)              AS avg_rating
    FROM product_reviews
");
$stats = $statsStmt->fetch() ?: [];

$adminActive   = 'reviews';
$pageTitle     = 'Quản lý Đánh giá - ' . APP_NAME;
$adminPageTitle    = 'Đánh giá sản phẩm';
$adminPageSubtitle = 'Phê duyệt, ẩn hoặc xóa các đánh giá từ khách hàng.';

include BASE_PATH . '/app/views/partials/admin-head.php';
?>
<style>
.review-card {
    background: #fff;
    border: 1px solid #e8ecef;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: box-shadow 0.2s;
    position: relative;
}
.review-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.07); }
.review-card.is-pending { border-left: 4px solid #f59e0b; }
.review-card.is-approved { border-left: 4px solid #10b981; }
.review-card.is-hidden { border-left: 4px solid #6b7280; opacity: 0.8; }

.review-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
.reviewer-info { display: flex; align-items: center; gap: .75rem; }
.reviewer-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, #1a2e4a, #2a4365);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: .95rem; flex: 0 0 40px;
}
.reviewer-meta { }
.reviewer-name { font-weight: 700; color: #1a2e4a; font-size: .9rem; }
.reviewer-email { font-size: .78rem; color: #718096; }
.review-product-tag {
    display: inline-flex; align-items: center; gap: .4rem;
    background: #e9f0ff; color: #3b4fd4; padding: .25rem .75rem;
    border-radius: 99px; font-size: .78rem; font-weight: 700;
    text-decoration: none;
}
.review-product-tag:hover { background: #d0dbff; }

.star-rating { display: flex; gap: 2px; margin-bottom: .5rem; }
.star-rating .star { color: #f59e0b; font-size: 1.1rem; }
.star-rating .star.empty { color: #d1d5db; }

.review-title { font-weight: 700; color: #1a2e4a; margin-bottom: .35rem; }
.review-body { color: #4a5568; font-size: .9rem; line-height: 1.65; }

.review-images { display: flex; gap: .5rem; margin-top: .75rem; flex-wrap: wrap; }
.review-img-thumb {
    width: 80px; height: 80px; border-radius: 8px; object-fit: cover;
    border: 2px solid #e2e8f0; cursor: pointer; transition: border-color 0.2s;
}
.review-img-thumb:hover { border-color: #1a2e4a; }

.review-actions { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #f0f3f5; }
.btn-approve { background: #d1fae5; color: #065f46; border: none; padding: .45rem 1rem; border-radius: 6px; font-weight: 700; font-size: .83rem; cursor: pointer; transition: all 0.2s; }
.btn-approve:hover { background: #a7f3d0; }
.btn-hide { background: #f3f4f6; color: #4b5563; border: none; padding: .45rem 1rem; border-radius: 6px; font-weight: 700; font-size: .83rem; cursor: pointer; transition: all 0.2s; }
.btn-hide:hover { background: #e5e7eb; }
.btn-del { background: #fee2e2; color: #991b1b; border: none; padding: .45rem 1rem; border-radius: 6px; font-weight: 700; font-size: .83rem; cursor: pointer; transition: all 0.2s; }
.btn-del:hover { background: #fecaca; }

.reviews-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
.stat-mini { background: #fff; border: 1px solid #e8ecef; border-radius: 10px; padding: 1rem 1.25rem; text-align: center; }
.stat-mini .val { font-size: 1.6rem; font-weight: 800; color: #1a2e4a; }
.stat-mini .lbl { font-size: .8rem; color: #718096; margin-top: .2rem; }

.filter-btn { padding: .45rem 1.1rem; border-radius: 6px; border: 1.5px solid #dbe4e7; background: #fff; color: #4a5568; font-weight: 600; font-size: .85rem; cursor: pointer; text-decoration: none; transition: all 0.2s; }
.filter-btn.active, .filter-btn:hover { border-color: #696cff; background: #696cff; color: #fff; }
.filter-btn.pending-btn.active { border-color: #f59e0b; background: #f59e0b; color: #fff; }

.review-date { font-size: .78rem; color: #9aa3a6; }
.admin-note-area { width: 100%; padding: .5rem; border: 1px solid #dbe4e7; border-radius: 6px; font-size: .85rem; margin-top: .5rem; min-height: 60px; }

/* Lightbox */
.lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 9999; align-items: center; justify-content: center; }
.lightbox.show { display: flex; }
.lightbox img { max-width: 90vw; max-height: 85vh; border-radius: 8px; object-fit: contain; }
.lightbox-close { position: absolute; top: 1.5rem; right: 1.5rem; color: #fff; font-size: 2rem; cursor: pointer; line-height: 1; }
</style>

<div class="admin-shell">
    <?php include BASE_PATH . '/app/views/partials/admin-sidebar.php'; ?>
    <div class="admin-main">
        <?php include BASE_PATH . '/app/views/partials/admin-topbar.php'; ?>
        <main class="admin-dashboard">

            <?php if ($msg = get_flash('success')): ?>
                <div class="alert success" style="margin-bottom:1rem;border-radius:8px;"><?= e($msg) ?></div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="reviews-stats">
                <div class="stat-mini">
                    <div class="val" style="color:#10b981"><?= (int) ($stats['approved_count'] ?? 0) ?></div>
                    <div class="lbl">✅ Đang hiển thị</div>
                </div>
                <div class="stat-mini">
                    <div class="val" style="color:#6b7280"><?= (int) ($stats['hidden_count'] ?? 0) ?></div>
                    <div class="lbl">🙈 Đã ẩn</div>
                </div>
                <div class="stat-mini">
                    <div class="val" style="color:#d4880a">
                        <?= number_format((float) ($stats['avg_rating'] ?? 0), 1) ?>★
                    </div>
                    <div class="lbl">Trung bình</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="admin-filter-card" style="display: flex; gap: .75rem; align-items: center; flex-wrap: wrap;">
                <span style="font-weight:700;color:#4a5568;font-size:.88rem;">Lọc:</span>
                <?php foreach (['' => 'Tất cả', 'approved' => '✅ Đang hiển thị', 'hidden' => '🙈 Đã ẩn'] as $val => $label): ?>
                    <a href="<?= e(APP_URL) ?>/admin/reviews/index.php<?= $val ? '?status=' . $val : '' ?>"
                       class="filter-btn <?= $filterStatus === $val ? 'active' : '' ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
                <span style="margin-left:auto;color:#9aa3a6;font-size:.85rem;"><?= $total ?> kết quả</span>
            </div>

            <!-- Reviews list -->
            <?php if (empty($reviews)): ?>
                <div style="text-align:center;padding:3rem;color:#a0aec0;background:#fff;border-radius:12px;border:1px solid #e8ecef;">
                    <div style="font-size:3rem;margin-bottom:1rem;">📭</div>
                    <h3>Không có đánh giá nào</h3>
                    <p>Chưa có đánh giá nào khớp với bộ lọc hiện tại.</p>
                </div>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <?php
                        $images  = !empty($review['images']) ? json_decode($review['images'], true) : [];
                        $images  = is_array($images) ? $images : [];
                        $initials = mb_strtoupper(mb_substr($review['reviewer_name'], 0, 2));
                    ?>
                    <div class="review-card is-<?= e($review['status']) ?>">
                        <div class="review-header">
                            <div class="reviewer-info">
                                <div class="reviewer-avatar"><?= e($initials) ?></div>
                                <div class="reviewer-meta">
                                    <div class="reviewer-name"><?= e($review['reviewer_name']) ?></div>
                                    <div class="reviewer-email"><?= e($review['reviewer_email']) ?></div>
                                </div>
                            </div>
                            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.4rem;">
                                <a href="<?= e(APP_URL) ?>/product-detail.php?id=<?= (int) $review['product_id'] ?>"
                                   target="_blank" class="review-product-tag">
                                    🕶️ <?= e($review['product_name']) ?>
                                </a>
                                <span class="review-date"><?= e(date('d/m/Y H:i', strtotime((string) $review['created_at']))) ?></span>
                            </div>
                        </div>

                        <!-- Stars -->
                        <div class="star-rating">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <span class="star <?= $s > $review['rating'] ? 'empty' : '' ?>">★</span>
                            <?php endfor; ?>
                            <span style="font-size:.82rem;color:#9aa3a6;margin-left:.35rem;">(<?= (int) $review['rating'] ?>/5)</span>
                        </div>

                        <?php if (!empty($review['title'])): ?>
                            <div class="review-title"><?= e($review['title']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($review['body'])): ?>
                            <div class="review-body"><?= e($review['body']) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($images)): ?>
                            <div class="review-images">
                                <?php foreach ($images as $imgPath): ?>
                                    <img src="<?= e(APP_URL . $imgPath) ?>" alt="Review image"
                                         class="review-img-thumb"
                                         onclick="openLightbox('<?= e(APP_URL . $imgPath) ?>')">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Admin note -->
                        <?php if (!empty($review['admin_note'])): ?>
                            <div style="margin-top:.75rem;padding:.6rem .9rem;background:#fef9c3;border-radius:6px;font-size:.82rem;color:#78350f;">
                                📝 Ghi chú admin: <?= e($review['admin_note']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Actions -->
                        <div class="review-actions">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
                                <input type="hidden" name="_filter_status" value="<?= e($filterStatus) ?>">
                                <?php if ($review['status'] !== 'approved'): ?>
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn-approve">👁️ Hiển thị</button>
                                <?php endif; ?>
                            </form>
                            <?php if ($review['status'] !== 'hidden'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
                                    <input type="hidden" name="action" value="hide">
                                    <input type="hidden" name="_filter_status" value="<?= e($filterStatus) ?>">
                                    <button type="submit" class="btn-hide">🙈 Ẩn</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Xác nhận xóa đánh giá này?')">
                                <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="_filter_status" value="<?= e($filterStatus) ?>">
                                <button type="submit" class="btn-del">🗑️ Xóa</button>
                            </form>

                            <div style="flex:1;text-align:right;">
                                <span class="status-pill <?= e($review['status'] === 'approved' ? 'status-completed' : 'status-cancelled') ?>">
                                    <?= e(match($review['status']) { 'approved' => 'Đang hiển thị', 'hidden' => 'Đã ẩn', default => $review['status'] }) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($pages > 1): ?>
                    <div style="display:flex;gap:.5rem;justify-content:center;margin-top:1.5rem;flex-wrap:wrap;">
                        <?php for ($p = 1; $p <= $pages; $p++): ?>
                            <a href="?page=<?= $p ?><?= $filterStatus ? '&status=' . urlencode($filterStatus) : '' ?>"
                               style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:8px;border:1.5px solid <?= $p === $page ? '#1a2e4a' : '#dbe4e7' ?>;background:<?= $p === $page ? '#1a2e4a' : '#fff' ?>;color:<?= $p === $page ? '#fff' : '#4a5568' ?>;font-weight:700;text-decoration:none;font-size:.88rem;">
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <span class="lightbox-close" onclick="closeLightbox()">×</span>
    <img id="lightboxImg" src="" alt="Review image">
</div>

<script>
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('show');
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('show');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
</script>

</body>
</html>
