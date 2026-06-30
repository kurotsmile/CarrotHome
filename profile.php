<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

initialize_language_from_ip($pdo ?? null);

if (empty($_SESSION['home_user_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$error_message = '';
$user = null;

if (!$pdo instanceof PDO) {
    $error_message = $db_error ?? ui_label('error.mysql_connection', 'Lỗi kết nối MySQL.');
} else {
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim((string)($_POST['name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $birthday = trim((string)($_POST['birthday'] ?? ''));
            $avatar = trim((string)($_POST['avatar'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $lang = trim((string)($_POST['lang'] ?? current_lang_key()));
            $sex = trim((string)($_POST['sex'] ?? ''));

            if ($name === '') {
                throw new RuntimeException(ui_label('profile.error_name', 'Vui lòng nhập tên.'));
            }

            $stmt = $pdo->prepare('
                UPDATE users
                SET name = ?, phone = ?, birthday = ?, avatar = ?, address = ?, lang = ?, sex = ?
                WHERE id = ?
            ');
            $stmt->execute([$name, $phone, $birthday, $avatar, $address, $lang, $sex, (int)$_SESSION['home_user_id']]);
            $_SESSION['home_user_name'] = $name;
            $_SESSION['home_user_avatar'] = $avatar;
            $message = ui_label('profile.saved', 'Đã cập nhật thông tin.');
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$_SESSION['home_user_id']]);
        $user = $stmt->fetch();
        if (!$user) {
            unset($_SESSION['home_user_id'], $_SESSION['home_user_name'], $_SESSION['home_user_email'], $_SESSION['home_user_role'], $_SESSION['home_user_avatar']);
            header('Location: login.php');
            exit;
        }
    } catch (Throwable $e) {
        $error_message = $e->getMessage();
    }
}

$page_title = ui_label('profile.title', 'Profile') . ' - CarrotHome';
$page_description = ui_label('profile.description', 'View and update your CarrotHome profile.');
include __DIR__ . '/includes/header.php';
?>

<section class="profile-page">
  <div class="profile-header">
    <div class="profile-avatar-large">
      <?php if (!empty($user['avatar'])): ?>
        <img src="<?= h($user['avatar']) ?>" alt="">
      <?php else: ?>
        <span><?= h(strtoupper(substr(trim((string)($user['name'] ?? 'U')), 0, 1) ?: 'U')) ?></span>
      <?php endif; ?>
    </div>
    <div>
      <p class="eyebrow"><?= h(ui_label('profile.eyebrow', 'Account')) ?></p>
      <h2><?= h($user['name'] ?? '') ?></h2>
      <p><?= h($user['email'] ?? '') ?></p>
    </div>
  </div>

  <div class="profile-tabs">
    <a class="is-active" href="profile.php"><?= h(ui_label('profile.tab_information', 'Information')) ?></a>
    <a href="order.php"><?= h(ui_label('profile.tab_order', 'Order')) ?></a>
  </div>

  <?php if ($message): ?><div class="login-alert login-alert--success"><?= h($message) ?></div><?php endif; ?>
  <?php if ($error_message): ?><div class="login-alert login-alert--error"><?= h($error_message) ?></div><?php endif; ?>

  <form class="profile-form" method="post">
    <div class="profile-form-grid">
      <label>
        <span><?= h(ui_label('label.name', 'Name')) ?></span>
        <input name="name" value="<?= h($user['name'] ?? '') ?>" required>
      </label>
      <label>
        <span><?= h(ui_label('label.email', 'Email')) ?></span>
        <input value="<?= h($user['email'] ?? '') ?>" disabled>
      </label>
      <label>
        <span><?= h(ui_label('label.phone', 'Phone')) ?></span>
        <input name="phone" value="<?= h($user['phone'] ?? '') ?>">
      </label>
      <label>
        <span><?= h(ui_label('label.birthday', 'Birthday')) ?></span>
        <input name="birthday" type="date" value="<?= h($user['birthday'] ?? '') ?>">
      </label>
      <label>
        <span><?= h(ui_label('label.lang', 'Language')) ?></span>
        <input name="lang" value="<?= h($user['lang'] ?? current_lang_key()) ?>">
      </label>
      <label>
        <span><?= h(ui_label('label.sex', 'Sex')) ?></span>
        <?php $profileSex = trim((string)($user['sex'] ?? '')); ?>
        <select class="profile-sex-select" name="sex">
          <option value=""></option>
          <option value="male" <?= $profileSex === 'male' ? 'selected' : '' ?>>Male</option>
          <option value="female" <?= $profileSex === 'female' ? 'selected' : '' ?>>Female</option>
        </select>
      </label>
    </div>

    <label>
      <span><?= h(ui_label('label.avatar', 'Avatar URL')) ?></span>
      <input name="avatar" value="<?= h($user['avatar'] ?? '') ?>">
    </label>

    <label>
      <span><?= h(ui_label('label.address', 'Address')) ?></span>
      <textarea name="address" rows="3"><?= h($user['address'] ?? '') ?></textarea>
    </label>

    <div class="profile-actions">
      <button class="login-submit" type="submit"><?= h(ui_label('action.save', 'Save')) ?></button>
      <a class="login-secondary" href="login.php?logout=1"><?= h(ui_label('action.logout', 'Logout')) ?></a>
    </div>
  </form>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.jQuery && jQuery.fn.select2) {
    jQuery('.profile-sex-select').select2({
      width: '100%',
      minimumResultsForSearch: Infinity,
      dropdownParent: jQuery('.profile-page'),
      dropdownCssClass: 'profile-sex-dropdown'
    });
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
