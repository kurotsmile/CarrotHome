<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/visit_tracker.php';

if (isset($_GET['page'])) {
    $_GET['slug'] = trim($_GET['page']);
    include __DIR__ . '/page.php';
    exit;
}

visit_track_daily_ip($pdo ?? null);

$apps = [];
$total_apps = 0;
$error_message = $db_error ?? '';

$search = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? 'all');
$status = trim($_GET['status'] ?? '');

if ($pdo) {
    try {
        $where = [];
        $params = [];

        if ($status === '') {
            $status = 'public';
        }

        if ($status !== 'all') {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }

        if ($type !== 'all' && $type !== '') {
            $where[] = 'type = :type';
            $params[':type'] = $type;
        }

        if ($search !== '') {
            $search_value = '%' . $search . '%';
            $where[] = '(id LIKE :search_id OR decription LIKE :search_description)';
            $params[':search_id'] = $search_value;
            $params[':search_description'] = $search_value;
        }

        $where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM app {$where_sql}");
        $count_stmt->execute($params);
        $total_apps = (int)$count_stmt->fetchColumn();

        $sql = "SELECT id, id AS slug, decription, github, microsoft_store, icon, itch, exe_file, ipa_file, deb_file,
                       amazon_app_store, huawei_store, youtube_link, google_play, dmg_file, uptodown,
                       simmer, type, apk_file, status, sync_status, priority, category, created_at
                FROM app
                {$where_sql}
                ORDER BY priority DESC, created_at DESC, id ASC
                LIMIT 120";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $apps = $stmt->fetchAll();
    } catch (Throwable $e) {
        $error_message = $e->getMessage();
    }
}

$page_title = ui_label('meta.home_title', 'CarrotHome - App And Game');
$page_description = ui_label('meta.home_description', 'Store Carrot style app and game catalog loaded from PHP and MySQL.');
include __DIR__ . '/includes/header.php';
?>

<section class="home-intro">
  <h2><?= h(ui_label('home.heading', 'App And Game')) ?></h2>
  <p><?= h(ui_label('home.intro', 'Store Carrot specializes in publishing games and applications that support learning and working across multiple platforms.')) ?></p>
</section>

<?php if ($error_message): ?>
  <div class="empty-state">
    <strong><?= h(ui_label('error.mysql_connection', 'Lỗi kết nối MySQL:')) ?></strong><br>
    <?= h($error_message) ?><br><br>
    <?= h(ui_label('error.database_check', 'Kiểm tra database carrot_home và import file sql/carrot_home.sql.')) ?>
  </div>
<?php endif; ?>

<div class="result-line"><?= h($total_apps) ?> <?= h(ui_label('label.apps', 'apps')) ?></div>

<?php if (!$error_message && count($apps) === 0): ?>
  <p class="empty-state"><?= h(ui_label('empty.no_matching_apps', 'Không tìm thấy app phù hợp.')) ?></p>
<?php endif; ?>

<ol class="shots-grid">
  <?php foreach ($apps as $app): ?>
    <?php
      $name = $app['id'];
      $slug = $app['slug'] ?? $app['id'];
      $icon = app_card_icon($app);
      $downloads = app_download_links($app);
      $stores = app_store_links($app);
      $primary_store = first_active_link($stores);
    ?>
    <li class="shot-thumbnail">
      <div class="shot-thumbnail-base">
        <figure class="shot-thumbnail-placeholder">
          <img src="<?= h($icon) ?>" alt="<?= h($name) ?>" loading="lazy">
        </figure>
        <a class="shot-thumbnail-link" href="<?= h(app_url($slug)) ?>" aria-label="<?= h(ui_label('aria.view_app', 'View')) ?> <?= h($name) ?>"></a>
        <div class="shot-thumbnail-overlay">
          <div class="shot-thumbnail-overlay-content">
            <?php foreach ($downloads as $key => $url): ?>
              <a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer" class="download-chip" aria-label="<?= h(label_name($key)) ?>" title="<?= h(label_name($key)) ?>">
                <?= download_icon($key) ?>
                <span><?= h(short_label($key)) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="shot-details-container">
        <div class="user-information">
          <?= type_icon($app['type'] ?? 'app') ?>
          <span class="display-name"><?= h($name) ?></span>
          <a class="badge-link" href="index.php?type=<?= urlencode($app['type'] ?? 'app') ?>"><?= h($app['type'] ?? 'app') ?></a>
        </div>
        <?php if ($primary_store): ?>
          <a class="store-link" href="<?= h($primary_store) ?>" target="_blank" rel="noopener noreferrer"><?= h(ui_label('action.store', 'Store')) ?></a>
        <?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
</ol>

<?php include __DIR__ . '/includes/footer.php'; ?>
