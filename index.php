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
$error_message = $db_error ?? '';

$search = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? 'all');
$status = trim($_GET['status'] ?? '');

if ($pdo) {
    try {
        $has_apps_table_stmt = $pdo->query("SHOW TABLES LIKE 'apps'");
        $use_apps_table = (bool)$has_apps_table_stmt->fetchColumn();
        $where = [];
        $params = [];

        if ($use_apps_table) {
            if ($status === '') {
                $status = 'publish';
            }
        } elseif ($status === '') {
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
            if ($use_apps_table) {
                $where[] = '(name_en LIKE :search OR app_id LIKE :search OR slug LIKE :search OR type LIKE :search OR CAST(category AS CHAR) LIKE :search)';
            } else {
                $where[] = '(id LIKE :search OR decription LIKE :search OR category LIKE :search OR type LIKE :search)';
            }
            $params[':search'] = '%' . $search . '%';
        }

        $where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $table_name = $use_apps_table ? 'apps' : 'app';
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table_name} {$where_sql}");
        $count_stmt->execute($params);
        $total_apps = (int)$count_stmt->fetchColumn();

        if ($use_apps_table) {
            $sql = "SELECT name_en AS id, slug, NULL AS decription, NULL AS github,
                           JSON_UNQUOTE(JSON_EXTRACT(store_links, '$.microsoft_store')) AS microsoft_store,
                           icon,
                           JSON_UNQUOTE(JSON_EXTRACT(store_links, '$.itch')) AS itch,
                           JSON_UNQUOTE(JSON_EXTRACT(download_links, '$.exe_file')) AS exe_file,
                           JSON_UNQUOTE(JSON_EXTRACT(download_links, '$.ipa_file')) AS ipa_file,
                           JSON_UNQUOTE(JSON_EXTRACT(download_links, '$.deb_file')) AS deb_file,
                           JSON_UNQUOTE(JSON_EXTRACT(store_links, '$.amazon_app_store')) AS amazon_app_store,
                           JSON_UNQUOTE(JSON_EXTRACT(store_links, '$.huawei_store')) AS huawei_store,
                           JSON_UNQUOTE(JSON_EXTRACT(video_links, '$.youtube_link')) AS youtube_link,
                           JSON_UNQUOTE(JSON_EXTRACT(store_links, '$.google_play')) AS google_play,
                           JSON_UNQUOTE(JSON_EXTRACT(download_links, '$.dmg_file')) AS dmg_file,
                           JSON_UNQUOTE(JSON_EXTRACT(store_links, '$.uptodown')) AS uptodown,
                           JSON_UNQUOTE(JSON_EXTRACT(store_links, '$.simmer')) AS simmer,
                           type,
                           JSON_UNQUOTE(JSON_EXTRACT(download_links, '$.apk_file')) AS apk_file,
                           status, NULL AS sync_status, priority, CAST(category AS CHAR) AS category, created_at, icons
                    FROM apps
                    {$where_sql}
                    ORDER BY priority DESC, date_create DESC, name_en ASC
                    LIMIT 120";
        } else {
            $sql = "SELECT id, id AS slug, decription, github, microsoft_store, icon, itch, exe_file, ipa_file, deb_file,
                           amazon_app_store, huawei_store, youtube_link, google_play, dmg_file, uptodown,
                           simmer, type, apk_file, status, sync_status, priority, category, created_at
                    FROM app
                    {$where_sql}
                    ORDER BY priority DESC, created_at DESC, id ASC
                    LIMIT 120";
        }

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

<div class="result-line"><?= h($total_apps) ?> apps</div>

<?php if (!$error_message && count($apps) === 0): ?>
  <p class="empty-state">Không tìm thấy app phù hợp.</p>
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
        <a class="shot-thumbnail-link" href="<?= h(app_url($slug)) ?>" aria-label="View <?= h($name) ?>"></a>
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
          <a class="store-link" href="<?= h($primary_store) ?>" target="_blank" rel="noopener noreferrer">Store</a>
        <?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
</ol>

<?php include __DIR__ . '/includes/footer.php'; ?>
