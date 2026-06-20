<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = $page_title ?? 'CarrotHome';
$page_description = $page_description ?? 'Download apps and games';
$style_version = file_exists(__DIR__ . '/../styles.css') ? filemtime(__DIR__ . '/../styles.css') : time();
$header_search = trim($_GET['q'] ?? '');
$header_countries = [];
$current_key_lang = trim((string)($_SESSION['key_lang'] ?? ''));
$current_country = null;

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $country_stmt = $pdo->query("SELECT id, icon, name, lang_key, lang_country FROM country ORDER BY name ASC");
        $header_countries = $country_stmt->fetchAll();
    } catch (Throwable $e) {
        $header_countries = [];
    }
}

foreach ($header_countries as $country) {
    if ($current_key_lang !== '' && $country['lang_key'] === $current_key_lang) {
        $current_country = $country;
        break;
    }
}

if (!$current_country && count($header_countries) > 0) {
    $current_country = $header_countries[0];
}

$current_language_label = $current_country['lang_country'] ?? ($current_key_lang ?: 'Language');
?>
<!doctype html>
<html lang="<?= h($current_key_lang ?: 'vi') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($page_title) ?></title>
<meta name="description" content="<?= h($page_description) ?>">
<meta name="theme-color" content="#ff5900">
<link rel="apple-touch-icon" sizes="180x180" href="favicon/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="favicon/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="favicon/favicon-16x16.png">
<link rel="icon" href="favicon/favicon.ico" sizes="any">
<link rel="manifest" href="favicon/site.webmanifest">
<link rel="preload" href="fonts/mona-sans.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="styles.css?v=<?= $style_version ?>">
</head>
<body>
<header class="site-nav">
  <div class="site-nav__wrapper">
    <a class="site-nav__logo" href="index.php" aria-label="<?= h(ui_label('aria.back_home', 'Back to home page')) ?>">
      <img class="brand-logo-img" src="images/carrot_28.png" alt="CarrotHome">
    </a>
    <form class="header-search" method="get" action="index.php" aria-label="<?= h(ui_label('aria.search_apps_games', 'Search apps and games')) ?>">
      <input name="q" type="search" placeholder="<?= h(ui_label('search.placeholder', 'Search apps and games')) ?>" value="<?= h($header_search) ?>">
      <button class="header-search__button" type="submit" aria-label="<?= h(ui_label('action.search', 'Search')) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.3-4.3M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </button>
    </form>
    <nav class="site-nav-main" aria-label="<?= h(ui_label('aria.primary_navigation', 'Primary navigation')) ?>">
      <a href="index.php"><?= h(ui_label('nav.explore', 'Explore')) ?></a>
      <a href="index.php?type=app"><?= h(ui_label('nav.applications', 'Applications')) ?></a>
      <a href="index.php?type=game"><?= h(ui_label('nav.games', 'Games')) ?></a>
      <?php if (count($header_countries) > 0): ?>
        <div class="language-menu">
          <button class="language-menu__trigger" type="button" aria-expanded="false" aria-haspopup="true">
            <span class="language-menu__icon"><?= country_icon_html($current_country['icon'] ?? '') ?></span>
            <span><?= h(strtoupper($current_language_label)) ?></span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <div class="language-menu__panel" role="menu">
            <?php foreach ($header_countries as $country): ?>
              <?php $is_active = $current_key_lang !== '' && $country['lang_key'] === $current_key_lang; ?>
              <button
                class="language-menu__item<?= $is_active ? ' is-active' : '' ?>"
                type="button"
                role="menuitem"
                data-lang-key="<?= h($country['lang_key']) ?>"
              >
                <span class="language-menu__flag"><?= country_icon_html($country['icon'] ?? '') ?></span>
                <span class="language-menu__name"><?= h($country['name']) ?></span>
                <span class="language-menu__code"><?= h(strtoupper($country['lang_country'])) ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </nav>
  </div>
</header>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var menu = document.querySelector('.language-menu');
  if (!menu) return;

  var trigger = menu.querySelector('.language-menu__trigger');
  var items = menu.querySelectorAll('.language-menu__item');

  trigger.addEventListener('click', function () {
    var isOpen = menu.classList.toggle('is-open');
    trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  document.addEventListener('click', function (event) {
    if (menu.contains(event.target)) return;
    menu.classList.remove('is-open');
    trigger.setAttribute('aria-expanded', 'false');
  });

  items.forEach(function (item) {
    item.addEventListener('click', function () {
      var langKey = item.getAttribute('data-lang-key');
      if (!langKey) return;

      item.disabled = true;
      fetch('<?= h(base_url('language.php')) ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: 'lang_key=' + encodeURIComponent(langKey)
      })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (!data || !data.success) {
            throw new Error(data && data.message ? data.message : 'Cannot change language');
          }
          window.location.reload();
        })
        .catch(function () {
          item.disabled = false;
        });
    });
  });
});
</script>
<main class="container page-shell">
