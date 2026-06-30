<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/visit_tracker.php';

initialize_language_from_ip($pdo ?? null);
visit_track_daily_ip($pdo ?? null);

$stores = [];
$error_message = $db_error ?? '';

if ($pdo) {
    try {
        $stmt = $pdo->query('
            SELECT id, slug, title, description, icon, link, platform, sort_order, status
            FROM app_store
            WHERE status = "active"
            ORDER BY sort_order ASC, title ASC, id DESC
        ');
        $stores = $stmt->fetchAll();
    } catch (Throwable $e) {
        $error_message = $e->getMessage();
    }
}

$page_title = ui_label('meta.stores_title', 'Our Stores - CarrotHome');
$page_description = ui_label('meta.stores_description', 'Official stores and distribution channels for Carrot apps and games.');

include __DIR__ . '/includes/header.php';
?>

<section class="category-page">
  <div class="category-page__header">
    <p class="eyebrow"><?= h(ui_label('nav.our_stores', 'Our Stores')) ?></p>
    <h2><?= h(ui_label('stores.heading', 'Our Stores')) ?></h2>
    <p><?= h(ui_label('stores.intro', 'Find Carrot apps and games across our official stores and distribution channels.')) ?></p>
  </div>

  <?php if ($error_message): ?>
    <div class="empty-state">
      <strong><?= h(ui_label('error.mysql_connection', 'Lỗi kết nối MySQL:')) ?></strong><br>
      <?= h($error_message) ?>
    </div>
  <?php endif; ?>

  <?php if (!$error_message && !count($stores)): ?>
    <p class="empty-state"><?= h(ui_label('empty.no_stores', 'Chưa có store nào.')) ?></p>
  <?php endif; ?>

  <?php if (!$error_message && count($stores)): ?>
    <ol class="category-grid">
      <?php foreach ($stores as $store): ?>
        <?php
          $title = trim((string)($store['title'] ?? '')) ?: (string)($store['slug'] ?? '');
          $link = trim((string)($store['link'] ?? ''));
        ?>
        <li class="category-card">
          <a href="<?= h($link !== '' ? $link : '#') ?>" <?= $link !== '' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
            <figure>
              <img src="<?= h(app_icon($store['icon'] ?? '')) ?>" alt="<?= h($title) ?>" loading="lazy">
            </figure>
            <div>
              <h3><?= h($title) ?></h3>
              <?php if (trim((string)($store['description'] ?? '')) !== ''): ?>
                <p><?= h($store['description']) ?></p>
              <?php endif; ?>
              <span><?= h(trim((string)($store['platform'] ?? '')) ?: ui_label('label.store', 'Store')) ?></span>
            </div>
          </a>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
