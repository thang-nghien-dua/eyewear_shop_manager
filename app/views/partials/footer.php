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
        <li><a href="#contact" class="footer-link">Liên hệ chúng tôi</a></li>
        <li><a href="#" class="footer-link">Hướng dẫn chọn gọng</a></li>
        <li><a href="#" class="footer-link">Chính sách đổi trả</a></li>
        <li><a href="#" class="footer-link">Tư vấn prescription</a></li>
      </ul>
    </div>

    <div class="footer-bottom">&copy; <?= date('Y') ?> LUMINA Optical Atelier. All rights reserved.</div>
  </div>
</footer>

<!-- Scroll to top button -->
<button id="scroll-top-btn" aria-label="Lên đầu trang" onclick="window.scrollTo({top:0,behavior:'smooth'})">
  <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
</button>

<!-- Global toast container -->
<div class="lumina-toast" id="lumina-toast"></div>

<script src="<?= e(APP_URL) ?>/assets/js/optical-theme.js"></script>
<script>
// Scroll to top button
(function() {
  var btn = document.getElementById('scroll-top-btn');
  if (!btn) return;
  window.addEventListener('scroll', function() {
    if (window.scrollY > 300) btn.classList.add('visible');
    else btn.classList.remove('visible');
  }, { passive: true });
})();

// Global toast function
window.showToast = function(msg, type) {
  var t = document.getElementById('lumina-toast');
  if (!t) return;
  t.textContent = msg;
  t.className = 'lumina-toast' + (type ? ' ' + type : '');
  t.classList.add('show');
  clearTimeout(t._timer);
  t._timer = setTimeout(function() { t.classList.remove('show'); }, 3000);
};

// Newsletter form with toast feedback
(function() {
  var form = document.getElementById('newsletter-form');
  if (!form) return;
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    var input = document.getElementById('newsletter-input');
    if (input && input.value) {
      window.showToast('✓ Cảm ơn bạn đã đăng ký! Chúng tôi sẽ liên hệ sớm.', 'success');
      input.value = '';
    }
  });
})();
</script>
</body>
</html>
