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

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '' && strtolower(trim((string) ($_GET['page'] ?? ''))) === 'app') {
    $slug = trim((string) ($_GET['id'] ?? ''));
}
$slug_candidates = slug_lookup_candidates($slug);
$app = null;
$images = [];
$same_type_apps = [];
$same_category_apps = [];
$app_content_html = '';
$app_content_title = '';
$app_categories = [];
$app_view_count = 0;
$app_rate_summary = ['average' => 0.0, 'count' => 0, 'distribution' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]];
$app_user_rating = 0;
$app_rate_message = '';
$app_rate_success = false;
$error_message = $db_error ?? '';
$paypal_config = paypal_config_from_db($pdo ?? null, 'home');

if (!$slug_candidates) {
    http_response_code(404);
    $page_title = ui_label('meta.app_not_found_title', 'App not found - Carrot28');
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
                unset($query['slug'], $query['page'], $query['id']);
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
    $page_title = ui_label('meta.app_not_found_title', 'App not found - Carrot28');
    $page_description = ui_label('meta.app_not_found_description', 'The requested app was not found.');
    include 'includes/header.php';
    echo '<div class="empty-state"><strong>' . h(ui_label('error.app_not_found_colon', 'Không tìm thấy app:')) . '</strong><br>' . h($slug) . '</div>';
    include 'includes/footer.php';
    exit;
}

