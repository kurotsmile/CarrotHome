</main>
<?php
$footer_page_columns = [
    'footer.company' => [
        'about' => ['footer.about', 'About'],
        'services' => ['footer.services', 'Services'],
        'contact' => ['footer.contact', 'Contact'],
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
      </div>

      <?php foreach ($footer_page_columns as $column_key => $links): ?>
        <div class="footer-column">
          <h2><?= h(ui_label($column_key, $column_key === 'footer.company' ? 'Company' : 'Legal')) ?></h2>
          <?php foreach ($links as $slug => [$label_key, $label_default]): ?>
            <a href="<?= h(page_url($slug)) ?>"><?= h(ui_label($label_key, $label_default)) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
  </div>

  <div class="container footer-bottom">
    <span>© <?php echo date('Y'); ?> <?= h(ui_label('footer.copyright', 'Carrot28. All rights reserved.')) ?></span>
    <span><?= h(ui_label('footer.built_for_discovery', 'Built for simple app discovery.')) ?></span>
  </div>
</footer>
<div class="floating-share-tools" aria-label="<?= h(ui_label('aria.page_tools', 'Page tools')) ?>">
  <button class="floating-share-tool floating-share-tool--share" type="button" aria-label="<?= h(ui_label('action.share', 'Share')) ?>" title="<?= h(ui_label('action.share', 'Share')) ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 8a3 3 0 1 0-2.8-4M8 12l8-4M8 12l8 4M8 12a3 3 0 1 1-3-3 3 3 0 0 1 3 3Zm11 7a3 3 0 1 1-3-3 3 3 0 0 1 3 3Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </button>
  <button class="floating-share-tool floating-share-tool--qr" type="button" aria-label="<?= h(ui_label('action.show_qr', 'Show QR code')) ?>" title="<?= h(ui_label('action.show_qr', 'QR code')) ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h2v2h-2v-2Zm4 0h2v2h-2v-2Zm-4 4h2v2h-2v-2Zm4 0h2v2h-2v-2Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
  </button>
  <button class="floating-share-tool floating-share-tool--mail" type="button" aria-label="<?= h(ui_label('action.send_mail', 'Send mail')) ?>" title="<?= h(ui_label('action.send_mail', 'Send mail')) ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4V6Zm0 0 8 7 8-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </button>
  <button class="floating-share-tool floating-share-tool--top" type="button" aria-label="<?= h(ui_label('action.scroll_top', 'Scroll to top')) ?>" title="<?= h(ui_label('action.scroll_top', 'Scroll to top')) ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 15 6-6 6 6M12 9v12M5 3h14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </button>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var shareButton = document.querySelector('.floating-share-tool--share');
  var qrButton = document.querySelector('.floating-share-tool--qr');
  var mailButton = document.querySelector('.floating-share-tool--mail');
  var topButton = document.querySelector('.floating-share-tool--top');

  function currentUrl() {
    return window.location.href;
  }

  if (shareButton) {
    shareButton.addEventListener('click', function () {
      var shareUrl = currentUrl();
      if (navigator.share) {
        navigator.share({title: document.title, url: shareUrl});
        return;
      }
      if (navigator.clipboard) {
        navigator.clipboard.writeText(shareUrl);
        shareButton.classList.add('is-copied');
        setTimeout(function () { shareButton.classList.remove('is-copied'); }, 1600);
      }
    });
  }

  if (qrButton) {
    qrButton.addEventListener('click', function () {
      var shareUrl = currentUrl();
      var qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=14&data=' + encodeURIComponent(shareUrl);
      if (window.Swal) {
        Swal.fire({
          title: 'QR code',
          html: '<div class="qr-popup"><img src="' + qrUrl + '" alt="QR code"><p>' + shareUrl.replace(/[&<>"']/g, function (char) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]; }) + '</p></div>',
          confirmButtonColor: '#ff5900'
        });
      } else {
        window.open(qrUrl, '_blank', 'noopener,noreferrer');
      }
    });
  }

  if (mailButton) {
    mailButton.addEventListener('click', function () {
      var shareUrl = currentUrl();
      var subject = encodeURIComponent(document.title || 'CarrotHome');
      var body = encodeURIComponent(shareUrl);
      window.location.href = 'mailto:?subject=' + subject + '&body=' + body;
    });
  }

  if (topButton) {
    topButton.addEventListener('click', function () {
      window.scrollTo({top: 0, behavior: 'smooth'});
    });
  }
});
</script>
</body>
</html>
