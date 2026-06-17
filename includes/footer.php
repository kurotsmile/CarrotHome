</main>
<?php $footer_pages = fetch_footer_pages($pdo ?? null, $page_lang ?? ($_GET['lang'] ?? 'vi')); ?>
<footer class="site-footer">
  <div class="container footer-inner">
    <div class="footer-brand">
      <a class="footer-logo" href="index.php"><img class="brand-logo-img" src="images/carrot_28.png" alt="CarrotHome"></a>
      <p>Kho app và game được sắp xếp gọn gàng, dễ tìm, dễ tải cho nhiều nền tảng.</p>
    </div>

    <nav class="footer-menu" aria-label="Footer navigation">
      <div class="footer-column">
        <h2>Khám phá</h2>
        <a href="index.php">Trang chủ</a>
        <a href="index.php?type=app">Ứng dụng</a>
        <a href="index.php?type=game">Game</a>
        <a href="index.php?status=public">Mới cập nhật</a>
      </div>

      <?php if ($footer_pages): ?>
        <?php foreach ($footer_pages as $type => $pages): ?>
          <div class="footer-column">
            <h2><?= h($type) ?></h2>
            <?php foreach ($pages as $page): ?>
              <a href="<?= h(page_url($page['slug'], $page['lang'] ?? '')) ?>"><?= h($page['title']) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="footer-column">
          <h2>Thông tin</h2>
          <a href="#">About</a>
          <a href="#">Service</a>
          <a href="#">Contact</a>
          <a href="#">FAQ</a>
        </div>

        <div class="footer-column">
          <h2>Pháp lý</h2>
          <a href="#">Chính sách</a>
          <a href="#">Cookie</a>
          <a href="#">Điều khoản</a>
          <a href="#">Disclaimer</a>
        </div>
      <?php endif; ?>
    </nav>
  </div>

  <div class="container footer-bottom">
    <span>© <?php echo date('Y'); ?> CarrotHome. All rights reserved.</span>
    <span>Built for simple app discovery.</span>
  </div>
</footer>
</body>
</html>