if ($error_message) {
    http_response_code(500);
    $page_title = ui_label('meta.database_error_title', 'Database error - Carrot28');
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['app_rate_action'] ?? '') === 'rate_app') {
    $rating = (int) ($_POST['rating'] ?? 0);
    if ($rating < 1 || $rating > 5) {
        $app_rate_message = ui_label('rate.error_required', 'Vui lòng chọn số sao để đánh giá.');
    } else {
        $rateResult = app_rate_submit($pdo ?? null, $app_id, $rating, (string) ($_POST['review_text'] ?? ''));
        $app_rate_message = (string) ($rateResult['message'] ?? '');
        $app_rate_success = !empty($rateResult['success']);
        if ($app_rate_success) {
            header('Location: ' . app_url($app_id) . '?rated=1#app-rate');
            exit;
        }
    }
} elseif (!empty($_GET['rated'])) {
    $app_rate_message = ui_label('rate.success', 'Cảm ơn bạn đã đánh giá.');
    $app_rate_success = true;
}
$app_rate_summary = app_rate_summary($pdo ?? null, $app_id);
$app_user_rating = app_user_rate($pdo ?? null, $app_id);
$page_title = $app_name . ' - Carrot28';
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
        <span class="badge">
          <svg height="18px" width="18px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 511.999 511.999" xml:space="preserve" fill="#000000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path style="fill:#fa7000;" d="M255.999,451.414c-73.102,0-140.198-32.836-194.035-94.955 c-39.99-46.143-58.555-91.935-59.326-93.862L0,255.999l2.639-6.598c0.771-1.927,19.335-47.719,59.326-93.862 c53.837-62.119,120.933-94.955,194.035-94.955S396.197,93.42,450.035,155.54c39.99,46.143,58.555,91.935,59.326,93.862l2.639,6.598 l-2.639,6.598c-0.771,1.927-19.335,47.719-59.326,93.862C396.198,418.58,329.101,451.414,255.999,451.414z"></path> <path style="fill:#f0c399;" d="M509.361,249.402c-0.771-1.927-19.335-47.719-59.326-93.862 C396.198,93.42,329.101,60.585,255.999,60.585v390.83c73.102,0,140.198-32.836,194.035-94.955 c39.99-46.143,58.555-91.935,59.326-93.862l2.639-6.598L509.361,249.402z"></path> <circle style="fill:#c24e00;" cx="256.005" cy="256.005" r="106.59"></circle> <path style="fill:#523800;" d="M255.999,149.409c58.868,0,106.59,47.723,106.59,106.59s-47.722,106.59-106.59,106.59"></path> <path style="fill:#FFFFFF;" d="M238.234,255.999h-35.53c0-29.387,23.908-53.295,53.295-53.295v35.53 C246.204,238.234,238.234,246.204,238.234,255.999z"></path> <path style="fill:#f0c399;" d="M269.202,343.838c-4.31,0.645-8.717,0.987-13.203,0.987c-48.978,0-88.825-39.847-88.825-88.825 s39.847-88.825,88.825-88.825c4.486,0,8.893,0.341,13.203,0.987v-35.815c-4.339-0.461-8.743-0.701-13.203-0.701 c-68.569,0-124.355,55.786-124.355,124.355s55.786,124.355,124.355,124.355c4.46,0,8.864-0.24,13.203-0.701V343.838z"></path> <path style="fill:#c24e00;" d="M255.999,131.644v35.53c48.978,0,88.825,39.847,88.825,88.825s-39.847,88.825-88.825,88.825v35.53 c68.569,0,124.355-55.786,124.355-124.355S324.569,131.644,255.999,131.644z"></path> </g></svg>  &nbsp;
          <?= h(number_format($app_view_count)) ?> <?= h(ui_label('label.views', 'views')) ?>
        </span>
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

      <section class="app-rate-section" id="app-rate" aria-labelledby="app-rate-heading">
        <div class="app-rate-summary">
          <p class="eyebrow"><?= h(ui_label('rate', 'Rate')) ?></p>
          <h3 id="app-rate-heading"><?= h(ui_label('rate.title', 'Ratings and reviews')) ?></h3>
          <div class="app-rate-score">
            <strong><?= h(number_format((float) $app_rate_summary['average'], 1)) ?></strong>
            <span class="app-rate-score__stars" aria-label="<?= h(number_format((float) $app_rate_summary['average'], 1)) ?> / 5">
              <?php for ($star = 1; $star <= 5; $star++): ?>
                <span class="<?= $star <= round((float) $app_rate_summary['average']) ? 'is-filled' : '' ?>">★</span>
              <?php endfor; ?>
            </span>
            <small><?= h(number_format((int) $app_rate_summary['count'])) ?> <?= h(ui_label('rate.count', 'ratings')) ?></small>
          </div>
          <div class="app-rate-bars" aria-hidden="true">
            <?php foreach ([5, 4, 3, 2, 1] as $star): ?>
              <?php
                $starTotal = (int) (($app_rate_summary['distribution'][$star] ?? 0));
                $percent = (int) $app_rate_summary['count'] > 0 ? min(100, round(($starTotal / (int) $app_rate_summary['count']) * 100)) : 0;
              ?>
              <div class="app-rate-bar">
                <span><?= $star ?></span>
                <div><i style="width:<?= (int) $percent ?>%"></i></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <form class="app-rate-form" method="post" action="<?= h(app_url($app_id)) ?>#app-rate">
          <input type="hidden" name="app_rate_action" value="rate_app">
          <h3><?= h(ui_label('rate.your_rate', 'Rate this app')) ?></h3>
          <div class="rate-stars" aria-label="<?= h(ui_label('rate.choose_stars', 'Choose stars')) ?>">
            <?php for ($star = 5; $star >= 1; $star--): ?>
              <input id="app_rate_<?= $star ?>" name="rating" type="radio" value="<?= $star ?>" <?= $app_user_rating === $star ? 'checked' : '' ?>>
              <label for="app_rate_<?= $star ?>" title="<?= $star ?>/5">★</label>
            <?php endfor; ?>
          </div>
          <textarea name="review_text" maxlength="1000" placeholder="<?= h(ui_label('rate.placeholder', 'Share a short review')) ?>"></textarea>
          <?php if ($app_rate_message !== ''): ?>
            <div class="login-alert <?= $app_rate_success ? 'login-alert--success' : 'login-alert--error' ?>"><?= h($app_rate_message) ?></div>
          <?php endif; ?>
          <button class="store-action app-rate-submit" type="submit"><?= h(ui_label('rate.submit', 'Submit rating')) ?></button>
        </form>
      </section>

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
