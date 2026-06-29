<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

initialize_language_from_ip($pdo ?? null);

$error_message = '';
$success_message = '';
$email = trim($_POST['email'] ?? '');

if (isset($_GET['logout'])) {
    unset($_SESSION['home_user_id'], $_SESSION['home_user_name'], $_SESSION['home_user_email'], $_SESSION['home_user_role']);
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');

    if (!$pdo instanceof PDO) {
        $error_message = $db_error ?? ui_label('error.mysql_connection', 'Lỗi kết nối MySQL.');
    } elseif ($email === '' || $password === '') {
        $error_message = ui_label('login.error_required', 'Vui lòng nhập email và mật khẩu.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = ui_label('login.error_email', 'Email không hợp lệ.');
    } else {
        try {
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
                $error_message = ui_label('login.error_invalid', 'Sai email hoặc mật khẩu.');
            } else {
                session_regenerate_id(true);
                $_SESSION['home_user_id'] = (int)$user['id'];
                $_SESSION['home_user_name'] = (string)($user['name'] ?? '');
                $_SESSION['home_user_email'] = (string)($user['email'] ?? '');
                $_SESSION['home_user_role'] = (string)($user['role'] ?? 'user');
                $success_message = ui_label('login.success', 'Đăng nhập thành công.');
            }
        } catch (Throwable $e) {
            $error_message = $e->getMessage();
        }
    }
}

$style_version = file_exists(__DIR__ . '/styles.css') ? filemtime(__DIR__ . '/styles.css') : time();
$current_lang = h(current_lang_key());
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
      <form class="login-form" method="post">
        <h1><?= h(ui_label('login.heading', 'Login')) ?></h1>
        <p><?= h(ui_label('login.intro', 'Sign in to your CarrotHome account.')) ?></p>

        <?php if ($error_message): ?>
          <div class="login-alert login-alert--error"><?= h($error_message) ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
          <div class="login-alert login-alert--success"><?= h($success_message) ?></div>
        <?php endif; ?>

        <label for="login_email"><?= h(ui_label('label.email', 'Email')) ?></label>
        <input id="login_email" name="email" type="email" value="<?= h($email) ?>" autocomplete="email" required autofocus>

        <label for="login_password"><?= h(ui_label('label.password', 'Password')) ?></label>
        <input id="login_password" name="password" type="password" autocomplete="current-password" required>

        <button class="login-submit" type="submit"><?= h(ui_label('action.login', 'Login')) ?></button>
      </form>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
