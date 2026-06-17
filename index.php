<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_GET['page'])) {
    $_GET['slug'] = trim($_GET['page']);
    include __DIR__ . '/page.php';
    exit;
}

$apps = [];
$total_apps = 0;
$types = [];
$error_message = $db_error ?? '';

$search = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? 'all');
$status = trim($_GET['status'] ?? 'public');

if ($pdo) {
    try {
        $type_stmt = $pdo->query("SELECT DISTINCT type FROM app WHERE type IS NOT NULL AND type != '' ORDER BY type ASC");
        $types = $type_stmt->fetchAll(PDO::FETCH_COLUMN);

        $where = [];
        $params = [];

        if ($status !== 'all') {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }

        if ($type !== 'all' && $type !== '') {
            $where[] = 'type = :type';
            $params[':type'] = $type;
        }

        if ($search !== '') {
            $where[] = '(id LIKE :search OR decription LIKE :search OR category LIKE :search OR type LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM app {$where_sql}");
        $count_stmt->execute($params);
        $total_apps = (int)$count_stmt->fetchColumn();

        $sql = "SELECT id, decription, github, microsoft_store, icon, itch, exe_file, ipa_file, deb_file,
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

$page_title = 'CarrotHome - App And Game';
$page_description = 'Store Carrot style app and game catalog loaded from PHP and MySQL.';
include __DIR__ . '/includes/header.php';
?>

<section class="home-intro">
  <h2>App And Game</h2>
  <p>Store Carrot specializes in publishing <strong>games</strong> and <strong>applications</strong> that support learning and working across multiple platforms.</p>
</section>

<?php if ($error_message): ?>
  <div class="empty-state">
    <strong>Lỗi kết nối MySQL:</strong><br>
    <?= h($error_message) ?><br><br>
    Kiểm tra database <code>carrot_home</code> và import file <code>sql/carrot_home.sql</code>.
  </div>
<?php endif; ?>

<form class="store-toolbar" method="get" action="index.php" aria-label="Bộ lọc ứng dụng">
  <input name="q" type="search" placeholder="Looking for something special?" value="<?= h($search) ?>">

  <select name="type" aria-label="Type">
    <option value="all">All types</option>
    <?php foreach ($types as $item): ?>
      <option value="<?= h($item) ?>" <?= $type === $item ? 'selected' : '' ?>><?= h(ucfirst($item)) ?></option>
    <?php endforeach; ?>
  </select>

  <select name="status" aria-label="Status">
    <option value="public" <?= $status === 'public' ? 'selected' : '' ?>>Public</option>
    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
    <option value="trash" <?= $status === 'trash' ? 'selected' : '' ?>>Trash</option>
    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All status</option>
  </select>

  <button class="search-button" type="submit" aria-label="Search">
    <span>Search</span>
  </button>
</form>

<div class="result-line"><?= h($total_apps) ?> apps</div>

<?php if (!$error_message && count($apps) === 0): ?>
  <p class="empty-state">Không tìm thấy app phù hợp.</p>
<?php endif; ?>

<ol class="shots-grid">
  <?php foreach ($apps as $app): ?>
    <?php
      $name = $app['id'];
      $slug = $app['id'];
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
        <a class="shot-thumbnail-link" href="<?= h(app_url($slug)) ?>" aria-label="View <?= h($name) ?>"></a>
        <div class="shot-thumbnail-overlay">
          <div class="shot-thumbnail-overlay-content">
            <div class="shot-title"><?= h($name) ?></div>
            <?php foreach ($downloads as $key => $url): ?>
              <a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer" class="download-chip"><?= h(short_label($key)) ?></a>
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
          <a class="store-link" href="<?= h($primary_store) ?>" target="_blank" rel="noopener noreferrer">Store</a>
        <?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
</ol>

<?php include __DIR__ . '/includes/footer.php'; ?>
