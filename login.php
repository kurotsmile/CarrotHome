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

if (!empty($_GET['oauth_error'])) {
    $error_message = trim((string)$_GET['oauth_error']);
    $mode = 'register';
}

if (isset($_GET['logout'])) {
    unset($_SESSION['home_user_id'], $_SESSION['home_user_name'], $_SESSION['home_user_email'], $_SESSION['home_user_role']);
    header('Location: login.php');
    exit;
}

if ($social_provider !== '') {
    header('Location: social-login.php?provider=' . rawurlencode($social_provider));
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
                password_hash($password, PASSWORD_DEFAULT),
                'user',
                'private',
                'normal',
            ]);

            session_regenerate_id(true);
            $_SESSION['home_user_id'] = (int)$pdo->lastInsertId();
            $_SESSION['home_user_name'] = $name;
            $_SESSION['home_user_email'] = $email;
            $_SESSION['home_user_role'] = 'user';
            $success_message = ui_label('register.success', 'Đăng ký thành công.');
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
                $info = password_get_info($stored_password);
                $password_ok = $info['algo'] !== 0 ? password_verify($password, $stored_password) : hash_equals($stored_password, $password);
            }

            if (!$user || !$password_ok) {
                throw new RuntimeException(ui_label('login.error_invalid', 'Sai email hoặc mật khẩu.'));
            }

            session_regenerate_id(true);
            $_SESSION['home_user_id'] = (int)$user['id'];
            $_SESSION['home_user_name'] = (string)($user['name'] ?? '');
            $_SESSION['home_user_email'] = (string)($user['email'] ?? '');
            $_SESSION['home_user_role'] = (string)($user['role'] ?? 'user');
            $success_message = ui_label('login.success', 'Đăng nhập thành công.');
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
<title><?= h(ui_label('login.title', 'Login')) ?> - CarrotHome</title>
<meta name="theme-color" content="#ff5900">
<link rel="apple-touch-icon" sizes="180x180" href="favicon/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="favicon/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="favicon/favicon-16x16.png">
<link rel="icon" href="favicon/favicon.ico" sizes="any">
<link rel="manifest" href="favicon/site.webmanifest">
<link rel="stylesheet" href="styles.css?v=<?= $style_version ?>">
</head>
<body>
<main class="login-page">
  <section class="login-panel" aria-label="<?= h(ui_label('login.title', 'Login')) ?>">
    <a class="login-logo" href="index.php" aria-label="<?= h(ui_label('aria.back_home', 'Back to home page')) ?>">
      <img src="images/carrot_28.png" alt="CarrotHome">
    </a>

    <?php if (!empty($_SESSION['home_user_id'])): ?>
      <div class="login-state">
        <h1><?= h(ui_label('login.logged_in_title', 'You are logged in')) ?></h1>
        <p><?= h($_SESSION['home_user_name'] ?: $_SESSION['home_user_email']) ?></p>
        <div class="login-actions">
          <a class="login-submit" href="index.php"><?= h(ui_label('action.back_home', 'Back home')) ?></a>
          <a class="login-secondary" href="login.php?logout=1"><?= h(ui_label('action.logout', 'Logout')) ?></a>
        </div>
      </div>
    <?php else: ?>
      <div class="login-tabs" role="tablist" aria-label="<?= h(ui_label('login.tabs', 'Account access')) ?>">
        <a class="<?= $mode === 'login' ? 'is-active' : '' ?>" href="login.php"><?= h(ui_label('action.login', 'Login')) ?></a>
        <a class="<?= $mode === 'register' ? 'is-active' : '' ?>" href="login.php?mode=register"><?= h(ui_label('action.register', 'Register')) ?></a>
      </div>

      <form class="login-form <?= $mode === 'register' ? 'is-register-mode' : '' ?>" method="post">
        <input type="hidden" name="mode" value="<?= h($mode) ?>">
        <h1><?= h($mode === 'register' ? ui_label('register.heading', 'Register') : ui_label('login.heading', 'Login')) ?></h1>
        <p><?= h($mode === 'register' ? ui_label('register.intro', 'Create your CarrotHome account.') : ui_label('login.intro', 'Sign in to your CarrotHome account.')) ?></p>

        <?php if ($error_message): ?>
          <div class="login-alert login-alert--error"><?= h($error_message) ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
          <div class="login-alert login-alert--success"><?= h($success_message) ?></div>
        <?php endif; ?>

        <div class="social-auth-grid">
          <a class="social-auth-button" href="social-login.php?provider=google">Google</a>
          <a class="social-auth-button" href="social-login.php?provider=twitter_x">X</a>
          <a class="social-auth-button" href="social-login.php?provider=github">GitHub</a>
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

        <button class="login-submit" type="submit"><?= h($mode === 'register' ? ui_label('action.register', 'Register') : ui_label('action.login', 'Login')) ?></button>
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
