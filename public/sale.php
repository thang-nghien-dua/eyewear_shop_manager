<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once BASE_PATH . '/app/helpers/functions.php';

$pageTitle = 'Sale & Ưu Đãi - ' . APP_NAME;
$pageDescription = 'Chương trình khuyến mãi, sale ưu đãi gọng kính, kính mát và tròng kính tại LUMINA.';
require_once BASE_PATH . '/app/views/partials/head.php';
require_once BASE_PATH . '/app/views/partials/header.php';
?>

<style>
.sale-page {
  background: #f8f9fa;
  min-height: 60vh;
}
.sale-container {
  max-width: 960px;
  margin: 0 auto;
  padding: clamp(2rem, 5vw, 4rem) clamp(1rem, 4vw, 2rem);
}
.sale-kicker {
  display: inline-block;
  color: #016b5e;
  font-size: .75rem;
  font-weight: 800;
  letter-spacing: .14em;
  text-transform: uppercase;
  margin-bottom: .75rem;
}
.sale-title {
  font-size: clamp(1.6rem, 3vw, 2.4rem);
  font-weight: 800;
  color: #1a2e4a;
  line-height: 1.2;
  margin: 0 0 .6rem;
}
.sale-meta {
  font-size: .82rem;
  color: #9aa3a6;
  margin-bottom: 2rem;
}
.sale-meta strong { color: #586064; }

/* Hero banner image */
.sale-hero-img {
  width: 100%;
  border-radius: 14px;
  overflow: hidden;
  margin-bottom: 2.5rem;
  box-shadow: 0 4px 24px rgba(26,46,74,.10);
}
.sale-hero-img img {
  width: 100%;
  height: auto;
  display: block;
}

/* Content body */
.sale-body {
  background: #fff;
  border-radius: 14px;
  padding: 2.5rem;
  border: 1px solid #e4ebee;
  font-size: .95rem;
  color: #2b3437;
  line-height: 1.8;
}

.sale-section {
  margin-bottom: 2rem;
  padding-bottom: 2rem;
  border-bottom: 1px solid #f0f3f5;
}
.sale-section:last-child {
  border-bottom: none;
  margin-bottom: 0;
  padding-bottom: 0;
}

.sale-section h2 {
  font-size: 1.15rem;
  font-weight: 800;
  color: #1a2e4a;
  margin: 0 0 .85rem;
  display: flex;
  align-items: center;
  gap: .5rem;
}
.sale-section h2 .sale-num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  background: #1a2e4a;
  color: #f5b700;
  border-radius: 50%;
  font-size: .8rem;
  flex: 0 0 28px;
}

.sale-section h3 {
  font-size: 1rem;
  font-weight: 700;
  color: #016b5e;
  margin: 1rem 0 .5rem;
}

.sale-section p {
  margin: .4rem 0;
  color: #586064;
}

.sale-note {
  display: inline-block;
  background: #fff7e6;
  border: 1px solid #fce8b3;
  border-radius: 6px;
  padding: .4rem .85rem;
  font-size: .83rem;
  color: #92600a;
  font-weight: 600;
  margin-top: .6rem;
}

.sale-highlight-list {
  list-style: none;
  padding: 0;
  margin: .8rem 0 0;
  display: grid;
  gap: .5rem;
}
.sale-highlight-list li {
  display: flex;
  align-items: flex-start;
  gap: .6rem;
  color: #586064;
}
.sale-highlight-list li::before {
  content: '✓';
  color: #016b5e;
  font-weight: 800;
  flex: 0 0 auto;
  margin-top: .1rem;
}

