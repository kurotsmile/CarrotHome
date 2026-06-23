<?php
session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/visit_tracker.php';

visit_track_daily_ip($pdo ?? null);

$slug = trim($_GET['slug'] ?? '');
$app = null;
$images = [];
$error_message = $db_error ?? '';
$paypal_config = file_exists(__DIR__ . '/config/paypal.php') ? require __DIR__ . '/config/paypal.php' : [];

if (!$slug) {
    http_response_code(404);
    $page_title = ui_label('meta.app_not_found_title', 'App not found - CarrotHome');
    $page_description = ui_label('meta.app_not_found_description', 'The requested app was not found.');
    include 'includes/header.php';
    echo '<div class="empty-state"><strong>' . h(ui_label('error.app_not_found', 'Không tìm thấy app.')) . '</strong><br>' . h(ui_label('error.missing_slug', 'Thiếu tham số slug.')) . '</div>';
    include 'includes/footer.php';
    exit;
}

if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM app WHERE id = :slug AND status != 'trash' LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $app = $stmt->fetch();
        if ($app) {
            try {
                $photoStmt = $pdo->prepare('SELECT image_url FROM app_photo WHERE app_id = :slug ORDER BY sort_order ASC, id ASC');
                $photoStmt->execute([':slug' => $slug]);
                $images = $photoStmt->fetchAll();
            } catch (Throwable $photoError) {
                $images = [];
            }
        }
    } catch (Throwable $e) {
        $error_message = $e->getMessage();
    }
}

if (!$app && !$error_message) {
    http_response_code(404);
    $page_title = ui_label('meta.app_not_found_title', 'App not found - CarrotHome');
    $page_description = ui_label('meta.app_not_found_description', 'The requested app was not found.');
    include 'includes/header.php';
    echo '<div class="empty-state"><strong>' . h(ui_label('error.app_not_found_colon', 'Không tìm thấy app:')) . '</strong><br>' . h($slug) . '</div>';
    include 'includes/footer.php';
    exit;
}

if ($error_message) {
    http_response_code(500);
    $page_title = ui_label('meta.database_error_title', 'Database error - CarrotHome');
    $page_description = ui_label('meta.database_error_description', 'Database connection error.');
    include 'includes/header.php';
    echo '<div class="empty-state"><strong>' . h(ui_label('error.mysql', 'Lỗi MySQL:')) . '</strong><br>' . h($error_message) . '</div>';
    include 'includes/footer.php';
    exit;
}

$app_name = $app['id'] ?? 'App';
$page_title = $app_name . ' - CarrotHome';
$page_description = 'Download ' . $app_name . ' for Android, Windows, macOS, Linux and other platforms.';

