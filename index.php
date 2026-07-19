<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/visit_tracker.php';

initialize_language_from_ip($pdo ?? null);

if (isset($_GET['page']) && strtolower(trim((string) $_GET['page'])) === 'app') {
    $_GET['slug'] = trim((string) ($_GET['id'] ?? ($_GET['slug'] ?? '')));
    include __DIR__ . '/app.php';
    exit;
}

if (isset($_GET['page'])) {
    $_GET['slug'] = trim($_GET['page']);
    include __DIR__ . '/page.php';
    exit;
}

if (isset($_GET['slug'])) {
    $slug = trim((string)$_GET['slug']);
    $slug_candidates = slug_lookup_candidates($slug);
    $page_lang = trim((string)($_GET['lang'] ?? ($_SESSION['key_lang'] ?? 'en')));
    $is_page_slug = false;

    if ($pdo && $slug_candidates) {
        try {
            $placeholders = [];
            $params = [
                ':lang_filter' => $page_lang,
            ];
            foreach ($slug_candidates as $index => $candidate) {
                $key = ':slug' . $index;
                $placeholders[] = $key;
                $params[$key] = $candidate;
            }

            $stmt = $pdo->prepare("
                SELECT 1
                FROM page
                WHERE slug IN (" . implode(',', $placeholders) . ")
                  AND (lang = :lang_filter OR lang = '' OR lang IS NULL)
                LIMIT 1
            ");
            $stmt->execute($params);
            $is_page_slug = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $is_page_slug = false;
        }
    }

    include __DIR__ . ($is_page_slug ? '/page.php' : '/app.php');
    exit;
}

visit_track_daily_ip($pdo ?? null);

$apps = [];
$total_apps = 0;
$error_message = $db_error ?? '';

$search = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? 'all');
$status = trim($_GET['status'] ?? '');
$sort = trim((string) ($_GET['sort'] ?? 'trending'));
$dir = strtolower(trim((string) ($_GET['dir'] ?? 'desc')));
$sort_options = [
    'new' => ['label' => ui_label('sort.new', 'new'), 'default_dir' => 'desc'],
    'trending' => ['label' => ui_label('sort.trending', 'thịnh hành'), 'default_dir' => 'desc'],
    'views' => ['label' => ui_label('sort.views', 'xem nhiều nhất'), 'default_dir' => 'desc'],
];
if (!isset($sort_options[$sort])) {
    $sort = 'trending';
}
if (!in_array($dir, ['asc', 'desc'], true)) {
    $dir = $sort_options[$sort]['default_dir'];
}

function home_sort_url(string $sort_key, string $current_sort, string $current_dir, array $sort_options): string
{
    $query = $_GET;
    $query['sort'] = $sort_key;
    $query['dir'] = ($sort_key === $current_sort && $current_dir === 'desc') ? 'asc' : 'desc';
    if ($sort_key !== $current_sort) {
        $query['dir'] = $sort_options[$sort_key]['default_dir'] ?? 'desc';
    }
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null || ($key === 'type' && $value === 'all') || ($key === 'status' && $value === '')) {
            unset($query[$key]);
        }
    }

    return base_url('index.php') . (count($query) ? '?' . http_build_query($query) : '');
}

