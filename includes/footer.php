</main>
<?php
$footer_page_columns = [
    'Company' => [
        'about' => 'About',
        'services' => 'Services',
        'contact' => 'Contact',
        'faq' => 'FAQ',
    ],
    'Legal' => [
        'privacy-policy' => 'Privacy Policy',
        'terms-of-service' => 'Terms of Service',
        'cookie-policy' => 'Cookie Policy',
        'disclaimer' => 'Disclaimer',
    ],
];
?>
<footer class="site-footer">
  <div class="container footer-inner">
    <div class="footer-brand">
      <a class="footer-logo" href="index.php"><img class="brand-logo-img" src="images/carrot_28.png" alt="CarrotHome"></a>
      <p>Kho app và game được sắp xếp gọn gàng, dễ tìm, dễ tải cho nhiều nền tảng.</p>
    </div>

    <nav class="footer-menu" aria-label="Footer navigation">
      <div class="footer-column">
        <h2>Explore</h2>
        <a href="index.php">Home</a>
        <a href="index.php?type=app">Applications</a>
        <a href="index.php?type=game">Game</a>
        <a href="index.php?status=public">Latest</a>
      </div>

      <?php foreach ($footer_page_columns as $column_title => $links): ?>
        <div class="footer-column">
          <h2><?= h($column_title) ?></h2>
          <?php foreach ($links as $slug => $label): ?>
            <a href="<?= h(base_url('index.php?page=' . urlencode($slug))) ?>"><?= h($label) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
  </div>

  <div class="container footer-bottom">
    <span>© <?php echo date('Y'); ?> CarrotHome. All rights reserved.</span>
    <span>Built for simple app discovery.</span>
  </div>
</footer>
</body>
</html>
