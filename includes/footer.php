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
<style>
.site-footer.site-footer--liquid{
  position:relative;
  isolation:isolate;
  overflow:hidden;
  margin-top:56px;
  padding:64px 0 32px;
  border-top:1px solid rgba(255,255,255,.42);
  background:
    radial-gradient(circle at 10% 0%,rgba(255,89,0,.32),transparent 34%),
    radial-gradient(circle at 88% 15%,rgba(255,190,89,.28),transparent 30%),
    radial-gradient(circle at 50% 100%,rgba(104,154,255,.18),transparent 35%),
    linear-gradient(135deg,rgba(25,23,38,.92),rgba(61,39,28,.86) 48%,rgba(15,17,28,.94));
  color:rgba(255,255,255,.76);
  box-shadow:0 -28px 80px rgba(13,12,34,.22);
}
.site-footer.site-footer--liquid::before,
.site-footer.site-footer--liquid::after{
  content:"";
  position:absolute;
  z-index:-1;
  pointer-events:none;
}
.site-footer.site-footer--liquid::before{
  inset:18px;
  border:1px solid rgba(255,255,255,.18);
  border-radius:34px;
  background:
    linear-gradient(120deg,rgba(255,255,255,.22),transparent 30%,rgba(255,255,255,.08) 62%,rgba(255,255,255,.16)),
    rgba(255,255,255,.04);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.2);
  backdrop-filter:blur(24px) saturate(170%);
  -webkit-backdrop-filter:blur(24px) saturate(170%);
}
.site-footer.site-footer--liquid::after{
  width:420px;
  height:420px;
  right:-150px;
  top:-170px;
  border-radius:999px;
  background:rgba(255,255,255,.18);
  filter:blur(80px);
}
.site-footer--liquid .footer-inner,
.site-footer--liquid .footer-bottom{
  position:relative;
  z-index:1;
}
.site-footer--liquid .footer-brand,
.site-footer--liquid .footer-column{
  border:1px solid rgba(255,255,255,.2);
  border-radius:26px;
  background:linear-gradient(135deg,rgba(255,255,255,.16),rgba(255,255,255,.06));
  box-shadow:inset 0 1px 0 rgba(255,255,255,.24),0 18px 44px rgba(0,0,0,.18);
  backdrop-filter:blur(18px) saturate(165%);
  -webkit-backdrop-filter:blur(18px) saturate(165%);
}
.site-footer--liquid .footer-brand{
  padding:24px;
}
.site-footer--liquid .footer-column{
  padding:20px;
}
.site-footer--liquid .footer-logo{
  margin-bottom:16px;
}
.site-footer--liquid .footer-logo .brand-logo-img{
  height:38px;
  filter:drop-shadow(0 10px 24px rgba(0,0,0,.24));
}
.site-footer--liquid .footer-brand p{
  color:rgba(255,255,255,.74);
}
.site-footer--liquid .footer-column h2{
  color:#fff;
  text-shadow:0 1px 18px rgba(255,255,255,.2);
}
.site-footer--liquid .footer-column a{
  color:rgba(255,255,255,.76);
  transition:color .22s ease,transform .22s ease,text-shadow .22s ease;
}
.site-footer--liquid .footer-column a:hover{
  color:#fff;
  transform:translateX(4px);
  text-shadow:0 0 18px rgba(255,255,255,.52);
}
.site-footer--liquid .footer-bottom{
  border-top-color:rgba(255,255,255,.18);
  color:rgba(255,255,255,.64);
}
@media (max-width:680px){
  .site-footer.site-footer--liquid{
    padding-top:48px;
  }
  .site-footer.site-footer--liquid::before{
    inset:10px;
    border-radius:26px;
  }
  .site-footer--liquid .footer-brand,
  .site-footer--liquid .footer-column{
    border-radius:22px;
  }
}
</style>
<footer class="site-footer site-footer--liquid">
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