<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
$app = null;
$error_message = $db_error ?? '';

if (!$slug) {
    http_response_code(404);
    $page_title = 'App not found - CarrotHome';
    $page_description = 'The requested app was not found.';
    include 'includes/header.php';
    echo '<div class="empty-state"><strong>Không tìm thấy app.</strong><br>Thiếu tham số <code>slug</code>.</div>';
    include 'includes/footer.php';
    exit;
}

if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM app WHERE id = :slug AND status != 'trash' LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $app = $stmt->fetch();
    } catch (Throwable $e) {
        $error_message = $e->getMessage();
    }
}

if (!$app && !$error_message) {
    http_response_code(404);
    $page_title = 'App not found - CarrotHome';
    $page_description = 'The requested app was not found.';
    include 'includes/header.php';
    echo '<div class="empty-state"><strong>Không tìm thấy app:</strong><br>' . h($slug) . '</div>';
    include 'includes/footer.php';
    exit;
}

if ($error_message) {
    http_response_code(500);
    $page_title = 'Database error - CarrotHome';
    $page_description = 'Database connection error.';
    include 'includes/header.php';
    echo '<div class="empty-state"><strong>Lỗi MySQL:</strong><br>' . h($error_message) . '</div>';
    include 'includes/footer.php';
    exit;
}

$page_title = ($app['name_en'] ?? 'App') . ' - CarrotHome';
$page_description = 'Download ' . ($app['name_en'] ?? 'app') . ' for Android, Windows, macOS, Linux and other platforms.';

$images = json_array($app['images'] ?? '');
$downloads = active_links($app['download_links'] ?? '');
$stores = active_links($app['store_links'] ?? '');
$videos = active_links($app['video_links'] ?? '');
$cover = first_image($app);

include 'includes/header.php';
?>

<section class="app-detail">
  <div class="app-detail-hero">
    <div class="app-detail-icon">
      <img src="<?= h(app_icon($app['icon'] ?? '')) ?>" alt="<?= h($app['name_en']) ?> icon" loading="lazy">
    </div>

    <div class="app-detail-info">
      <p class="eyebrow"><?= h($app['type'] ?? 'app') ?></p>
      <h2><?= h($app['name_en']) ?></h2>
      <p class="subtitle">App ID: <?= h($app['id'] ?? '') ?></p>
      <div class="app-meta">
        <span class="badge"><?= h($app['status'] ?? '') ?></span>
        <?php if (!empty($app['date_create'])): ?>
          <span class="badge"><?= h($app['date_create']) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($cover): ?>
    <div class="app-main-image">
      <img src="<?= h($cover) ?>" alt="<?= h($app['name_en']) ?> preview" loading="lazy">
    </div>
  <?php endif; ?>

  <?php if (count($images)): ?>
    <h3>Ảnh giới thiệu</h3>
    <div class="gallery-grid">
      <?php foreach ($images as $image_url): ?>
        <?php if (!empty($image_url)): ?>
          <img src="<?= h($image_url) ?>" alt="<?= h($app['name_en']) ?> screenshot" loading="lazy">
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (count($downloads)): ?>
    <h3>Tải xuống</h3>
    <div class="downloads detail-links">
      <?php foreach ($downloads as $key => $url): ?>
        <a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer"><?= h(label_name($key)) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (count($stores)): ?>
    <h3>Cửa hàng</h3>
    <div class="downloads detail-links">
      <?php foreach ($stores as $key => $url): ?>
        <a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer"><?= h(label_name($key)) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (count($videos)): ?>
    <h3>Video</h3>
    <div class="downloads detail-links">
      <?php foreach ($videos as $key => $url): ?>
        <a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer"><?= h(label_name($key)) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