if ($pdo) {
    try {
        $where = [];
        $params = [];
        $lang_key = current_lang_key();
        $params[':lang_key'] = $lang_key;

        if ($status === '') {
            $status = 'public';
        }

        if ($status !== 'all') {
            $where[] = 'a.status = :status';
            $params[':status'] = $status;
        }

        if ($type !== 'all' && $type !== '') {
            $where[] = 'a.type = :type';
            $params[':type'] = $type;
        }

        if ($search !== '') {
            $search_value = '%' . $search . '%';
            $where[] = '(a.id LIKE :search_id OR a.decription LIKE :search_description OR ac.title LIKE :search_title)';
            $params[':search_id'] = $search_value;
            $params[':search_description'] = $search_value;
            $params[':search_title'] = $search_value;
        }

        $cache_ttl = $search === '' ? 86400 : 900;
        $cache_key = carrot_cache_key('home_apps', [
            'lang' => $lang_key,
            'type' => $type,
            'status' => $status,
            'sort' => $sort,
            'dir' => $dir,
            'q' => $search !== '' ? sha1($search) : '',
        ]);

        $cached_home = carrot_cache_get($cache_key, $cache_ttl);
        if (is_array($cached_home)) {
            $apps = is_array($cached_home['apps'] ?? null) ? $cached_home['apps'] : [];
            $total_apps = (int)($cached_home['total_apps'] ?? 0);
        } else {

            $where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            $order_dir = $dir === 'asc' ? 'ASC' : 'DESC';
            $order_by = 'a.created_at ' . $order_dir . ', a.priority DESC, a.id ASC';
            if ($sort === 'trending') {
                $order_by = 'a.priority ' . $order_dir . ', a.created_at DESC, a.id ASC';
            } elseif ($sort === 'views') {
                $order_by = 'view_count ' . $order_dir . ', a.priority DESC, a.created_at DESC, a.id ASC';
            }

            $count_stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT a.id)
                FROM app a
                LEFT JOIN app_content ac
                  ON ac.app_id = a.id AND ac.lang_key = :lang_key
                {$where_sql}
            ");
            $count_stmt->execute($params);
            $total_apps = (int)$count_stmt->fetchColumn();

            $has_app_view_table = true;
            try {
                $pdo->query('SELECT 1 FROM app_view LIMIT 1');
            } catch (Throwable $viewTableError) {
                $has_app_view_table = false;
            }

            $view_join_sql = $has_app_view_table
                ? "SELECT app_id, COUNT(*) AS view_count FROM app_view GROUP BY app_id"
                : "SELECT '' AS app_id, 0 AS view_count WHERE 1 = 0";

            $sql = "SELECT a.id, a.id AS slug, a.decription, a.github, a.microsoft_store, a.icon, a.itch, a.exe_file, a.ipa_file, a.deb_file,
                           a.amazon_app_store, a.huawei_store, a.youtube_link, a.google_play, a.dmg_file, a.uptodown,
                           a.simmer, a.type, a.apk_file, a.status, a.priority, a.price, a.category, a.created_at,
                           ac.title AS app_content_title,
                           COALESCE(av.view_count, 0) AS view_count
                    FROM app a
                    LEFT JOIN app_content ac
                      ON ac.app_id = a.id AND ac.lang_key = :lang_key
                    LEFT JOIN (
                      {$view_join_sql}
                    ) av ON av.app_id = a.id
                    {$where_sql}
                    ORDER BY {$order_by}
                    LIMIT 120";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $apps = $stmt->fetchAll();

            carrot_cache_set($cache_key, [
                'created_at' => date('c'),
                'total_apps' => $total_apps,
                'apps' => $apps,
            ]);
        }
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

<div class="result-bar">
  <div class="result-line"><?= h($total_apps) ?> <?= h(ui_label('label.apps', 'apps')) ?></div>
  <nav class="sort-filter" aria-label="<?= h(ui_label('aria.sort_apps', 'Sort apps')) ?>">
    <?php foreach ($sort_options as $sort_key => $sort_option): ?>
      <?php
        $is_active_sort = $sort === $sort_key;
        $next_dir = ($is_active_sort && $dir === 'desc') ? 'asc' : 'desc';
        $sort_label = (string) ($sort_option['label'] ?? $sort_key);
      ?>
      <a class="sort-filter__item<?= $is_active_sort ? ' is-active' : '' ?>" href="<?= h(home_sort_url($sort_key, $sort, $dir, $sort_options)) ?>" aria-label="<?= h($sort_label . ' ' . strtoupper($is_active_sort ? $next_dir : ($sort_option['default_dir'] ?? 'desc'))) ?>">
        <span><?= h($sort_label) ?></span>
        <span class="sort-filter__icon<?= $is_active_sort ? ' sort-filter__icon--' . h($dir) : '' ?>" aria-hidden="true"></span>
      </a>
    <?php endforeach; ?>
  </nav>
</div>

<?php if (!$error_message && count($apps) === 0): ?>
  <p class="empty-state"><?= h(ui_label('empty.no_matching_apps', 'Không tìm thấy app phù hợp.')) ?></p>
<?php endif; ?>

<ol class="shots-grid">
  <?php foreach ($apps as $app): ?>
    <?php
      $name = app_display_title($app);
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
          <a class="badge-link" href="<?= h(base_url('index.php')) ?>?type=<?= urlencode($app['type'] ?? 'app') ?>"><?= h($app['type'] ?? 'app') ?></a>
        </div>
        <?php if ($primary_store): ?>
          <a class="store-link" href="<?= h($primary_store) ?>" target="_blank" rel="noopener noreferrer"><?= h(ui_label('action.store', 'Store')) ?></a>
        <?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
</ol>

<?php include __DIR__ . '/includes/footer.php'; ?>
