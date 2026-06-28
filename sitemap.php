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

function sitemap_lang_url($loc, $lang) {
    $separator = strpos($loc, '?') === false ? '?' : '&';
    return $loc . $separator . 'lang=' . rawurlencode((string)$lang);
}

function sitemap_languages($pdo) {
    $fallback = ['en', 'zh', 'vi'];

    if (!$pdo instanceof PDO) {
        return $fallback;
    }

    try {
        $stmt = $pdo->query("SELECT DISTINCT lang_key FROM country WHERE lang_key IS NOT NULL AND lang_key != '' ORDER BY lang_key ASC");
        $languages = [];
        foreach ($stmt->fetchAll() as $row) {
            $lang = trim((string)($row['lang_key'] ?? ''));
            if ($lang !== '') {
                $languages[] = $lang;
            }
        }

        $languages = array_values(array_unique($languages));
        return count($languages) ? $languages : $fallback;
    } catch (Throwable $e) {
        return $fallback;
    }
}

function sitemap_xml_url($loc, $lastmod = '', $changefreq = 'weekly', $priority = '0.7', $languages = []) {
    echo "  <url>\n";
    echo '    <loc>' . h($loc) . "</loc>\n";
    foreach ($languages as $lang) {
        echo '    <xhtml:link rel="alternate" hreflang="' . h($lang) . '" href="' . h(sitemap_lang_url($loc, $lang)) . "\"/>\n";
    }
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

$sitemap_languages = sitemap_languages($pdo ?? null);

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xhtml=\"http://www.w3.org/1999/xhtml\">\n";

sitemap_xml_url(sitemap_url(''), '', 'daily', '1.0');

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, created_at FROM app WHERE status != 'trash' ORDER BY priority DESC, created_at DESC");
        foreach ($stmt->fetchAll() as $app) {
            sitemap_xml_url(sitemap_site_url() . app_url($app['id']), $app['created_at'], 'weekly', '0.8');
        }

        $page_stmt = $pdo->query("
            SELECT slug, MAX(COALESCE(NULLIF(updated_at, ''), created_at)) AS lastmod
            FROM page
            WHERE slug IS NOT NULL AND slug != ''
            GROUP BY slug
            ORDER BY lastmod DESC
        ");
        foreach ($page_stmt->fetchAll() as $page) {
            sitemap_xml_url(sitemap_site_url() . page_url($page['slug']), $page['lastmod'], 'monthly', '0.6', $sitemap_languages);
        }
    } catch (Throwable $e) {
    }
}

echo "</urlset>\n";
