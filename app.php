<?php
session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/visit_tracker.php';

visit_track_daily_ip($pdo ?? null);

$slug = trim($_GET['slug'] ?? '');
$app = null;
$error_message = $db_error ?? '';
$paypal_config = file_exists(__DIR__ . '/config/paypal.php') ? require __DIR__ . '/config/paypal.php' : [];

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

$app_name = $app['id'] ?? 'App';
$page_title = $app_name . ' - CarrotHome';
$page_description = 'Download ' . $app_name . ' for Android, Windows, macOS, Linux and other platforms.';

$images = [];
$downloads = app_download_links($app);
$stores = app_store_links($app);
$videos = app_video_links($app);
$github_url = trim((string)($app['github'] ?? ''));
$has_paid_github = !empty($_SESSION['paid_github_apps'][$app_name]);
$share_url = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'home.carrot28.com') . app_url($app_name);

include 'includes/header.php';
?>

<section class="app-detail">
  <div class="app-detail-hero">
    <div class="app-detail-icon">
      <img src="<?= h(app_icon($app['icon'] ?? '')) ?>" alt="<?= h($app_name) ?> icon" loading="lazy">
    </div>

    <div class="app-detail-info">
      <p class="eyebrow"><?= h($app['type'] ?? 'app') ?></p>
      <h2><?= h($app_name) ?></h2>
      <p class="subtitle"><?= h($app['category'] ?? '') ?></p>
      <div class="app-meta">
        <span class="badge"><?= h($app['status'] ?? '') ?></span>
        <?php if (!empty($app['created_at'])): ?>
          <span class="badge"><?= h($app['created_at']) ?></span>
        <?php endif; ?>
      </div>
      <button class="share-button" type="button" data-share-url="<?= h($share_url) ?>" data-share-title="<?= h($app_name) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 8a3 3 0 1 0-2.8-4M8 12l8-4M8 12l8 4M8 12a3 3 0 1 1-3-3 3 3 0 0 1 3 3Zm11 7a3 3 0 1 1-3-3 3 3 0 0 1 3 3Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span>Share</span>
      </button>
    </div>
  </div>

  <?php if (!empty($app['decription'])): ?>
    <div class="app-description">
      <?= nl2br(h($app['decription'])) ?>
    </div>
  <?php endif; ?>

  <?php if (count($images)): ?>
    <h3>Ảnh giới thiệu</h3>
    <div class="gallery-grid">
      <?php foreach (array_values($images) as $image_url): ?>
        <?php if (!empty($image_url)): ?>
          <img src="<?= h(asset_url($image_url)) ?>" alt="<?= h($app_name) ?> screenshot" loading="lazy">
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (count($downloads)): ?>
    <h3>Tải xuống</h3>
    <div class="downloads detail-links">
      <?php foreach ($downloads as $key => $url): ?>
        <a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer">
          <?= download_icon($key) ?>
          <span><?= h(label_name($key)) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (count($stores)): ?>
    <h3>Cửa hàng</h3>
    <div class="downloads detail-links">
      <?php foreach ($stores as $key => $url): ?>
        <a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer">
          <?= store_icon($key) ?>
          <span><?= h(label_name($key)) ?></span>
        </a>
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

  <?php if ($github_url): ?>
    <h3>GitHub</h3>
    <div class="downloads detail-links paid-links">
      <?php if ($has_paid_github): ?>
        <a href="<?= h($github_url) ?>" target="_blank" rel="noopener noreferrer">
          <?= store_icon('github') ?>
          <span>GitHub</span>
        </a>
      <?php elseif (!empty($paypal_config['enabled'])): ?>
        <a class="paypal-button" href="<?= h(base_url('paypal-create.php?slug=' . urlencode($app_name))) ?>">
          <?= store_icon('paypal') ?>
          <span>PayPal <?= h($paypal_config['amount'] ?? '') ?> <?= h($paypal_config['currency'] ?? '') ?></span>
        </a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<script>
document.querySelectorAll('.share-button').forEach(function(button){
  button.addEventListener('click', function(){
    var shareUrl = button.getAttribute('data-share-url');
    var shareTitle = button.getAttribute('data-share-title');
    if (navigator.share) {
      navigator.share({title: shareTitle, url: shareUrl});
      return;
    }
    if (navigator.clipboard) {
      navigator.clipboard.writeText(shareUrl);
      button.classList.add('is-copied');
      setTimeout(function(){ button.classList.remove('is-copied'); }, 1600);
    }
  });
});
</script>

<?php include 'includes/footer.php'; ?>