$downloads = app_download_links($app);
$stores = app_store_links($app);
$videos = app_video_links($app);
$github_url = trim((string)($app['github'] ?? ''));
$has_paid_github = !empty($_SESSION['paid_github_apps'][$app_name]);
$share_url = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'home.carrot28.com') . app_url($app_name);
$source_price = trim((string)($app['price'] ?? ''));
if ($source_price === '' || (float)$source_price <= 0) {
    $source_price = trim((string)($paypal_config['amount'] ?? ''));
}
$source_price_label = $source_price !== '' ? number_format((float)$source_price, 2, '.', '') : '';
$source_currency = trim((string)($paypal_config['currency'] ?? 'USD'));
$extra_head = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">' . "\n";

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
          <?php $createdDate = date('Y-m-d', strtotime((string)$app['created_at'])); ?>
          <span class="badge"><?= h($createdDate) ?></span>
        <?php endif; ?>
      </div>
      <div class="app-hero-actions">
        <button class="share-button" type="button" data-share-url="<?= h($share_url) ?>" data-share-title="<?= h($app_name) ?>">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 8a3 3 0 1 0-2.8-4M8 12l8-4M8 12l8 4M8 12a3 3 0 1 1-3-3 3 3 0 0 1 3 3Zm11 7a3 3 0 1 1-3-3 3 3 0 0 1 3 3Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span><?= h(ui_label('action.share', 'Share')) ?></span>
        </button>
        <?php foreach ($stores as $key => $url): ?>
          <a class="store-action" href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer">
            <?= store_icon($key) ?>
            <span><?= h(label_name($key)) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="app-detail-layout">
    <div class="app-detail-content">
      <?php if (count($images)): ?>
        <h3><?= h(ui_label('section.screenshots', 'Ảnh giới thiệu')) ?></h3>
        <div class="app-photo-slider swiper" data-app-photo-slider>
          <button class="app-photo-slider__nav app-photo-slider__nav--prev" type="button" aria-label="<?= h(ui_label('action.previous', 'Previous')) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <div class="app-photo-slider__track swiper-wrapper">
            <?php foreach (array_values($images) as $image): ?>
              <?php $imageUrl = trim((string) ($image['image_url'] ?? '')); ?>
              <?php if ($imageUrl !== ''): ?>
                <?php $resolvedImageUrl = asset_url($imageUrl); ?>
                <figure class="app-photo-slider__slide swiper-slide">
                  <button class="app-photo-slider__zoom" type="button" data-lightbox-image="<?= h($resolvedImageUrl) ?>" aria-label="<?= h(ui_label('action.view_image', 'View image')) ?>">
                    <img src="<?= h($resolvedImageUrl) ?>" alt="<?= h($app_name) ?> screenshot" loading="lazy">
                  </button>
                </figure>
            <?php endif; ?>
          <?php endforeach; ?>
          </div>
          <button class="app-photo-slider__nav app-photo-slider__nav--next" type="button" aria-label="<?= h(ui_label('action.next', 'Next')) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <div class="app-photo-slider__pagination"></div>
        </div>
      <?php endif; ?>

      <?php if (!empty($app['decription'])): ?>
        <div class="app-description">
          <?= nl2br(h($app['decription'])) ?>
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
    </div>

    <?php if (count($downloads) || $github_url): ?>
      <aside class="app-detail-sidebar" aria-label="<?= h(ui_label('aria.app_links', 'App links')) ?>">
        <?php if (count($downloads)): ?>
          <div class="sidebar-panel download-panel">
            <h3><?= h(ui_label('section.downloads', 'Tải xuống')) ?></h3>
            <div class="sidebar-links">
              <?php foreach ($downloads as $key => $url): ?>
                <a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer">
                  <?= download_icon($key) ?>
                  <span><?= h(label_name($key)) ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($github_url): ?>
          <div class="source-promo">
            <div class="source-promo__icon"><?= store_icon('github') ?></div>
            <p class="source-promo__label"><?= h(ui_label('source.code_label', 'Mã nguồn GitHub')) ?></p>
            <h3><?= h(ui_label('source.code_title', 'Mua source để tùy biến nhanh hơn')) ?></h3>
            <?php if ($source_price_label !== ''): ?>
              <p class="source-promo__price"><?= h($source_price_label) ?> <?= h($source_currency) ?></p>
            <?php endif; ?>
            <p><?= h(ui_label('source.code_description', 'Sở hữu liên kết mã nguồn, triển khai phiên bản riêng và tiết kiệm thời gian phát triển sản phẩm.')) ?></p>
            <?php if ($has_paid_github): ?>
              <a class="source-promo__button" href="<?= h($github_url) ?>" target="_blank" rel="noopener noreferrer">
                <?= store_icon('github') ?>
                <span>GitHub</span>
              </a>
            <?php elseif (!empty($paypal_config['enabled']) && $source_price_label !== ''): ?>
              <a class="source-promo__button source-promo__button--paypal" href="<?= h(base_url('paypal-create.php?slug=' . urlencode($app_name))) ?>">
                <?= store_icon('paypal') ?>
                <span><?= h(ui_label('action.buy_source', 'Mua mã nguồn')) ?> <?= h($source_price_label) ?> <?= h($source_currency) ?></span>
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </aside>
    <?php endif; ?>
  </div>
</section>

<div class="app-image-lightbox" data-app-image-lightbox aria-hidden="true">
  <button class="app-image-lightbox__close" type="button" aria-label="<?= h(ui_label('action.close', 'Close')) ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
  </button>
  <img src="" alt="<?= h($app_name) ?> screenshot">
</div>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
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

document.querySelectorAll('[data-app-photo-slider]').forEach(function(slider){
  if (typeof Swiper === 'undefined') {
    return;
  }

  new Swiper(slider, {
    slidesPerView: 1,
    centeredSlides: true,
    spaceBetween: 18,
    grabCursor: true,
    keyboard: {enabled: true},
    pagination: {
      el: slider.querySelector('.app-photo-slider__pagination'),
      clickable: true
    },
    navigation: {
      prevEl: slider.querySelector('.app-photo-slider__nav--prev'),
      nextEl: slider.querySelector('.app-photo-slider__nav--next')
    },
    breakpoints: {
      760: {
        slidesPerView: 1,
        spaceBetween: 22
      },
      1120: {
        slidesPerView: 1,
        spaceBetween: 24
      }
    }
  });
});

var lightbox = document.querySelector('[data-app-image-lightbox]');
if (lightbox) {
  var lightboxImage = lightbox.querySelector('img');
  var closeLightbox = lightbox.querySelector('.app-image-lightbox__close');

  function hideLightbox() {
    lightbox.classList.remove('is-open');
    lightbox.setAttribute('aria-hidden', 'true');
    lightboxImage.setAttribute('src', '');
    document.body.classList.remove('has-lightbox-open');
  }

  document.querySelectorAll('[data-lightbox-image]').forEach(function(button){
    button.addEventListener('click', function(){
      lightboxImage.setAttribute('src', button.getAttribute('data-lightbox-image'));
      lightbox.classList.add('is-open');
      lightbox.setAttribute('aria-hidden', 'false');
      document.body.classList.add('has-lightbox-open');
      closeLightbox.focus();
    });
  });

  closeLightbox.addEventListener('click', hideLightbox);
  lightbox.addEventListener('click', function(event){
    if (event.target === lightbox) {
      hideLightbox();
    }
  });
  document.addEventListener('keydown', function(event){
    if (event.key === 'Escape' && lightbox.classList.contains('is-open')) {
      hideLightbox();
    }
  });
});
</script>

<?php include 'includes/footer.php'; ?>
