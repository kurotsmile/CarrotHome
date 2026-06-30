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
$header_user = null;

initialize_language_from_ip($pdo ?? null);

$current_key_lang = trim((string)($_SESSION['key_lang'] ?? ''));
$current_country_id = (int)($_SESSION['country_id'] ?? 0);

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $country_stmt = $pdo->query("SELECT id, icon, name, lang_key, lang_country FROM country ORDER BY name ASC");
        $header_countries = $country_stmt->fetchAll();
    } catch (Throwable $e) {
        $header_countries = [];
    }

    if (!empty($_SESSION['home_user_id'])) {
        try {
            $user_stmt = $pdo->prepare('SELECT id, name, email, avatar FROM users WHERE id = ? LIMIT 1');
            $user_stmt->execute([(int)$_SESSION['home_user_id']]);
            $header_user = $user_stmt->fetch() ?: null;
            if ($header_user) {
                $_SESSION['home_user_name'] = (string)($header_user['name'] ?? '');
                $_SESSION['home_user_email'] = (string)($header_user['email'] ?? '');
                $_SESSION['home_user_avatar'] = (string)($header_user['avatar'] ?? '');
            }
        } catch (Throwable $e) {
            $header_user = null;
        }
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

if (!$current_country) {
    foreach ($header_countries as $country) {
        if ($country['lang_key'] === 'en') {
            $current_country = $country;
            break;
        }
    }
}

if (!$current_country && count($header_countries) > 0) {
    $current_country = $header_countries[0];
}

$current_key_lang = $current_country['lang_key'] ?? ($current_key_lang ?: 'en');
?>
<!doctype html>
<html lang="<?= h($current_key_lang ?: 'en') ?>">
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
<?= $extra_head ?? '' ?>
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
      <a href="category.php"><?= h(ui_label('nav.category', 'Category')) ?></a>
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
                data-name="<?= h($country['name']) ?>"
                data-lang-country="<?= h($country['lang_country']) ?>"
                <?= $is_active ? 'selected' : '' ?>
              >
                <?= h($country['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <?php if ($header_user): ?>
        <?php
          $header_user_name = trim((string)($header_user['name'] ?? '')) ?: (string)($header_user['email'] ?? '');
          $header_user_avatar = trim((string)($header_user['avatar'] ?? ''));
          $header_user_initial = strtoupper(substr($header_user_name, 0, 1) ?: 'U');
        ?>
        <a class="profile-button" href="profile.php" title="<?= h($header_user_name) ?>" aria-label="<?= h(ui_label('nav.profile', 'Profile')) ?>">
          <?php if ($header_user_avatar !== ''): ?>
            <img src="<?= h($header_user_avatar) ?>" alt="">
          <?php else: ?>
            <span><?= h($header_user_initial) ?></span>
          <?php endif; ?>
        </a>
      <?php else: ?>
        <a class="login-button" href="login.php"><?= h(ui_label('nav.login', 'Login')) ?></a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var authHash = new URLSearchParams((window.location.hash || '').replace(/^#/, ''));
  var accessToken = authHash.get('access_token');
  if (accessToken) {
    fetch('<?= h(base_url('oauth-callback.php')) ?>', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      credentials: 'same-origin',
      body: 'action=supabase_token&access_token=' + encodeURIComponent(accessToken)
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (!data || !data.success) {
          throw new Error(data && data.message ? data.message : 'OAuth failed');
        }
        history.replaceState(null, document.title, window.location.pathname + window.location.search);
        window.location.reload();
      })
      .catch(function (error) {
        history.replaceState(null, document.title, window.location.pathname + window.location.search);
        window.location.href = '<?= h(base_url('login.php')) ?>?mode=register&oauth_error=' + encodeURIComponent(error.message || 'OAuth failed');
      });
    return;
  }

  var select = document.querySelector('.language-menu__select');
  if (!select) return;
  var languageChanging = false;

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
    var name = option ? (option.getAttribute('data-name') || item.text) : item.text;
    return jQuery(
      '<span class="language-select2-option">' +
        '<span class="language-menu__flag">' + iconMarkup(icon) + '</span>' +
        '<span class="language-menu__name">' + escapeHtml(name) + '</span>' +
        '<span class="language-menu__code">' + escapeHtml(code.toUpperCase()) + '</span>' +
      '</span>'
    );
  }

  function languageMatcher(params, data) {
    var term = String(params.term || '').trim().toLowerCase();
    if (term === '') return data;
    if (!data.element) return null;

    var name = data.element.getAttribute('data-name') || data.text || '';
    var code = data.element.getAttribute('data-lang-country') || '';
    var key = data.element.getAttribute('data-lang-key') || '';
    var haystack = [name, code, key].join(' ').toLowerCase();
    return haystack.indexOf(term) !== -1 ? data : null;
  }

  if (window.jQuery && jQuery.fn.select2) {
    jQuery(select).select2({
      width: 'style',
      dropdownParent: jQuery('.language-menu'),
      dropdownCssClass: 'language-select2-dropdown',
      templateResult: languageTemplate,
      templateSelection: languageTemplate,
      matcher: languageMatcher,
      escapeMarkup: function (markup) { return markup; }
    });

    jQuery(select).on('select2:select', function (event) {
      var option = event.params && event.params.data ? event.params.data.element : null;
      changeLanguage(option || select.options[select.selectedIndex]);
    });
  }

  function changeLanguage(selected) {
    if (languageChanging || !selected) return;
    var langKey = selected.getAttribute('data-lang-key') || '';
    var countryId = selected.value || '';
    if (!langKey) return;

    languageChanging = true;
    select.disabled = true;
    if (window.jQuery && jQuery.fn.select2) {
      jQuery(select).prop('disabled', true);
    }

    fetch('<?= h(base_url('language.php')) ?>', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      credentials: 'same-origin',
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
        languageChanging = false;
        select.disabled = false;
        if (window.jQuery && jQuery.fn.select2) {
          jQuery(select).prop('disabled', false);
        }
      });
  }

  select.addEventListener('change', function () {
    changeLanguage(select.options[select.selectedIndex]);
  });
});
</script>
<main class="container page-shell">
