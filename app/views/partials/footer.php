<section class="newsletter">
  <div class="newsletter-container">
    <h2 class="newsletter-title">Nhận Ưu Đãi Độc Quyền</h2>
    <p class="newsletter-description">Nhận thông báo về sản phẩm mới, ưu đãi độc quyền và mẹo chăm sóc mắt từ LUMINA.</p>
    <form class="newsletter-form" id="newsletter-form">
      <input type="email" id="newsletter-input" placeholder="Nhập email của bạn" required class="newsletter-input">
      <button type="submit" class="newsletter-btn">Đăng ký</button>
    </form>
  </div>
</section>

<footer class="footer">
  <div class="footer-container">
    <div class="footer-brand">
      <div class="footer-logo">LUMINA</div>
      <p class="footer-description">Bộ sưu tập kính mắt premium với thiết kế tinh tế, dữ liệu sản phẩm đồng bộ từ hệ thống và trải nghiệm mua hàng rõ ràng.</p>
    </div>

    <div>
      <h4 class="footer-section-title">Mua sắm</h4>
      <ul class="footer-links">
        <li><a href="<?= e(APP_URL) ?>/products.php" class="footer-link">Tất cả sản phẩm</a></li>
        <li><a href="<?= e(APP_URL) ?>/products.php?category=gong-kinh" class="footer-link">Gọng kính</a></li>
        <li><a href="<?= e(APP_URL) ?>/products.php?category=kinh-mat" class="footer-link">Kính mát</a></li>
        <li><a href="<?= e(APP_URL) ?>/products.php?category=trong-kinh" class="footer-link">Tròng kính</a></li>
      </ul>
    </div>

    <div>
      <h4 class="footer-section-title">Khách hàng</h4>
      <ul class="footer-links">
        <li><a href="<?= e(APP_URL) ?>/cart.php" class="footer-link">Giỏ hàng</a></li>
        <li><a href="<?= e(APP_URL) ?>/orders.php" class="footer-link">Tra cứu đơn hàng</a></li>
        <li><a href="<?= e(APP_URL) ?>/profile.php" class="footer-link">Tài khoản</a></li>
        <li><a href="<?= e(APP_URL) ?>/login.php" class="footer-link">Đăng nhập</a></li>
      </ul>
    </div>

    <div>
      <h4 class="footer-section-title">Hỗ trợ</h4>
      <ul class="footer-links">
        <li><a href="#" class="footer-link">Liên hệ chúng tôi</a></li>
        <li><a href="#" class="footer-link">Hướng dẫn chọn gọng</a></li>
        <li><a href="#" class="footer-link">Chính sách đổi trả</a></li>
        <li><a href="#" class="footer-link">Tư vấn prescription</a></li>
      </ul>
    </div>

    <div class="footer-bottom">&copy; <?= date('Y') ?> LUMINA. UI port từ source bạn cung cấp.</div>
  </div>
</footer>
<script src="<?= e(APP_URL) ?>/assets/js/optical-theme.js"></script>
</body>
</html>
