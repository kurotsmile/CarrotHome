<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

initialize_language_from_ip($pdo ?? null);

$error_message = '';
$success_message = '';
$mode = ($_POST['mode'] ?? $_GET['mode'] ?? 'login') === 'register' ? 'register' : 'login';
$social_provider = trim((string)($_GET['auth'] ?? ''));
$email = trim($_POST['email'] ?? '');
$name = trim($_POST['name'] ?? '');
$redirect_target = carrot_login_redirect_target('index.php');
$_SESSION['home_login_redirect'] = $redirect_target;

if (!empty($_GET['oauth_error'])) {
    $error_message = trim((string)$_GET['oauth_error']);
    $mode = 'register';
}

if (isset($_GET['logout'])) {
    unset($_SESSION['home_user_id'], $_SESSION['home_user_name'], $_SESSION['home_user_email'], $_SESSION['home_user_role'], $_SESSION['home_user_avatar']);
    header('Location: login.php?' . http_build_query(['redirect' => $redirect_target]));
    exit;
}

if ($social_provider !== '') {
    header('Location: social-login.php?' . http_build_query(['provider' => $social_provider, 'redirect' => $redirect_target]));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $password_confirm = (string)($_POST['password_confirm'] ?? '');

    if (!$pdo instanceof PDO) {
        $error_message = $db_error ?? ui_label('error.mysql_connection', 'Lỗi kết nối MySQL.');
    } elseif ($mode === 'register') {
        try {
            if ($name === '' || $email === '' || $password === '') {
                throw new RuntimeException(ui_label('register.error_required', 'Vui lòng nhập tên, email và mật khẩu.'));
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException(ui_label('login.error_email', 'Email không hợp lệ.'));
            }
            if (strlen($password) < 6) {
                throw new RuntimeException(ui_label('register.error_password', 'Mật khẩu cần ít nhất 6 ký tự.'));
            }
            if ($password !== $password_confirm) {
                throw new RuntimeException(ui_label('register.error_confirm', 'Mật khẩu xác nhận không khớp.'));
            }

            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new RuntimeException(ui_label('register.error_exists', 'Email này đã được đăng ký.'));
            }

            $stmt = $pdo->prepare('
                INSERT INTO users (created_at, email, lang, name, password, role, status_share, type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                date('Y-m-d H:i:s'),
                $email,
                current_lang_key(),
                $name,
                $password,
                'user',
                'private',
                'normal',
            ]);

            session_regenerate_id(true);
                $_SESSION['home_user_id'] = (int)$pdo->lastInsertId();
                $_SESSION['home_user_name'] = $name;
                $_SESSION['home_user_email'] = $email;
                $_SESSION['home_user_role'] = 'user';
                $_SESSION['home_user_avatar'] = '';
                unset($_SESSION['home_login_redirect']);
                header('Location: ' . $redirect_target);
                exit;
        } catch (Throwable $e) {
            $error_message = $e->getMessage();
        }
    } else {
        try {
            if ($email === '' || $password === '') {
                throw new RuntimeException(ui_label('login.error_required', 'Vui lòng nhập email và mật khẩu.'));
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException(ui_label('login.error_email', 'Email không hợp lệ.'));
            }

            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            $stored_password = (string)($user['password'] ?? '');
            $password_ok = false;

            if ($user && $stored_password !== '') {
                $password_ok = hash_equals($stored_password, $password);
            }

            if (!$user || !$password_ok) {
                throw new RuntimeException(ui_label('login.error_invalid', 'Sai email hoặc mật khẩu.'));
            }

            session_regenerate_id(true);
            $_SESSION['home_user_id'] = (int)$user['id'];
            $_SESSION['home_user_name'] = (string)($user['name'] ?? '');
            $_SESSION['home_user_email'] = (string)($user['email'] ?? '');
            $_SESSION['home_user_role'] = (string)($user['role'] ?? 'user');
            $_SESSION['home_user_avatar'] = (string)($user['avatar'] ?? '');
            unset($_SESSION['home_login_redirect']);
            header('Location: ' . $redirect_target);
            exit;
        } catch (Throwable $e) {
            $error_message = $e->getMessage();
        }
    }
}

