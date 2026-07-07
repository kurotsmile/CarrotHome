<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/paypal_config.php';
require_once 'includes/visit_tracker.php';

initialize_language_from_ip($pdo ?? null);

visit_track_daily_ip($pdo ?? null);

$slug = trim($_GET['slug'] ?? '');
$slug_candidates = slug_lookup_candidates($slug);
$app = null;
$images = [];
$same_type_apps = [];
$same_category_apps = [];
$app_content_html = '';
$app_content_title = '';
$app_categories = [];
$app_view_count = 0;
$error_message = $db_error ?? '';
$paypal_config = paypal_config_from_db($pdo ?? null, 'home');

if (!$slug_candidates) {
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
        $placeholders = [];
        $order_cases = [];
        $params = [];
        foreach ($slug_candidates as $index => $candidate) {
            $key = ':slug' . $index;
            $order_key = ':slug_order' . $index;
            $placeholders[] = $key;
            $order_cases[] = 'WHEN ' . $order_key . ' THEN ' . $index;
            $params[$key] = $candidate;
            $params[$order_key] = $candidate;
        }

        $stmt = $pdo->prepare("SELECT * FROM app WHERE id IN (" . implode(',', $placeholders) . ") AND status != 'trash' ORDER BY CASE id " . implode(' ', $order_cases) . " ELSE 999 END LIMIT 1");
        $stmt->execute($params);
        $app = $stmt->fetch();
        if ($app) {
            $slug = $app['id'];
            $canonical_path = app_url($slug);
            $request_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
            if ($request_path !== '' && basename($request_path) !== 'app.php' && $request_path !== $canonical_path) {
                $query = $_GET;
                unset($query['slug']);
                header('Location: ' . $canonical_path . (count($query) ? '?' . http_build_query($query) : ''), true, 301);
                exit;
            }

            app_track_view($pdo, (string) $slug);
            $app_view_count = app_view_count($pdo, (string) $slug);

            try {
                $app_categories = fetch_app_category_labels($pdo, $slug, current_lang_key());
            } catch (Throwable $categoryError) {
                $app_categories = [];
            }

            if (count($app_categories)) {
                try {
                    $category_ids = array_values(array_filter(array_unique(array_map(static function ($category) {
                        return trim((string)($category['category_id'] ?? ''));
                    }, $app_categories))));

                    if (count($category_ids)) {
                        $categoryPlaceholders = [];
                        $categoryParams = [
                            ':lang_key' => current_lang_key(),
                            ':slug' => $slug,
                        ];

                        foreach ($category_ids as $index => $category_id) {
                            $key = ':category_id' . $index;
                            $categoryPlaceholders[] = $key;
                            $categoryParams[$key] = $category_id;
                        }

                        $sameCategoryStmt = $pdo->prepare("SELECT DISTINCT a.id, a.id AS slug, a.decription, a.github, a.microsoft_store, a.icon, a.itch, a.exe_file, a.ipa_file, a.deb_file,
                                       a.amazon_app_store, a.huawei_store, a.youtube_link, a.google_play, a.dmg_file, a.uptodown,
                                       a.simmer, a.type, a.apk_file, a.status, a.priority, a.price, a.category, a.created_at,
                                       ac.title AS app_content_title
                                FROM app a
                                INNER JOIN category_app ca ON ca.app_id = a.id
                                LEFT JOIN app_content ac
                                  ON ac.app_id = a.id AND ac.lang_key = :lang_key
                                WHERE ca.category_id IN (" . implode(',', $categoryPlaceholders) . ") AND a.id != :slug AND a.status != 'trash'
                                ORDER BY RAND()
                                LIMIT 8");
                        $sameCategoryStmt->execute($categoryParams);
                        $same_category_apps = $sameCategoryStmt->fetchAll();
                    }
                } catch (Throwable $sameCategoryError) {
                    $same_category_apps = [];
                }
            }

            try {
                $contentStmt = $pdo->prepare('SELECT title, content_html FROM app_content WHERE app_id = :slug AND lang_key = :lang_key LIMIT 1');
                $contentStmt->execute([
                    ':slug' => $slug,
                    ':lang_key' => current_lang_key(),
                ]);
                $content = $contentStmt->fetch();
                if ($content) {
                    $contentTitle = trim((string) ($content['title'] ?? ''));
                    $contentHtml = trim((string) ($content['content_html'] ?? ''));
                    if ($contentTitle !== '') {
                        $app_content_title = $contentTitle;
                        $app['app_content_title'] = $contentTitle;
                    }
                    if ($contentHtml !== '') {
                        $app_content_html = (string) ($content['content_html'] ?? '');
                    }
                }
            } catch (Throwable $contentError) {
                $app_content_html = '';
                $app_content_title = '';
            }

            try {
                $photoStmt = $pdo->prepare('SELECT image_url, display_mode FROM app_photo WHERE app_id = :slug ORDER BY sort_order ASC, id ASC');
                $photoStmt->execute([':slug' => $slug]);
                $images = $photoStmt->fetchAll();
            } catch (Throwable $photoError) {
                $images = [];
            }

            if (!empty($app['type'])) {
                try {
                    $sameTypeStmt = $pdo->prepare("SELECT a.id, a.id AS slug, a.decription, a.github, a.microsoft_store, a.icon, a.itch, a.exe_file, a.ipa_file, a.deb_file,
                                   a.amazon_app_store, a.huawei_store, a.youtube_link, a.google_play, a.dmg_file, a.uptodown,
                                   a.simmer, a.type, a.apk_file, a.status, a.priority, a.price, a.category, a.created_at,
                                   ac.title AS app_content_title
                            FROM app a
                            LEFT JOIN app_content ac
                              ON ac.app_id = a.id AND ac.lang_key = :lang_key
                            WHERE a.type = :type AND a.id != :slug AND a.status != 'trash'
                            ORDER BY RAND()
                            LIMIT 8");
                    $sameTypeStmt->execute([
                        ':lang_key' => current_lang_key(),
                        ':type' => $app['type'],
                        ':slug' => $slug,
                    ]);
                    $same_type_apps = $sameTypeStmt->fetchAll();
                } catch (Throwable $sameTypeError) {
                    $same_type_apps = [];
                }
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

$app_id = (string) ($app['id'] ?? 'App');
$app_name = app_display_title($app);
if ($app_name === '') {
    $app_name = $app_id;
}
$page_title = $app_name . ' - CarrotHome';
$page_description = 'Download ' . $app_name . ' for Android, Windows, macOS, Linux and other platforms.';

$downloads = app_download_links($app);
$stores = app_store_links($app);
$videos = app_video_links($app);
$github_url = trim((string)($app['github'] ?? ''));
$has_paid_github = !empty($_SESSION['paid_github_apps'][$app_id]);
$source_price = trim((string)($app['price'] ?? ''));
if ($source_price === '' || (float)$source_price <= 0) {
    $source_price = trim((string)($paypal_config['amount'] ?? ''));
}
$source_price_label = $source_price !== '' ? number_format((float)$source_price, 2, '.', '') : '';
$source_currency = trim((string)($paypal_config['currency'] ?? 'USD'));
$slider_style_version = file_exists(__DIR__ . '/responsive-lightbox-slider-web/css/style.css') ? filemtime(__DIR__ . '/responsive-lightbox-slider-web/css/style.css') : time();
$slider_script_version = file_exists(__DIR__ . '/responsive-lightbox-slider-web/js/imagesSlider.js') ? filemtime(__DIR__ . '/responsive-lightbox-slider-web/js/imagesSlider.js') : time();
$extra_head = '<link rel="stylesheet" href="' . h(base_url('responsive-lightbox-slider-web/css/style.css')) . '?v=' . $slider_style_version . '">' . "\n"
    . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css">' . "\n";

include 'includes/header.php';
?>

<section class="app-detail">
  <div class="app-detail-hero">
    <div class="app-detail-icon">
      <img src="<?= h(app_icon($app['icon'] ?? '')) ?>" alt="<?= h($app_name) ?> icon" loading="lazy">
    </div>

    <div class="app-detail-info">
      <h2><?= h($app_name) ?></h2>
      <div class="app-meta">
        <span class="app-id-meta"><?= h($app_id) ?></span>
        <?php if (!empty($app['type'])): ?>
          <a class="badge" href="<?= h(base_url('index.php')) ?>?type=<?= h(urlencode((string) $app['type'])) ?>"><?= h($app['type']) ?></a>
        <?php endif; ?>
        <?php foreach ($app_categories as $category): ?>
          <a class="badge" href="<?= h(category_url($category['category_id'] ?? '')) ?>"><?= h(category_display_title($category)) ?></a>
        <?php endforeach; ?>
        <?php if (!empty($app['created_at'])): ?>
          <?php $createdDate = date('Y-m-d', strtotime((string)$app['created_at'])); ?>
          <span class="badge"><?= h($createdDate) ?></span>
        <?php endif; ?>
        <span class="badge"><?= h(number_format($app_view_count)) ?> <?= h(ui_label('label.views', 'views')) ?></span>
      </div>
      <div class="app-hero-actions">
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
        <section class="useSliderPlugin imagesSec">
          <?php foreach (array_values($images) as $image): ?>
            <?php $imageUrl = trim((string) ($image['image_url'] ?? '')); ?>
            <?php if ($imageUrl !== ''): ?>
              <?php $displayMode = (($image['display_mode'] ?? 'vertical') === 'horizontal') ? 'horizontal' : 'vertical'; ?>
              <img class="<?= $displayMode === 'horizontal' ? 'app-photo-horizontal' : 'app-photo-vertical' ?>" src="<?= h(asset_url($imageUrl)) ?>" alt="<?= h($app_name) ?> screenshot" loading="lazy">
            <?php endif; ?>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>

      <?php if ($app_content_html !== '' || !empty($app['decription'])): ?>
        <div class="app-description">
          <?= $app_content_html !== '' ? $app_content_html : nl2br(h($app['decription'])) ?>
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
              <a class="source-promo__button source-promo__button--paypal" href="<?= h(base_url('paypal-create.php?slug=' . urlencode($app_id))) ?>">
                <?= store_icon('paypal') ?>
                <span><?= h(ui_label('action.buy_source', 'Mua mã nguồn')) ?> <?= h($source_price_label) ?> <?= h($source_currency) ?></span>
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </aside>
    <?php endif; ?>
  </div>

  <?php if (count($same_type_apps)): ?>
    <section class="same-type-section" aria-labelledby="same-type-heading">
      <div class="same-type-header">
        <h3 id="same-type-heading"><?= h(ui_label('section.same_type', 'Same Type')) ?></h3>
        <a href="<?= h(base_url('index.php')) ?>?type=<?= urlencode($app['type'] ?? '') ?>"><?= h(ui_label('action.view_all', 'View all')) ?></a>
      </div>
      <ol class="same-type-slider">
        <?php foreach ($same_type_apps as $same_app): ?>
          <?php
            $same_name = app_display_title($same_app);
            $same_slug = $same_app['slug'] ?? $same_app['id'];
            $same_icon = app_card_icon($same_app);
            $same_downloads = app_download_links($same_app);
          ?>
          <li class="same-type-item">
            <div class="same-type-card">
              <figure class="same-type-image">
                <img src="<?= h($same_icon) ?>" alt="<?= h($same_name) ?>" loading="lazy">
              </figure>
              <a class="same-type-link" href="<?= h(app_url($same_slug)) ?>" aria-label="<?= h(ui_label('aria.view_app', 'View')) ?> <?= h($same_name) ?>"></a>
              <div class="same-type-overlay">
                <?php foreach ($same_downloads as $key => $url): ?>
                  <a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer" class="download-chip" aria-label="<?= h(label_name($key)) ?>" title="<?= h(label_name($key)) ?>">
                    <?= download_icon($key) ?>
                    <span><?= h(short_label($key)) ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="same-type-meta">
              <?= type_icon($same_app['type'] ?? 'app') ?>
              <span><?= h($same_name) ?></span>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  <?php endif; ?>

  <?php if (count($same_category_apps)): ?>
    <?php $primary_category = $app_categories[0] ?? null; ?>
    <section class="same-type-section" aria-labelledby="same-category-heading">
      <div class="same-type-header">
        <h3 id="same-category-heading"><?= h(ui_label('section.same_category', 'Cùng chủ đề')) ?></h3>
        <?php if ($primary_category): ?>
          <a href="<?= h(category_url($primary_category['category_id'] ?? '')) ?>"><?= h(ui_label('action.view_all', 'View all')) ?></a>
        <?php endif; ?>
      </div>
      <ol class="same-type-slider">
        <?php foreach ($same_category_apps as $same_app): ?>
          <?php
            $same_name = app_display_title($same_app);
            $same_slug = $same_app['slug'] ?? $same_app['id'];
            $same_icon = app_card_icon($same_app);
            $same_downloads = app_download_links($same_app);
          ?>
          <li class="same-type-item">
            <div class="same-type-card">
              <figure class="same-type-image">
                <img src="<?= h($same_icon) ?>" alt="<?= h($same_name) ?>" loading="lazy">
              </figure>
              <a class="same-type-link" href="<?= h(app_url($same_slug)) ?>" aria-label="<?= h(ui_label('aria.view_app', 'View')) ?> <?= h($same_name) ?>"></a>
              <div class="same-type-overlay">
                <?php foreach ($same_downloads as $key => $url): ?>
                  <a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer" class="download-chip" aria-label="<?= h(label_name($key)) ?>" title="<?= h(label_name($key)) ?>">
                    <?= download_icon($key) ?>
                    <span><?= h(short_label($key)) ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="same-type-meta">
              <?= type_icon($same_app['type'] ?? 'app') ?>
              <span><?= h($same_name) ?></span>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  <?php endif; ?>
</section>

<script src="<?= h(base_url('responsive-lightbox-slider-web/js/imagesSlider.js')) ?>?v=<?= (int) $slider_script_version ?>"></script>

<?php include 'includes/footer.php'; ?>
