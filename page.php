<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/visit_tracker.php';

visit_track_daily_ip($pdo ?? null);

$slug = trim($_GET['page'] ?? ($_GET['slug'] ?? ''));
$page_lang = trim($_GET['lang'] ?? ($_SESSION['key_lang'] ?? 'vi'));
$page = null;
$error_message = $db_error ?? '';

if ($slug === '') {
    http_response_code(404);
    $page_title = 'Page not found - CarrotHome';
    $page_description = 'The requested page was not found.';
    include __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><strong>Không tìm thấy page.</strong><br>Thiếu tham số <code>slug</code>.</div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

if ($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM page
            WHERE slug = :slug
              AND status = 'public'
              AND (lang = :lang_filter OR lang = '' OR lang IS NULL)
            ORDER BY CASE WHEN lang = :lang_order THEN 0 ELSE 1 END
            LIMIT 1
        ");
        $stmt->execute([
            ':slug' => $slug,
            ':lang_filter' => $page_lang,
            ':lang_order' => $page_lang,
        ]);
        $page = $stmt->fetch();
    } catch (Throwable $e) {
        $error_message = $e->getMessage();
    }
}

if (!$page && !$error_message) {
    http_response_code(404);
    $page_title = 'Page not found - CarrotHome';
    $page_description = 'The requested page was not found.';
    include __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><strong>Không tìm thấy page:</strong><br>' . h($slug) . '</div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

if ($error_message) {
    http_response_code(500);
    $page_title = 'Database error - CarrotHome';
    $page_description = 'Database connection error.';
    include __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><strong>Lỗi MySQL:</strong><br>' . h($error_message) . '</div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$page_title = ($page['seo_title'] ?: $page['title']) . ' - CarrotHome';
$page_description = $page['seo_description'] ?: $page['title'];
$page_lang = $page['lang'] ?: $page_lang;

include __DIR__ . '/includes/header.php';
?>

<article class="content-page">
  <header class="content-page__header">
    <h2><?= h($page['title']) ?></h2>
  </header>

  <div class="content-page__body">
    <?= $page['content_html'] ?>
  </div>
</article>

<?php include __DIR__ . '/includes/footer.php'; ?>