$style_version = file_exists(__DIR__ . '/styles.css') ? filemtime(__DIR__ . '/styles.css') : time();
$current_lang = h(current_lang_key());
$register_form_open = $mode === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST';
?>
<!doctype html>
<html lang="<?= $current_lang ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(ui_label('login.title', 'Login')) ?> - Carrot28</title>
<meta name="theme-color" content="#ff5900">
<link rel="apple-touch-icon" sizes="180x180" href="<?= h(base_url('favicon/apple-touch-icon.png')) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= h(base_url('favicon/favicon-32x32.png')) ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= h(base_url('favicon/favicon-16x16.png')) ?>">
<link rel="icon" href="<?= h(base_url('favicon/favicon.ico')) ?>" sizes="any">
<link rel="manifest" href="<?= h(base_url('favicon/site.webmanifest')) ?>">
<link rel="stylesheet" href="<?= h(base_url('styles.css')) ?>?v=<?= $style_version ?>">
</head>
<body>
<main class="login-page">
  <section class="login-panel" aria-label="<?= h(ui_label('login.title', 'Login')) ?>">
    <a class="login-logo" href="<?= h(base_url('index.php')) ?>" aria-label="<?= h(ui_label('aria.back_home', 'Back to home page')) ?>">
      <img src="<?= h(base_url('images/carrot_28.png')) ?>" alt="Carrot28">
    </a>

    <?php if (!empty($_SESSION['home_user_id'])): ?>
      <div class="login-state">
        <h1><?= h(ui_label('login.logged_in_title', 'You are logged in')) ?></h1>
        <p><?= h($_SESSION['home_user_name'] ?: $_SESSION['home_user_email']) ?></p>
        <div class="login-actions">
          <a class="login-submit" href="<?= h($redirect_target) ?>"><?= h(ui_label('action.back_home', 'Back home')) ?></a>
          <a class="login-secondary" href="<?= h('login.php?' . http_build_query(['logout' => 1, 'redirect' => $redirect_target])) ?>"><?= h(ui_label('action.logout', 'Logout')) ?></a>
        </div>
      </div>
    <?php else: ?>
      <div class="login-tabs" role="tablist" aria-label="<?= h(ui_label('login.tabs', 'Account access')) ?>">
        <a class="<?= $mode === 'login' ? 'is-active' : '' ?>" href="<?= h('login.php?' . http_build_query(['redirect' => $redirect_target])) ?>"><?= h(ui_label('nav.login', 'Login')) ?></a>
        <a class="<?= $mode === 'register' ? 'is-active' : '' ?>" href="<?= h('login.php?' . http_build_query(['mode' => 'register', 'redirect' => $redirect_target])) ?>"><?= h(ui_label('nav.register', 'Register')) ?></a>
      </div>

      <form class="login-form <?= $mode === 'register' ? 'is-register-mode' : '' ?>" method="post">
        <input type="hidden" name="mode" value="<?= h($mode) ?>">
        <input type="hidden" name="redirect" value="<?= h($redirect_target) ?>">
        <h1><?= h($mode === 'register' ? ui_label('nav.register', 'Register') : ui_label('nav.login', 'Login')) ?></h1>
        <p><?= h($mode === 'register' ? ui_label('register.intro', 'Create your Carrot28 account.') : ui_label('login.intro', 'Sign in to your Carrot28 account.')) ?></p>

        <?php if ($error_message): ?>
          <div class="login-alert login-alert--error"><?= h($error_message) ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
          <div class="login-alert login-alert--success"><?= h($success_message) ?></div>
        <?php endif; ?>

        <div class="social-auth-grid">
          <a class="social-auth-button" href="<?= h('social-login.php?' . http_build_query(['provider' => 'google', 'redirect' => $redirect_target])) ?>" aria-label="Google">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285f4" d="M21.6 12.2c0-.7-.1-1.3-.2-1.8H12v3.5h5.4c-.2 1.1-.9 2.1-1.9 2.7v2.2h3c1.8-1.6 3.1-3.9 3.1-6.6Z"/><path fill="#34a853" d="M12 22c2.7 0 5-0.9 6.6-2.5l-3-2.2c-.8.5-1.9.9-3.6.9-2.6 0-4.8-1.7-5.6-4.1H3.3v2.3C4.9 19.7 8.2 22 12 22Z"/><path fill="#fbbc05" d="M6.4 14.1c-.2-.6-.3-1.3-.3-2.1s.1-1.4.3-2.1V7.6H3.3C2.5 8.9 2.1 10.4 2.1 12s.4 3.1 1.2 4.4l3.1-2.3Z"/><path fill="#ea4335" d="M12 5.8c1.5 0 2.8.5 3.8 1.5l2.8-2.8C17 2.9 14.7 2 12 2 8.2 2 4.9 4.3 3.3 7.6l3.1 2.3c.8-2.4 3-4.1 5.6-4.1Z"/></svg>
            <span>Google</span>
          </a>
          <a class="social-auth-button" href="<?= h('social-login.php?' . http_build_query(['provider' => 'twitter_x', 'redirect' => $redirect_target])) ?>" aria-label="X">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M18.3 2.8h3.3l-7.2 8.2 8.5 10.2h-6.6l-5.2-6.2-6 6.2H1.8l7.7-8.7L1.4 2.8h6.8l4.7 5.7 5.4-5.7Zm-1.2 16.6h1.8L7.2 4.5H5.3l11.8 14.9Z"/></svg>
            <span>X</span>
          </a>
          <a class="social-auth-button" href="<?= h('social-login.php?' . http_build_query(['provider' => 'github', 'redirect' => $redirect_target])) ?>" aria-label="GitHub">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-3.2 19.5c.5.1.7-.2.7-.5v-1.8c-2.9.6-3.5-1.2-3.5-1.2-.5-1.1-1.1-1.4-1.1-1.4-.9-.6.1-.6.1-.6 1 0 1.5 1 1.5 1 .9 1.5 2.3 1.1 2.9.8.1-.6.3-1.1.6-1.3-2.3-.3-4.7-1.1-4.7-5A3.9 3.9 0 0 1 6.4 8.7c-.1-.3-.5-1.3.1-2.7 0 0 .8-.3 2.8 1a9.6 9.6 0 0 1 5.2 0c2-1.3 2.8-1 2.8-1 .6 1.4.2 2.4.1 2.7a3.9 3.9 0 0 1 1.1 2.8c0 3.9-2.4 4.7-4.7 5 .4.3.7.9.7 1.8V21c0 .3.2.6.7.5A10 10 0 0 0 12 2Z"/></svg>
            <span>GitHub</span>
          </a>
        </div>

        <?php if ($mode === 'register'): ?>
          <button class="login-secondary js-register-form-toggle" type="button"><?= h(ui_label('register.with_form', 'Đăng ký bằng form')) ?></button>
          <div class="register-form-fields <?= $register_form_open ? 'is-open' : '' ?>">
            <label for="register_name"><?= h(ui_label('label.name', 'Name')) ?></label>
            <input id="register_name" name="name" value="<?= h($name) ?>" autocomplete="name" <?= $register_form_open ? '' : 'disabled' ?>>
        <?php endif; ?>

        <label for="login_email"><?= h(ui_label('label.email', 'Email')) ?></label>
        <input id="login_email" name="email" type="email" value="<?= h($email) ?>" autocomplete="email" required autofocus <?= $mode === 'register' && !$register_form_open ? 'disabled' : '' ?>>

        <label for="login_password"><?= h(ui_label('label.password', 'Password')) ?></label>
        <input id="login_password" name="password" type="password" autocomplete="<?= $mode === 'register' ? 'new-password' : 'current-password' ?>" required <?= $mode === 'register' && !$register_form_open ? 'disabled' : '' ?>>

        <?php if ($mode === 'register'): ?>
            <label for="register_password_confirm"><?= h(ui_label('label.password_confirm', 'Confirm password')) ?></label>
            <input id="register_password_confirm" name="password_confirm" type="password" autocomplete="new-password" required <?= $register_form_open ? '' : 'disabled' ?>>
          </div>
        <?php endif; ?>

        <button class="login-submit" type="submit"><?= h($mode === 'register' ? ui_label('nav.register', 'Register') : ui_label('nav.login', 'Login')) ?></button>
      </form>
    <?php endif; ?>
  </section>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.querySelector('.js-register-form-toggle');
  var fields = document.querySelector('.register-form-fields');
  if (!toggle || !fields) return;
  toggle.addEventListener('click', function () {
    fields.classList.toggle('is-open');
    fields.querySelectorAll('input, select, textarea').forEach(function (input) {
      input.disabled = !fields.classList.contains('is-open');
    });
  });
});
</script>
</body>
</html>
