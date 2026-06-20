<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

function sitemap_site_url() {
    $scheme = 'http';
    if (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ) {
        $scheme = 'https';
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'home.carrot28.com';
    return $scheme . '://' . $host;
}

function sitemap_url($path) {
    return sitemap_site_url() . base_url($path);
}

function sitemap_xml_url($loc, $lastmod = '', $changefreq = 'weekly', $priority = '0.7') {
    echo "  <url>\n";
    echo '    <loc>' . h($loc) . "</loc>\n";
    if (!empty($lastmod)) {
        $lastmod_time = strtotime($lastmod);
        if ($lastmod_time) {
            echo '    <lastmod>' . h(date('Y-m-d', $lastmod_time)) . "</lastmod>\n";
        }
    }
    echo '    <changefreq>' . h($changefreq) . "</changefreq>\n";
    echo '    <priority>' . h($priority) . "</priority>\n";
    echo "  </url>\n";
}

header('Content-Type: application/xml; charset=utf-8');

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

sitemap_xml_url(sitemap_url(''), '', 'daily', '1.0');

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, created_at FROM app WHERE status != 'trash' ORDER BY priority DESC, created_at DESC");
        foreach ($stmt->fetchAll() as $app) {
            sitemap_xml_url(sitemap_url(urlencode($app['id'])), $app['created_at'], 'weekly', '0.8');
        }

        $page_stmt = $pdo->query("SELECT slug, lang, updated_at, published_at, created_at FROM page WHERE status = 'public' ORDER BY priority DESC, published_at DESC, created_at DESC");
        foreach ($page_stmt->fetchAll() as $page) {
            sitemap_xml_url(sitemap_site_url() . page_url($page['slug'], trim((string)($page['lang'] ?? ''))), $page['updated_at'] ?: ($page['published_at'] ?: $page['created_at']), 'monthly', '0.6');
        }
    } catch (Throwable $e) {
    }
}

echo "</urlset>\n";
