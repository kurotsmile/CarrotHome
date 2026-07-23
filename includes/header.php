<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = $page_title ?? 'Carrot28';
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
<?= carrot_google_search_verification_meta($pdo ?? null, 'CarrotHome') ?>
<meta name="theme-color" content="#ff5900">
<link rel="apple-touch-icon" sizes="180x180" href="<?= h(base_url('favicon/apple-touch-icon.png')) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= h(base_url('favicon/favicon-32x32.png')) ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= h(base_url('favicon/favicon-16x16.png')) ?>">
<link rel="icon" href="<?= h(base_url('favicon/favicon.ico')) ?>" sizes="any">
<link rel="manifest" href="<?= h(base_url('favicon/site.webmanifest')) ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?= $extra_head ?? '' ?>
<link rel="stylesheet" href="<?= h(base_url('styles.css')) ?>?v=<?= $style_version ?>">
</head>
<body>
<header class="site-nav">
  <div class="site-nav__wrapper">
    <a class="site-nav__logo" href="<?= h(base_url('index.php')) ?>" aria-label="<?= h(ui_label('aria.back_home', 'Back to home page')) ?>">
      <img class="brand-logo-img" src="<?= h(base_url('images/carrot_28.png')) ?>" alt="Carrot28">
    </a>
    <form class="header-search" method="get" action="<?= h(base_url('index.php')) ?>" aria-label="<?= h(ui_label('aria.search_apps_games', 'Search apps and games')) ?>">
      <input name="q" type="search" placeholder="<?= h(ui_label('search.placeholder', 'Search apps and games')) ?>" value="<?= h($header_search) ?>">
      <button class="header-search__button" type="submit" aria-label="<?= h(ui_label('action.search', 'Search')) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.3-4.3M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </button>
    </form>
    <nav class="site-nav-main" aria-label="<?= h(ui_label('aria.primary_navigation', 'Primary navigation')) ?>">
      <a href="<?= h(category_url()) ?>"><?= h(ui_label('nav.category', 'Category')) ?></a>
      <a href="<?= h(base_url('index.php')) ?>?type=app"><?= h(ui_label('nav.applications', 'Applications')) ?></a>
      <a href="<?= h(base_url('index.php')) ?>?type=game"><?= h(ui_label('nav.game', 'Games')) ?></a>
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
        <div class="profile-menu">
          <a class="profile-button" href="profile.php" title="<?= h($header_user_name) ?>" aria-label="<?= h(ui_label('nav.profile', 'Profile')) ?>">
            <?php if ($header_user_avatar !== ''): ?>
              <img src="<?= h($header_user_avatar) ?>" alt="">
            <?php else: ?>
              <span><?= h($header_user_initial) ?></span>
            <?php endif; ?>
          </a>
          <div class="profile-menu__dropdown">
            <a href="profile.php"><?= h(ui_label('nav.edit_profile', 'Edit Profile')) ?></a>
            <a href="order.php"><?= h(ui_label('nav.order', 'Order')) ?></a>
            <a href="login.php?logout=1"><?= h(ui_label('action.logout', 'Logout')) ?></a>
          </div>
        </div>
      <?php else: ?>
        <button class="login-button js-home-login" type="button"><?= h(ui_label('nav.login', 'Login')) ?></button>
      <?php endif; ?>
    </nav>
  </div>
