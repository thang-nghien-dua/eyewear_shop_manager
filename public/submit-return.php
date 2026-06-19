<?php

require_once __DIR__ . '/../app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';
require_once BASE_PATH . '/app/middleware/auth.php';

auth_only();
require_login();
$user = auth_user();
$db = Database::connect();

$orderCode = trim((string) ($_GET['code'] ?? ''));

if ($orderCode === '') {
    add_flash('warning', 'Vui lòng chọn đơn hàng để gửi yêu cầu.');
    redirect_to('/profile.php#returns');
}

// Lấy đơn hàng thuộc về user hiện tại
$orderStmt = $db->prepare('SELECT * FROM orders WHERE order_code = :code AND user_id = :uid LIMIT 1');
$orderStmt->execute(['code' => $orderCode, 'uid' => $user['id']]);
$order = $orderStmt->fetch();

if (!$order) {
    add_flash('warning', 'Không tìm thấy đơn hàng hoặc đơn hàng không thuộc về bạn.');
    redirect_to('/profile.php#returns');
}

// Kiểm tra trạng thái đơn hàng (yêu cầu đổi trả/bảo hành chỉ áp dụng cho đơn hoàn thành)
if ($order['status'] !== 'completed') {
    add_flash('warning', 'Chỉ có đơn hàng ở trạng thái "Hoàn tất" mới có thể gửi yêu cầu Đổi trả / Bảo hành.');
    redirect_to('/profile.php#returns');
}

// Lấy các sản phẩm trong đơn hàng
$itemsStmt = $db->prepare('
    SELECT oi.*, p.thumbnail, p.slug 
    FROM order_items oi
    INNER JOIN product_variants pv ON pv.id = oi.product_variant_id
    INNER JOIN products p ON p.id = pv.product_id
    WHERE oi.order_id = :order_id
');
$itemsStmt->execute(['order_id' => $order['id']]);
$orderItems = $itemsStmt->fetchAll();

// Kiểm tra xem đã có yêu cầu nào gửi chưa
$checkStmt = $db->prepare('SELECT * FROM return_requests WHERE order_id = :order_id LIMIT 1');
$checkStmt->execute(['order_id' => $order['id']]);
$existingRequest = $checkStmt->fetch();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestType = trim((string) ($_POST['request_type'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? ''));

    if ($existingRequest) {
        $errors[] = 'Bạn đã gửi một yêu cầu cho đơn hàng này rồi.';
    }

    if (!in_array($requestType, ['return', 'exchange', 'warranty', 'refund'], true)) {
        $errors[] = 'Loại yêu cầu không hợp lệ.';
    }

    if ($reason === '') {
        $errors[] = 'Vui lòng cung cấp lý do chi tiết.';
    }

    // Xử lý upload ảnh (tối đa 3 ảnh)
    $uploadedImages = [];
    if (empty($errors) && !empty($_FILES['return_images']['name'][0])) {
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxSize     = 5 * 1024 * 1024; // 5MB
        $uploadDir = PUBLIC_PATH . '/assets/uploads/returns/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uploadCount = 0;
        foreach ($_FILES['return_images']['tmp_name'] as $idx => $tmpName) {
            if ($uploadCount >= 3) break;
            if ($_FILES['return_images']['error'][$idx] !== UPLOAD_ERR_OK) {
                continue;
            }
            if ($_FILES['return_images']['size'][$idx] > $maxSize) {
                $errors[] = "Ảnh thứ " . ($idx + 1) . " vượt quá dung lượng cho phép (5MB).";
                continue;
            }

            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $tmpName);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedMime, true)) {
                $errors[] = "Ảnh thứ " . ($idx + 1) . " không đúng định dạng hình ảnh (JPG/PNG/WebP/GIF).";
                continue;
            }

            $ext = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/gif'  => 'gif',
                default      => 'jpg',
            };
            $filename = 'ret_' . $user['id'] . '_' . $order['id'] . '_' . time() . '_' . $uploadCount . '.' . $ext;
            $destPath = $uploadDir . $filename;

            if (move_uploaded_file($tmpName, $destPath)) {
                $uploadedImages[] = '/assets/uploads/returns/' . $filename;
                $uploadCount++;
            }
        }
    }

    if ($errors === []) {
        $insertStmt = $db->prepare('
            INSERT INTO return_requests (order_id, user_id, request_type, reason, status, images)
            VALUES (:order_id, :user_id, :request_type, :reason, "pending", :images)
        ');
        $insertStmt->execute([
            'order_id' => $order['id'],
            'user_id' => $user['id'],
            'request_type' => $requestType,
            'reason' => $reason,
            'images' => !empty($uploadedImages) ? json_encode($uploadedImages) : null
        ]);

        add_flash('success', 'Gửi yêu cầu đổi trả / bảo hành thành công. Nhân viên sẽ liên hệ lại bạn sớm nhất.');
        redirect_to('/profile.php#returns');
    }
}

