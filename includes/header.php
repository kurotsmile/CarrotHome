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
$current_country_id = (int)($_SESSION['country_id'] ?? 0);
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
    if ($current_country_id > 0 && (int)$country['id'] === $current_country_id) {
        $current_country = $country;
        break;
    }
}

if (!$current_country) {
    foreach ($header_countries as $country) {
        if ($current_key_lang !== '' && $country['lang_key'] === $current_key_lang) {
            $current_country = $country;
            break;
        }
    }
}

if (!$current_country && count($header_countries) > 0) {
    $current_country = $header_countries[0];
}

$current_key_lang = $current_country['lang_key'] ?? ($current_key_lang ?: 'vi');
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
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
      <a href="index.php?type=game"><?= h(ui_label('nav.game', 'Games')) ?></a>
      <?php if (count($header_countries) > 0): ?>
        <div class="language-menu">
          <select class="language-menu__select" aria-label="<?= h(ui_label('aria.choose_language', 'Choose language')) ?>">
            <?php foreach ($header_countries as $country): ?>
              <?php $is_active = $current_country && (int) $country['id'] === (int) $current_country['id']; ?>
              <option
                value="<?= (int) $country['id'] ?>"
                data-lang-key="<?= h($country['lang_key']) ?>"
                data-icon="<?= h($country['icon'] ?? '') ?>"
                data-lang-country="<?= h($country['lang_country']) ?>"
                <?= $is_active ? 'selected' : '' ?>
              >
                <?= h($country['name'] . ' (' . strtoupper($country['lang_country']) . ')') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
    </nav>
  </div>
</header>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var select = document.querySelector('.language-menu__select');
  if (!select) return;

  function iconMarkup(icon) {
    icon = (icon || '').trim();
    if (!icon) return '<span class="language-menu__emoji" aria-hidden="true">🌐</span>';
    if (/^(https?:\/\/|\/|r2:)/i.test(icon)) {
      if (icon.indexOf('r2:') === 0) {
        icon = 'https://json-worker.tranthienthanh93.workers.dev/get_file?file=' + encodeURIComponent(icon.substring(3));
      }
      return '<img src="' + icon.replace(/"/g, '&quot;') + '" alt="" loading="lazy">';
    }
    return '<span class="language-menu__emoji" aria-hidden="true">' + icon.replace(/[&<>"']/g, function (char) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
    }) + '</span>';
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
    });
  }

  function languageTemplate(item) {
    if (!item.id) return item.text;
    var option = item.element;
    var code = option ? (option.getAttribute('data-lang-country') || '') : '';
    var icon = option ? (option.getAttribute('data-icon') || '') : '';
    var name = String(item.text || '').replace(/\s+\([^)]+\)$/, '');
    return jQuery(
      '<span class="language-select2-option">' +
        '<span class="language-menu__flag">' + iconMarkup(icon) + '</span>' +
        '<span class="language-menu__name">' + escapeHtml(name) + '</span>' +
        '<span class="language-menu__code">' + escapeHtml(code.toUpperCase()) + '</span>' +
      '</span>'
    );
  }

  if (window.jQuery && jQuery.fn.select2) {
    jQuery(select).select2({
      width: 'style',
      dropdownCssClass: 'language-select2-dropdown',
      dropdownAutoWidth: true,
      templateResult: languageTemplate,
      templateSelection: languageTemplate,
      escapeMarkup: function (markup) { return markup; }
    });
  }

  select.addEventListener('change', function () {
    var selected = select.options[select.selectedIndex];
    var langKey = selected ? selected.getAttribute('data-lang-key') : '';
    var countryId = select.value;
    if (!langKey) return;

    select.disabled = true;
    fetch('<?= h(base_url('language.php')) ?>', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: 'country_id=' + encodeURIComponent(countryId || '') + '&lang_key=' + encodeURIComponent(langKey)
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (!data || !data.success) {
          throw new Error(data && data.message ? data.message : 'Cannot change language');
        }
        window.location.reload();
      })
      .catch(function () {
        select.disabled = false;
      });
  });
});
</script>
<main class="container page-shell">
