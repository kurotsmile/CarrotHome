</main>
<?php
$footer_page_columns = [
    'footer.company' => [
        'about' => ['footer.about', 'About'],
        'services' => ['footer.services', 'Services'],
        'contact' => ['footer.contact', 'Contact'],
        'faq' => ['footer.faq', 'FAQ'],
    ],
    'footer.legal' => [
        'privacy-policy' => ['footer.privacy_policy', 'Privacy Policy'],
        'terms-of-service' => ['footer.terms_of_service', 'Terms of Service'],
        'cookie-policy' => ['footer.cookie_policy', 'Cookie Policy'],
        'disclaimer' => ['footer.disclaimer', 'Disclaimer'],
    ],
];
?>
<footer class="site-footer">
  <div class="container footer-inner">
    <div class="footer-brand">
      <a class="footer-logo" href="index.php"><img class="brand-logo-img" src="images/carrot_28.png" alt="CarrotHome"></a>
      <p><?= h(ui_label('footer.description', 'Kho app và game được sắp xếp gọn gàng, dễ tìm, dễ tải cho nhiều nền tảng.')) ?></p>
    </div>

    <nav class="footer-menu" aria-label="<?= h(ui_label('aria.footer_navigation', 'Footer navigation')) ?>">
      <div class="footer-column">
        <h2><?= h(ui_label('nav.explore', 'Explore')) ?></h2>
        <a href="index.php"><?= h(ui_label('nav.home', 'Home')) ?></a>
        <a href="index.php?type=app"><?= h(ui_label('nav.applications', 'Applications')) ?></a>
        <a href="index.php?type=game"><?= h(ui_label('nav.game', 'Game')) ?></a>
        <a href="index.php?status=public"><?= h(ui_label('nav.latest', 'Latest')) ?></a>
      </div>

      <?php foreach ($footer_page_columns as $column_key => $links): ?>
        <div class="footer-column">
          <h2><?= h(ui_label($column_key, $column_key === 'footer.company' ? 'Company' : 'Legal')) ?></h2>
          <?php foreach ($links as $slug => [$label_key, $label_default]): ?>
            <a href="<?= h(base_url('index.php?page=' . urlencode($slug))) ?>"><?= h(ui_label($label_key, $label_default)) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
  </div>

  <div class="container footer-bottom">
    <span>© <?php echo date('Y'); ?> CarrotHome. All rights reserved.</span>
    <span><?= h(ui_label('footer.built_for_discovery', 'Built for simple app discovery.')) ?></span>
  </div>
</footer>
</body>
</html>