$pageTitle = 'Gửi yêu cầu đổi trả / bảo hành';
require_once BASE_PATH . '/app/views/partials/head.php';
require_once BASE_PATH . '/app/views/partials/header.php';
?>
<main class="page-section" style="background:#f8fafc; padding: 3rem 0;">
    <div class="container" style="max-width: 800px;">
        <div style="margin-bottom: 2rem;">
            <a href="<?= e(APP_URL) ?>/profile.php#returns" style="text-decoration:none; color:#1a2e4a; font-weight:700; display:flex; align-items:center; gap:0.5rem; font-size:0.95rem;">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Quay lại trang cá nhân
            </a>
        </div>

        <section class="premium-card" style="background: #fff; padding: 2.5rem; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid #edf2f7;">
            <div style="margin-bottom: 2rem; border-bottom: 2px solid #edf2f7; padding-bottom: 1.5rem;">
                <h1 style="font-size: 1.85rem; font-weight: 800; color: #1a2e4a; margin: 0 0 0.5rem 0; display:flex; align-items:center; gap:0.75rem;">
                    <span>↩️</span> Yêu cầu Đổi trả / Bảo hành
                </h1>
                <p style="color: #718096; margin: 0; font-size:0.95rem;">Đơn hàng: <strong style="color:#1a2e4a;"><?= e($orderCode) ?></strong> &bull; Ngày đặt: <strong><?= e(date('d/m/Y', strtotime($order['created_at']))) ?></strong></p>
            </div>

            <!-- Khung hiển thị sản phẩm để nhận diện -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 2rem;">
                <h3 style="font-size: 0.95rem; font-weight: 800; color: #4a5568; margin: 0 0 1rem 0; text-transform: uppercase; letter-spacing: 0.05em;">Sản phẩm trong đơn hàng</h3>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach ($orderItems as $item): ?>
                        <div style="display: flex; align-items: center; gap: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #edf2f7; last-child: border-bottom-none;">
                            <img src="<?= e($item['thumbnail'] ? (str_starts_with($item['thumbnail'],'/') ? APP_URL.$item['thumbnail'] : APP_URL.'/'.$item['thumbnail']) : APP_URL.'/assets/images/placeholder-glasses.png') ?>" 
                                 alt="<?= e($item['product_name']) ?>" 
                                 style="width: 60px; height: 60px; object-fit: contain; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; padding: 4px;">
                            <div style="flex: 1;">
                                <h4 style="font-size: 0.95rem; font-weight: 700; color: #1a2e4a; margin: 0 0 0.25rem 0;"><?= e($item['product_name']) ?></h4>
                                <div style="font-size: 0.82rem; color: #718096;">
                                    Số lượng: <strong><?= (int)$item['quantity'] ?></strong> &middot; Giá: <strong><?= format_price($item['unit_price']) ?></strong>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($errors !== []): ?>
                <div class="alert warning" style="margin-bottom: 1.5rem; border-radius: 8px;">
                    <ul class="form-error-list" style="margin: 0; padding-left: 1.25rem;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($existingRequest): ?>
                <div style="background: #fff9f0; border: 1.5px solid #ffe8cc; border-radius: 12px; padding: 1.75rem; text-align: center;">
                    <h3 style="color: #d4880a; margin-top: 0; font-size:1.2rem; font-weight:800;">Yêu cầu đang được xử lý</h3>
                    <p style="margin-bottom: 1.25rem; color:#4a5568;">Bạn đã gửi một yêu cầu cho đơn hàng này vào ngày <strong><?= e(date('d/m/Y H:i', strtotime($existingRequest['created_at']))) ?></strong>.</p>
                    <div style="display: inline-block; background: #d4880a; color: #fff; padding: 0.5rem 1.5rem; border-radius: 99px; font-weight: 800; font-size: 0.85rem; text-transform:uppercase; letter-spacing:0.05em;">
                        Trạng thái: <?= e(
                            match ($existingRequest['status']) {
                                'pending' => 'Chờ duyệt',
                                'approved' => 'Đã duyệt',
                                'rejected' => 'Bị từ chối',
                                'received' => 'Đã nhận sản phẩm',
                                'resolved' => 'Đã giải quyết xong',
                                default => $existingRequest['status']
                            }
                        ) ?>
                    </div>
                    <?php if (!empty($existingRequest['resolution_note'])): ?>
                        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px dashed #ffe8cc; text-align: left;">
                            <strong style="color:#1a2e4a;">Ghi chú phản hồi:</strong>
                            <p style="margin: 0.5rem 0 0 0; color: #4a5568; line-height:1.6;"><?= nl2br(e($existingRequest['resolution_note'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form method="POST" enctype="multipart/form-data" class="form-grid">
                    <div class="form-field" style="grid-column: span 2; margin-bottom: 1.5rem;">
                        <label for="request_type" style="font-weight: 700; color: #1a2e4a; margin-bottom: 0.5rem; display: block; font-size:0.95rem;">Chọn loại yêu cầu *</label>
                        <select id="request_type" name="request_type" required style="width: 100%; padding: 0.75rem; border: 1.5px solid #dbe4e7; border-radius: 8px; font-size: 0.95rem; outline: none; background: #f8fafc; font-weight:600; color:#1a2e4a;">
                            <option value="return">Đổi hàng mới (Trả hàng đổi mẫu/size)</option>
                            <option value="exchange">Đổi sản phẩm lỗi (Do lỗi nhà sản xuất)</option>
                            <option value="warranty">Yêu cầu bảo hành (Sửa chữa kính/ốc/phụ kiện)</option>
                            <option value="refund">Hoàn tiền (Trả hàng hoàn tiền)</option>
                        </select>
                    </div>

                    <div class="form-field" style="grid-column: span 2; margin-bottom: 1.5rem;">
                        <label for="reason" style="font-weight: 700; color: #1a2e4a; margin-bottom: 0.5rem; display: block; font-size:0.95rem;">Lý do chi tiết *</label>
                        <textarea id="reason" name="reason" rows="6" placeholder="Vui lòng cung cấp lý do cụ thể và tình trạng sản phẩm cần hỗ trợ..." required style="width: 100%; padding: 0.75rem; border: 1.5px solid #dbe4e7; border-radius: 8px; font-size: 0.95rem; outline: none; background: #f8fafc; font-family: inherit; resize: vertical; box-sizing:border-box; line-height:1.6;"></textarea>
                    </div>

                    <!-- Khu vực upload hình ảnh minh họa -->
                    <div class="form-field" style="grid-column: span 2; margin-bottom: 1.5rem;">
                        <label style="font-weight: 700; color: #1a2e4a; margin-bottom: 0.5rem; display: block; font-size:0.95rem;">Hình ảnh minh chứng (Tối đa 3 ảnh, mỗi ảnh < 5MB) *</label>
                        
                        <div style="border: 2px dashed #c8dff7; border-radius: 12px; padding: 1.5rem; text-align: center; background: #f0f7ff; cursor: pointer; transition: all 0.2s;" onclick="document.getElementById('return_images').click()">
                            <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#1a2e4a" style="margin: 0 auto 0.75rem; opacity: 0.8;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <p style="font-weight: 700; color: #1a2e4a; margin: 0 0 0.25rem 0; font-size:0.9rem;">Chọn ảnh để tải lên</p>
                            <p style="font-size: 0.8rem; color: #718096; margin: 0;">Chấp nhận ảnh định dạng JPG, PNG, WebP hoặc GIF</p>
                            <input type="file" id="return_images" name="return_images[]" multiple accept="image/*" style="display: none;" onchange="previewImages(event)">
                        </div>

                        <!-- Khung hiển thị preview ảnh -->
                        <div id="image_preview_container" style="display: flex; gap: 0.75rem; margin-top: 1rem; flex-wrap: wrap;"></div>
                    </div>

                    <div style="grid-column: span 2; display: flex; justify-content: flex-end; margin-top: 1rem;">
                        <button type="submit" class="btn-primary" style="padding: 0.85rem 3rem; font-weight: 800; font-size: 1rem; border-radius: 8px; border: none; cursor: pointer; transition: all 0.2s;">
                            Gửi yêu cầu đổi trả
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>
</main>

<script>
function previewImages(event) {
    const container = document.getElementById('image_preview_container');
    container.innerHTML = '';
    const files = event.target.files;

    if (files.length > 3) {
        alert("Bạn chỉ được phép chọn tối đa 3 hình ảnh.");
        event.target.value = '';
        return;
    }

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const reader = new FileReader();

        reader.onload = function(e) {
            const wrapper = document.createElement('div');
            wrapper.style.position = 'relative';
            wrapper.style.width = '80px';
            wrapper.style.height = '80px';
            wrapper.style.border = '1px solid #edf2f7';
            wrapper.style.borderRadius = '8px';
            wrapper.style.overflow = 'hidden';
            wrapper.style.boxShadow = '0 2px 6px rgba(0,0,0,0.05)';

            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.width = '100%';
            img.style.height = '100%';
            img.style.objectFit = 'cover';

            wrapper.appendChild(img);
            container.appendChild(wrapper);
        }

        reader.readAsDataURL(file);
    }
}
</script>
<?php require_once BASE_PATH . '/app/views/partials/footer.php'; ?>
