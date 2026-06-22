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
  margin-top:48px;
  padding:58px 0 30px;
  border-top:1px solid var(--line);
  background:
    radial-gradient(circle at 12% 0%,rgba(255,89,0,.12),transparent 30%),
    radial-gradient(circle at 86% 18%,rgba(255,183,77,.14),transparent 28%),
    radial-gradient(circle at 50% 100%,rgba(13,12,34,.045),transparent 34%),
    #fff;
  color:var(--muted);
  box-shadow:0 -18px 42px rgba(13,12,34,.045);
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
  border:1px solid rgba(231,231,233,.78);
  border-radius:30px;
  background:
    linear-gradient(130deg,rgba(255,255,255,.82),rgba(255,255,255,.42) 42%,rgba(248,247,244,.62)),
    rgba(255,255,255,.7);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.95),0 18px 44px rgba(13,12,34,.055);
  backdrop-filter:blur(18px) saturate(150%);
  -webkit-backdrop-filter:blur(18px) saturate(150%);
}
.site-footer.site-footer--liquid::after{
  width:360px;
  height:360px;
  right:-135px;
  top:-165px;
  border-radius:999px;
  background:rgba(255,89,0,.1);
  filter:blur(76px);
}
.site-footer--liquid .footer-inner,
.site-footer--liquid .footer-bottom{
  position:relative;
  z-index:1;
}
.site-footer--liquid .footer-brand,
.site-footer--liquid .footer-column{
  border:1px solid rgba(231,231,233,.86);
  border-radius:22px;
  background:linear-gradient(145deg,rgba(255,255,255,.92),rgba(255,255,255,.64));
  box-shadow:inset 0 1px 0 rgba(255,255,255,.95),0 14px 32px rgba(13,12,34,.06);
  backdrop-filter:blur(14px) saturate(145%);
  -webkit-backdrop-filter:blur(14px) saturate(145%);
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
  height:36px;
  filter:drop-shadow(0 8px 18px rgba(255,89,0,.12));
}
.site-footer--liquid .footer-brand p{
  color:var(--muted);
}
.site-footer--liquid .footer-column h2{
  color:var(--text);
}
.site-footer--liquid .footer-column a{
  color:#565564;
  transition:color .22s ease,transform .22s ease,text-shadow .22s ease;
}
.site-footer--liquid .footer-column a:hover{
  color:var(--accent);
  transform:translateX(4px);
  text-shadow:0 8px 22px rgba(255,89,0,.18);
}
.site-footer--liquid .footer-bottom{
  border-top-color:rgba(231,231,233,.86);
  color:var(--muted);
}
@media (max-width:680px){
  .site-footer.site-footer--liquid{
    padding-top:46px;
  }
  .site-footer.site-footer--liquid::before{
    inset:10px;
    border-radius:24px;
  }
  .site-footer--liquid .footer-brand,
  .site-footer--liquid .footer-column{
    border-radius:18px;
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