<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

initialize_language_from_ip($pdo ?? null);

if (empty($_SESSION['home_user_id'])) {
    header('Location: login.php');
    exit;
}

$orders = [];
$error_message = $db_error ?? '';

if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare('
            SELECT o.*, a.icon AS app_icon, a.decription AS app_description, ac.title AS app_title
            FROM app_orders o
            LEFT JOIN app a ON a.id = o.app_id
            LEFT JOIN app_content ac ON ac.app_id = o.app_id AND ac.lang_key = ?
            WHERE o.user_id = ?
            ORDER BY o.created_at DESC, o.id DESC
        ');
        $stmt->execute([current_lang_key(), (int)$_SESSION['home_user_id']]);
        $orders = $stmt->fetchAll();
    } catch (Throwable $e) {
        $error_message = $e->getMessage();
    }
}

$page_title = ui_label('order.title', 'Orders') . ' - CarrotHome';
$page_description = ui_label('order.description', 'Your CarrotHome orders.');
include __DIR__ . '/includes/header.php';
?>

<section class="profile-page">
  <div class="profile-tabs">
    <a href="profile.php"><?= h(ui_label('profile.tab_information', 'Information')) ?></a>
    <a class="is-active" href="order.php"><?= h(ui_label('profile.tab_order', 'Order')) ?></a>
  </div>

  <div class="profile-header">
    <div>
      <p class="eyebrow"><?= h(ui_label('profile.tab_order', 'Order')) ?></p>
      <h2><?= h(ui_label('order.heading', 'Your orders')) ?></h2>
      <p><?= h(ui_label('order.intro', 'Track app source purchases and payment status.')) ?></p>
    </div>
  </div>

  <?php if ($error_message): ?><div class="login-alert login-alert--error"><?= h($error_message) ?></div><?php endif; ?>

  <div class="order-list">
    <?php foreach ($orders as $order): ?>
      <?php $orderTitle = trim((string)($order['app_title'] ?? '')) ?: (string)($order['app_id'] ?? ''); ?>
      <article class="order-item">
        <div class="order-app">
          <?php if (!empty($order['app_icon'])): ?>
            <img src="<?= h($order['app_icon']) ?>" alt="">
          <?php endif; ?>
          <div>
            <h3><?= h($orderTitle) ?></h3>
            <p><?= h($order['paypal_order_id'] ?? '') ?></p>
          </div>
        </div>
        <div class="order-meta">
          <span class="badge"><?= h($order['status'] ?? '') ?></span>
          <strong><?= h(number_format((float)($order['amount'] ?? 0), 2)) ?> <?= h($order['currency'] ?? 'USD') ?></strong>
          <small><?= h($order['created_at'] ?? '') ?></small>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$orders && !$error_message): ?>
      <p class="empty-state"><?= h(ui_label('order.empty', 'Bạn chưa có đơn đặt hàng.')) ?></p>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
