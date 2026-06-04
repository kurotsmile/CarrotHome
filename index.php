<?php
$db_host = 'localhost';
$db_name = 'carrot_home';
$db_user = 'root';
$db_pass = '';
$db_charset = 'utf8mb4';

$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$apps = [];
$total_apps = 0;
$types = [];
$error_message = '';

$search = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? 'all');
$status = trim($_GET['status'] ?? 'publish');

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);

    $type_stmt = $pdo->query("SELECT DISTINCT type FROM apps WHERE type IS NOT NULL AND type != '' ORDER BY type ASC");
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
        $where[] = '(name_en LIKE :search OR app_id LIKE :search OR type LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    $where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM apps {$where_sql}");
    $count_stmt->execute($params);
    $total_apps = (int)$count_stmt->fetchColumn();

    $sql = "SELECT id, app_id, slug, name_en, type, status, priority, date_create, icon, download_links, store_links, images, video_links
            FROM apps
            {$where_sql}
            ORDER BY priority DESC, date_create DESC, id DESC
            LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $apps = $stmt->fetchAll();
} catch (Throwable $e) {
    $error_message = $e->getMessage();
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function json_array($json) {
    if (!$json) return [];
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function first_image($app) {
    $images = json_array($app['images'] ?? '');
    foreach ($images as $url) {
        if (!empty($url)) return $url;
    }
    return $app['icon'] ?? '';
}

function active_links($json) {
    $items = json_array($json);
    $links = [];
    foreach ($items as $key => $url) {
        if (!empty($url)) {
            $links[$key] = $url;
        }
    }
    return $links;
}

function label_name($key) {
    $labels = [
        'apk_file' => 'APK',
        'exe_file' => 'Windows',
        'deb_file' => 'Linux DEB',
        'dmg_file' => 'macOS',
        'ipa_file' => 'iOS',
        'google_play' => 'Google Play',
        'amazon_app_store' => 'Amazon',
        'microsoft_store' => 'Microsoft',
        'itch' => 'Itch.io',
        'uptodown' => 'Uptodown',
        'huawei_store' => 'Huawei',
        'simmer' => 'Simmer',
        'youtube_link' => 'YouTube',
    ];

    return $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>CarrotHome - Kho lưu trữ ứng dụng</title>
  <meta name="description" content="CarrotHome hiển thị danh sách game và ứng dụng từ MySQL database carrot_home." />
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <div>
        <p class="eyebrow">CarrotHome</p>
        <h1>Kho lưu trữ ứng dụng</h1>
        <p class="subtitle">Danh sách game và ứng dụng được lấy trực tiếp từ MySQL database <strong>carrot_home</strong>.</p>
      </div>
      <div class="stats-card">
        <span><?= h($total_apps) ?></span>
        <small>apps</small>
      </div>
    </div>
  </header>

  <main class="container">
    <?php if ($error_message): ?>
      <div class="empty-state">
        <strong>Lỗi kết nối MySQL:</strong><br>
        <?= h($error_message) ?><br><br>
        Kiểm tra lại XAMPP MySQL, database <code>carrot_home</code>, user <code>root</code> và file <code>sql/carrot_home.sql</code> đã import chưa.
      </div>
    <?php endif; ?>

    <form class="toolbar" method="get" action="index.php" aria-label="Bộ lọc ứng dụng">
      <input name="q" type="search" placeholder="Tìm app theo tên, id, loại..." value="<?= h($search) ?>" />

      <select name="type">
        <option value="all">Tất cả loại</option>
        <?php foreach ($types as $item): ?>
          <option value="<?= h($item) ?>" <?= $type === $item ? 'selected' : '' ?>><?= h($item) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="status">
        <option value="publish" <?= $status === 'publish' ? 'selected' : '' ?>>Publish</option>
        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
        <option value="trash" <?= $status === 'trash' ? 'selected' : '' ?>>Trash</option>
        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tất cả trạng thái</option>
      </select>

      <button class="search-button" type="submit">Tìm kiếm</button>
    </form>

    <?php if (!$error_message && count($apps) === 0): ?>
      <p class="empty-state">Không tìm thấy app phù hợp.</p>
    <?php endif; ?>

    <section class="app-grid" aria-live="polite">
      <?php foreach ($apps as $app): ?>
        <?php
          $downloads = active_links($app['download_links'] ?? '');
          $stores = active_links($app['store_links'] ?? '');
          $videos = active_links($app['video_links'] ?? '');
          $cover = first_image($app);
        ?>
        <article class="app-card">
          <a class="app-cover" href="app.php?slug=<?= urlencode($app['slug']) ?>" aria-label="Xem <?= h($app['name_en']) ?>">
            <?php if ($cover): ?>
              <img src="<?= h($cover) ?>" alt="<?= h($app['name_en']) ?>" loading="lazy" />
            <?php endif; ?>
          </a>

          <div class="app-body">
            <h2 class="app-title">
              <a href="app.php?slug=<?= urlencode($app['slug']) ?>"><?= h($app['name_en']) ?></a>
            </h2>

            <div class="app-meta">
              <span class="badge"><?= h($app['type']) ?></span>
              <span class="badge"><?= h($app['status']) ?></span>
            </div>

            <div class="downloads">
              <?php foreach ($downloads as $key => $url): ?>
                <a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer"><?= h(label_name($key)) ?></a>
              <?php endforeach; ?>

              <?php foreach ($stores as $key => $url): ?>
                <a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer"><?= h(label_name($key)) ?></a>
              <?php endforeach; ?>

              <?php foreach ($videos as $key => $url): ?>
                <a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer"><?= h(label_name($key)) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container">CarrotHome PHP + MySQL. Database: <code>carrot_home</code>, table: <code>apps</code>.</div>
  </footer>
</body>
</html>
