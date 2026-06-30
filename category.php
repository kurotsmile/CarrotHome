<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/visit_tracker.php';

initialize_language_from_ip($pdo ?? null);
visit_track_daily_ip($pdo ?? null);

$lang_key = current_lang_key();
$selected_category_id = trim(rawurldecode((string)($_GET['category'] ?? '')));
$selected_category_candidates = slug_lookup_candidates($selected_category_id);
$categories = [];
$apps = [];
$category_detail = null;
$error_message = $db_error ?? '';

if ($pdo) {
    try {
        if ($selected_category_id === '') {
            $stmt = $pdo->prepare('
                SELECT c.category_id, c.icon,
                       COALESCE(NULLIF(cc_lang.title, ""), NULLIF(cc_en.title, ""), c.category_id) AS title,
                       COALESCE(NULLIF(cc_lang.description, ""), NULLIF(cc_en.description, ""), "") AS description,
                       COUNT(DISTINCT ca.app_id) AS app_count
                FROM app_category c
                LEFT JOIN app_category_content cc_lang
                  ON cc_lang.category_id = c.category_id AND cc_lang.key_lang = :lang_key
                LEFT JOIN app_category_content cc_en
                  ON cc_en.category_id = c.category_id AND cc_en.key_lang = "en"
                LEFT JOIN category_app ca ON ca.category_id = c.category_id
                GROUP BY c.category_id, c.icon, cc_lang.title, cc_lang.description, cc_en.title, cc_en.description
                ORDER BY title ASC, c.category_id ASC
            ');
            $stmt->execute([':lang_key' => $lang_key]);
            $categories = $stmt->fetchAll();
        } else {
            $categoryPlaceholders = [];
            $categoryOrderCases = [];
            $categoryParams = [
                ':lang_key' => $lang_key,
            ];
            foreach ($selected_category_candidates as $index => $candidate) {
                $key = ':category_id' . $index;
                $orderKey = ':category_order_id' . $index;
                $categoryPlaceholders[] = $key;
                $categoryOrderCases[] = 'WHEN ' . $orderKey . ' THEN ' . $index;
                $categoryParams[$key] = $candidate;
                $categoryParams[$orderKey] = $candidate;
            }

            $categoryStmt = $pdo->prepare('
                SELECT c.category_id, c.icon,
                       COALESCE(NULLIF(cc_lang.title, ""), NULLIF(cc_en.title, ""), c.category_id) AS title,
                       COALESCE(NULLIF(cc_lang.description, ""), NULLIF(cc_en.description, ""), "") AS description
                FROM app_category c
                LEFT JOIN app_category_content cc_lang
                  ON cc_lang.category_id = c.category_id AND cc_lang.key_lang = :lang_key
                LEFT JOIN app_category_content cc_en
                  ON cc_en.category_id = c.category_id AND cc_en.key_lang = "en"
                WHERE c.category_id IN (' . implode(',', $categoryPlaceholders) . ')
                ORDER BY CASE c.category_id ' . implode(' ', $categoryOrderCases) . ' ELSE 999 END
                LIMIT 1
            ');
            $categoryStmt->execute($categoryParams);
            $category_detail = $categoryStmt->fetch() ?: [
                'category_id' => $selected_category_id,
                'icon' => '',
                'title' => $selected_category_id,
                'description' => '',
            ];
            $resolved_category_id = (string)($category_detail['category_id'] ?? $selected_category_id);

            $appStmt = $pdo->prepare("
                SELECT a.id, a.id AS slug, a.decription, a.github, a.microsoft_store, a.icon, a.itch, a.exe_file, a.ipa_file, a.deb_file,
                       a.amazon_app_store, a.huawei_store, a.youtube_link, a.google_play, a.dmg_file, a.uptodown,
                       a.simmer, a.type, a.apk_file, a.status, a.priority, a.price, a.category, a.created_at,
                       ac.title AS app_content_title
                FROM app a
                INNER JOIN category_app ca ON ca.app_id = a.id
                LEFT JOIN app_content ac
                  ON ac.app_id = a.id AND ac.lang_key = :lang_key
                WHERE ca.category_id = :category_id AND a.status != 'trash'
                ORDER BY a.priority DESC, a.created_at DESC, a.id ASC
                LIMIT 120
            ");
            $appStmt->execute([
                ':lang_key' => $lang_key,
                ':category_id' => $resolved_category_id,
            ]);
            $apps = $appStmt->fetchAll();
        }
    } catch (Throwable $e) {
        $error_message = $e->getMessage();
    }
}

$page_title = $selected_category_id === ''
    ? ui_label('meta.category_title', 'Category - CarrotHome')
    : category_display_title($category_detail) . ' - CarrotHome';
$page_description = $selected_category_id === ''
    ? ui_label('meta.category_description', 'Browse app and game categories.')
    : (trim((string)($category_detail['description'] ?? '')) ?: $page_title);

include __DIR__ . '/includes/header.php';
?>

<section class="category-page">
  <div class="category-page__header">
    <p class="eyebrow"><?= h(ui_label('nav.category', 'Category')) ?></p>
    <h2><?= h($selected_category_id === '' ? ui_label('category.heading', 'Category') : category_display_title($category_detail)) ?></h2>
    <?php if ($selected_category_id !== '' && trim((string)($category_detail['description'] ?? '')) !== ''): ?>
      <p><?= h($category_detail['description']) ?></p>
    <?php endif; ?>
  </div>

  <?php if ($error_message): ?>
    <div class="empty-state">
      <strong><?= h(ui_label('error.mysql_connection', 'Lỗi kết nối MySQL:')) ?></strong><br>
      <?= h($error_message) ?>
    </div>
  <?php endif; ?>

  <?php if (!$error_message && $selected_category_id === ''): ?>
    <?php if (!count($categories)): ?>
      <p class="empty-state"><?= h(ui_label('empty.no_categories', 'Chưa có chuyên mục.')) ?></p>
    <?php endif; ?>
    <ol class="category-grid">
      <?php foreach ($categories as $category): ?>
        <li class="category-card">
          <a href="<?= h(category_url($category['category_id'])) ?>">
            <figure>
              <img src="<?= h(app_icon($category['icon'] ?? '')) ?>" alt="<?= h(category_display_title($category)) ?>" loading="lazy">
            </figure>
            <div>
              <h3><?= h(category_display_title($category)) ?></h3>
              <?php if (trim((string)($category['description'] ?? '')) !== ''): ?>
                <p><?= h($category['description']) ?></p>
              <?php endif; ?>
              <span><?= (int)($category['app_count'] ?? 0) ?> <?= h(ui_label('label.apps', 'apps')) ?></span>
            </div>
          </a>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>

  <?php if (!$error_message && $selected_category_id !== ''): ?>
    <div class="result-line"><?= h(count($apps)) ?> <?= h(ui_label('label.apps', 'apps')) ?></div>
    <?php if (!count($apps)): ?>
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
              <a class="badge-link" href="index.php?type=<?= urlencode($app['type'] ?? 'app') ?>"><?= h($app['type'] ?? 'app') ?></a>
            </div>
            <?php if ($primary_store): ?>
              <a class="store-link" href="<?= h($primary_store) ?>" target="_blank" rel="noopener noreferrer"><?= h(ui_label('action.store', 'Store')) ?></a>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