</header>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  function homeLoginUrl(mode) {
    var redirect = window.location.href;
    var query = mode === 'register'
      ? {mode: 'register', redirect: redirect}
      : {redirect: redirect};
    return '<?= h(base_url('login.php')) ?>?' + new URLSearchParams(query).toString();
  }

  function homeSocialUrl(provider) {
    return '<?= h(base_url('social-login.php')) ?>?' + new URLSearchParams({
      provider: provider,
      redirect: window.location.href
    }).toString();
  }

  function homeLoginPanel(mode) {
    var isRegister = mode === 'register';
    return '' +
      '<section class="login-panel home-login-panel" aria-label="<?= h(ui_label('login.title', 'Login')) ?>">' +
        '<a class="login-logo" href="<?= h(base_url('index.php')) ?>" aria-label="<?= h(ui_label('aria.back_home', 'Back to home page')) ?>">' +
          '<img src="<?= h(base_url('images/carrot_28.png')) ?>" alt="Carrot28">' +
        '</a>' +
        '<div class="login-tabs" role="tablist" aria-label="<?= h(ui_label('login.tabs', 'Account access')) ?>">' +
          '<button class="' + (!isRegister ? 'is-active' : '') + '" type="button" data-home-login-mode="login"><?= h(ui_label('nav.login', 'Login')) ?></button>' +
          '<button class="' + (isRegister ? 'is-active' : '') + '" type="button" data-home-login-mode="register"><?= h(ui_label('nav.register', 'Register')) ?></button>' +
        '</div>' +
        '<form class="login-form ' + (isRegister ? 'is-register-mode' : '') + '" method="post" action="<?= h(base_url('login.php')) ?>">' +
          '<input type="hidden" name="mode" value="' + (isRegister ? 'register' : 'login') + '">' +
          '<input type="hidden" name="redirect" value="' + homeEscapeHtml(window.location.href) + '">' +
          '<h1>' + (isRegister ? '<?= h(ui_label('nav.register', 'Register')) ?>' : '<?= h(ui_label('nav.login', 'Login')) ?>') + '</h1>' +
          '<p>' + (isRegister ? '<?= h(ui_label('register.intro', 'Create your Carrot28 account.')) ?>' : '<?= h(ui_label('login.intro', 'Sign in to your Carrot28 account.')) ?>') + '</p>' +
          '<div class="social-auth-grid">' +
            '<a class="social-auth-button" href="' + homeSocialUrl('google') + '" aria-label="Google"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285f4" d="M21.6 12.2c0-.7-.1-1.3-.2-1.8H12v3.5h5.4c-.2 1.1-.9 2.1-1.9 2.7v2.2h3c1.8-1.6 3.1-3.9 3.1-6.6Z"/><path fill="#34a853" d="M12 22c2.7 0 5-0.9 6.6-2.5l-3-2.2c-.8.5-1.9.9-3.6.9-2.6 0-4.8-1.7-5.6-4.1H3.3v2.3C4.9 19.7 8.2 22 12 22Z"/><path fill="#fbbc05" d="M6.4 14.1c-.2-.6-.3-1.3-.3-2.1s.1-1.4.3-2.1V7.6H3.3C2.5 8.9 2.1 10.4 2.1 12s.4 3.1 1.2 4.4l3.1-2.3Z"/><path fill="#ea4335" d="M12 5.8c1.5 0 2.8.5 3.8 1.5l2.8-2.8C17 2.9 14.7 2 12 2 8.2 2 4.9 4.3 3.3 7.6l3.1 2.3c.8-2.4 3-4.1 5.6-4.1Z"/></svg><span>Google</span></a>' +
            '<a class="social-auth-button" href="' + homeSocialUrl('twitter_x') + '" aria-label="X"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M18.3 2.8h3.3l-7.2 8.2 8.5 10.2h-6.6l-5.2-6.2-6 6.2H1.8l7.7-8.7L1.4 2.8h6.8l4.7 5.7 5.4-5.7Zm-1.2 16.6h1.8L7.2 4.5H5.3l11.8 14.9Z"/></svg><span>X</span></a>' +
            '<a class="social-auth-button" href="' + homeSocialUrl('github') + '" aria-label="GitHub"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-3.2 19.5c.5.1.7-.2.7-.5v-1.8c-2.9.6-3.5-1.2-3.5-1.2-.5-1.1-1.1-1.4-1.1-1.4-.9-.6.1-.6.1-.6 1 0 1.5 1 1.5 1 .9 1.5 2.3 1.1 2.9.8.1-.6.3-1.1.6-1.3-2.3-.3-4.7-1.1-4.7-5A3.9 3.9 0 0 1 6.4 8.7c-.1-.3-.5-1.3.1-2.7 0 0 .8-.3 2.8 1a9.6 9.6 0 0 1 5.2 0c2-1.3 2.8-1 2.8-1 .6 1.4.2 2.4.1 2.7a3.9 3.9 0 0 1 1.1 2.8c0 3.9-2.4 4.7-4.7 5 .4.3.7.9.7 1.8V21c0 .3.2.6.7.5A10 10 0 0 0 12 2Z"/></svg><span>GitHub</span></a>' +
          '</div>' +
          (isRegister ? '<button class="login-secondary js-register-form-toggle" type="button"><?= h(ui_label('register.with_form', 'Đăng ký bằng form')) ?></button><div class="register-form-fields"><label for="popup_register_name"><?= h(ui_label('label.name', 'Name')) ?></label><input id="popup_register_name" name="name" autocomplete="name" disabled>' : '') +
          '<label for="popup_login_email"><?= h(ui_label('label.email', 'Email')) ?></label>' +
          '<input id="popup_login_email" name="email" type="email" autocomplete="email" required autofocus ' + (isRegister ? 'disabled' : '') + '>' +
          '<label for="popup_login_password"><?= h(ui_label('label.password', 'Password')) ?></label>' +
          '<input id="popup_login_password" name="password" type="password" autocomplete="' + (isRegister ? 'new-password' : 'current-password') + '" required ' + (isRegister ? 'disabled' : '') + '>' +
          (isRegister ? '<label for="popup_register_password_confirm"><?= h(ui_label('label.password_confirm', 'Confirm password')) ?></label><input id="popup_register_password_confirm" name="password_confirm" type="password" autocomplete="new-password" required disabled></div>' : '') +
          '<button class="login-submit" type="submit">' + (isRegister ? '<?= h(ui_label('nav.register', 'Register')) ?>' : '<?= h(ui_label('nav.login', 'Login')) ?>') + '</button>' +
        '</form>' +
      '</section>';
  }

  function openHomeLogin(mode) {
    if (!window.Swal) {
      window.location.href = homeLoginUrl(mode || 'login');
      return;
    }

    Swal.fire({
      html: homeLoginPanel(mode || 'login'),
      showConfirmButton: false,
      showCloseButton: true,
      customClass: {popup: 'home-login-swal'},
      background: 'transparent',
      width: 'min(100% - 28px, 470px)',
      padding: 0,
      didOpen: function (popup) {
        var firstInput = popup.querySelector('input:not([type="hidden"]):not(:disabled)');
        if (firstInput) firstInput.focus();
      }
    });
  }

  function homeEscapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
    });
  }

  document.addEventListener('click', function (event) {
    var loginButton = event.target.closest('.js-home-login');
    if (loginButton) {
      event.preventDefault();
      openHomeLogin('login');
      return;
    }

    var modeButton = event.target.closest('[data-home-login-mode]');
    if (modeButton) {
      event.preventDefault();
      openHomeLogin(modeButton.getAttribute('data-home-login-mode') || 'login');
      return;
    }

    var registerToggle = event.target.closest('.home-login-panel .js-register-form-toggle');
    if (registerToggle) {
      var fields = document.querySelector('.home-login-panel .register-form-fields');
      if (!fields) return;
      fields.classList.toggle('is-open');
      fields.querySelectorAll('input, select, textarea').forEach(function (input) {
        input.disabled = !fields.classList.contains('is-open');
      });
      var input = fields.querySelector('input:not(:disabled)');
      if (input) input.focus();
    }
  });

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