.sale-why-box {
  background: linear-gradient(135deg, #1a2e4a 0%, #2d4563 100%);
  border-radius: 12px;
  padding: 1.75rem 2rem;
  color: #fff;
  margin-top: 2rem;
}
.sale-why-box h2 {
  color: #f5b700;
  margin-bottom: 1rem;
}
.sale-why-box li { color: rgba(255,255,255,.88); }
.sale-why-box li::before { color: #f5b700; }

.sale-hotline-banner {
  margin-top: 2rem;
  background: #e8f0ed;
  border: 1px solid #b2d8ce;
  border-radius: 10px;
  padding: 1.2rem 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}
.sale-hotline-banner .icon {
  font-size: 1.6rem;
}
.sale-hotline-banner p {
  margin: 0;
  color: #586064;
  font-size: .9rem;
}
.sale-hotline-banner strong {
  color: #016b5e;
  font-size: 1.05rem;
}
</style>

<div class="sale-page">
  <div class="sale-container">
    <span class="sale-kicker">LUMINA × Khuyến Mãi</span>
    <h1 class="sale-title">Ưu Đãi Gọng Kính Từ 0Đ Mừng Reopening Toàn Hệ Thống</h1>
    <p class="sale-meta"><strong>Blog Kính Mắt</strong> · Thứ Bảy, 23/11/2024 10:39 (GMT +07:00)</p>

    <!-- Hero Banner Image -->
    <div class="sale-hero-img">
      <img src="<?= e(APP_URL) ?>/assets/images/sale.png" alt="Ưu đãi sale kính mắt Eye Plus - Hè Rực Rỡ Deal Hết Cỡ">
    </div>

    <!-- Sale Content -->
    <div class="sale-body">

      <div class="sale-section">
        <h2><span class="sale-num">1</span> Đồng giá 39K</h2>
        <p><strong>Bộ sưu tập Summer Boom:</strong> Gọng kính đồng giá 39K khi cắt tròng từ 450K, cắt tròng &lt;450K hoặc không cắt tròng giảm 50%.</p>
        <span class="sale-note">⚠ Lưu ý: Không áp dụng kèm Voucher đặt lịch.</span>
      </div>

      <div class="sale-section">
        <h2><span class="sale-num">2</span> Sale upto 50% gọng kính / kính râm</h2>

        <h3>2.1. Bộ sưu tập 'The Multi Look'</h3>
        <p>Giảm 50% cho gọng kính / kính râm trong Bộ sưu tập "The Multi Look" (không cần điều kiện cắt tròng).</p>

        <h3>2.2. Bộ sưu tập New Arrival</h3>
        <p>Giảm 20% cho gọng kính đã chọn nếu cắt kèm tròng kính.</p>

        <h3>2.3. Kính râm</h3>
        <p>Giảm 20% không cần điều kiện cắt tròng.</p>
      </div>

      <div class="sale-section">
        <h2><span class="sale-num">3</span> Tròng kính tiên phong</h2>
        <p>Free gọng kính 450K hoặc Giảm giá 450K nâng lên gọng cao hơn nếu cắt kèm tròng kính <strong>Chemi 1.67 / 1.74 DAS</strong>.</p>
        <span class="sale-note">⚠ Lưu ý: Không áp dụng kèm Voucher đặt lịch.</span>
      </div>

      <div class="sale-section">
        <h2>🎁 Quà tặng thêm</h2>
        <ul class="sale-highlight-list">
          <li>Tặng <strong>VOUCHER 50K</strong> cho tất cả khách hàng đặt lịch trước với Eye Plus.</li>
          <li>Tặng <strong>khay đựng lens chính hãng SEED</strong> khi mua kính áp tròng 1 tháng.</li>
        </ul>
      </div>

      <div class="sale-section sale-why-box">
        <h2>TẠI SAO NÊN CHỌN KÍNH MẮT EYE PLUS?</h2>
        <ul class="sale-highlight-list">
          <li>Sản phẩm chính hãng, bảo vệ thị lực tối ưu.</li>
          <li>Mẫu mã đa dạng, bắt trend nhanh chóng.</li>
          <li>Giá hợp lý, ưu đãi liên tục.</li>
          <li>Dịch vụ chuyên nghiệp, tư vấn tận tâm.</li>
        </ul>
      </div>

      <div class="sale-section" style="margin-top:1.5rem;">
        <p style="color:#9aa3a6;font-size:.85rem;">
          📍 Chương trình áp dụng duy nhất trong tháng 5 tại tất cả các cửa hàng Kính mắt Eye Plus trên toàn quốc.
        </p>
      </div>

    </div><!-- /.sale-body -->

    <!-- Hotline banner -->
    <div class="sale-hotline-banner">
      <span class="icon">📞</span>
      <p>Để biết thêm chi tiết, vui lòng liên hệ hotline <strong>0904 915 377</strong> hoặc ghé cửa hàng gần nhất.</p>
    </div>

  </div><!-- /.sale-container -->
</div>

<?php require_once BASE_PATH . '/app/views/partials/footer.php'; ?>
