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

function sitemap_xml($value) {
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function sitemap_xml_url($loc, $lastmod = '', $changefreq = 'weekly', $priority = '0.7') {
    static $seen = [];

    if (isset($seen[$loc])) {
        return;
    }
    $seen[$loc] = true;

    echo "  <url>\n";
    echo '    <loc>' . sitemap_xml($loc) . "</loc>\n";
    if (!empty($lastmod)) {
        $lastmod_time = strtotime($lastmod);
        if ($lastmod_time) {
            echo '    <lastmod>' . sitemap_xml(date('Y-m-d', $lastmod_time)) . "</lastmod>\n";
        }
    }
    echo '    <changefreq>' . sitemap_xml($changefreq) . "</changefreq>\n";
    echo '    <priority>' . sitemap_xml($priority) . "</priority>\n";
    echo "  </url>\n";
}

header('Content-Type: application/xml; charset=utf-8');

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

sitemap_xml_url(sitemap_url(''), '', 'daily', '1.0');
sitemap_xml_url(sitemap_url('index.php'), '', 'daily', '0.9');
sitemap_xml_url(sitemap_url('index.php?type=app'), '', 'daily', '0.8');
sitemap_xml_url(sitemap_url('index.php?type=game'), '', 'daily', '0.8');
sitemap_xml_url(sitemap_site_url() . stores_url(), '', 'weekly', '0.7');
sitemap_xml_url(sitemap_site_url() . category_url(), '', 'weekly', '0.7');

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, created_at FROM app WHERE status != 'trash' ORDER BY priority DESC, created_at DESC");
        foreach ($stmt->fetchAll() as $app) {
            sitemap_xml_url(sitemap_site_url() . app_url($app['id']), $app['created_at'], 'weekly', '0.8');
        }

        $page_stmt = $pdo->query("
            SELECT slug, lang, COALESCE(NULLIF(updated_at, ''), created_at) AS lastmod
            FROM page
            WHERE slug IS NOT NULL AND slug != ''
            ORDER BY lastmod DESC
        ");
        foreach ($page_stmt->fetchAll() as $page) {
            sitemap_xml_url(sitemap_site_url() . page_url($page['slug']), $page['lastmod'], 'monthly', '0.6');
            if (trim((string)($page['lang'] ?? '')) !== '') {
                sitemap_xml_url(sitemap_site_url() . page_url($page['slug'], $page['lang']), $page['lastmod'], 'monthly', '0.5');
            }
        }

        $category_stmt = $pdo->query("
            SELECT c.category_id, MAX(a.created_at) AS lastmod
            FROM app_category c
            LEFT JOIN category_app ca ON ca.category_id = c.category_id
            LEFT JOIN app a ON a.id = ca.app_id AND a.status != 'trash'
            WHERE c.category_id IS NOT NULL AND c.category_id != ''
            GROUP BY c.category_id
            ORDER BY c.category_id ASC
        ");
        foreach ($category_stmt->fetchAll() as $category) {
            sitemap_xml_url(sitemap_site_url() . category_url($category['category_id']), $category['lastmod'], 'weekly', '0.7');
        }
    } catch (Throwable $e) {
    }
}

echo "</urlset>\n";
