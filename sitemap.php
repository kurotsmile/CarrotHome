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
            if (preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/i', $lang)) {
                $languages[] = $lang;
            }
        }

        $languages = array_values(array_unique($languages));
        return count($languages) ? $languages : $fallback;
    } catch (Throwable $e) {
        return $fallback;
    }
}

function sitemap_xml_url($loc, $lastmod = '', $changefreq = 'weekly', $priority = '0.7', $alternates = []) {
    echo "  <url>\n";
    echo '    <loc>' . sitemap_xml($loc) . "</loc>\n";
    foreach ($alternates as $lang => $href) {
        echo "    <xhtml:link\n";
        echo "        rel=\"alternate\"\n";
        echo '        hreflang="' . sitemap_xml($lang) . "\"\n";
        echo '        href="' . sitemap_xml($href) . "\"/>\n";
    }
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

function sitemap_page_alternates($base_loc, $languages) {
    $alternates = [
        'x-default' => $base_loc,
    ];

    foreach ($languages as $lang) {
        $lang = trim((string)$lang);
        if (preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/i', $lang)) {
            $alternates[$lang] = sitemap_lang_url($base_loc, $lang);
        }
    }

    return $alternates;
}

function sitemap_valid_languages($languages) {
    $valid = [];

    foreach ($languages as $lang) {
        $lang = trim((string)$lang);
        if (preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/i', $lang)) {
            $valid[] = $lang;
        }
    }

    return array_values(array_unique($valid));
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
            SELECT
                slug,
                GROUP_CONCAT(DISTINCT NULLIF(lang, '') ORDER BY lang ASC) AS languages,
                MAX(COALESCE(NULLIF(updated_at, ''), created_at)) AS lastmod
            FROM page
            WHERE slug IS NOT NULL AND slug != ''
            GROUP BY slug
            ORDER BY lastmod DESC
        ");
        foreach ($page_stmt->fetchAll() as $page) {
            $base_loc = sitemap_site_url() . page_url($page['slug']);
            $page_languages = array_filter(array_map('trim', explode(',', (string)($page['languages'] ?? ''))));
            if (!$page_languages) {
                $page_languages = $sitemap_languages;
            }
            $page_languages = sitemap_valid_languages($page_languages);

            $alternates = sitemap_page_alternates($base_loc, $page_languages);
            sitemap_xml_url($base_loc, $page['lastmod'], 'monthly', '0.6', $alternates);

            foreach ($page_languages as $lang) {
                sitemap_xml_url(sitemap_lang_url($base_loc, $lang), $page['lastmod'], 'monthly', '0.6', $alternates);
            }
        }
    } catch (Throwable $e) {
    }
}

echo "</urlset>\n";
