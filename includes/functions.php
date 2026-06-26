<?php
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function initialize_language_from_ip($pdo = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (trim((string)($_SESSION['key_lang'] ?? '')) !== '') {
        return;
    }

    if (!$pdo instanceof PDO) {
        return;
    }

    if (!empty($_SESSION['auto_lang_checked'])) {
        return;
    }

    $_SESSION['auto_lang_checked'] = 1;

    $country_code = '';
    $header_keys = [
        'HTTP_CF_IPCOUNTRY',
        'HTTP_X_VERCEL_IP_COUNTRY',
        'HTTP_CLOUDFRONT_VIEWER_COUNTRY',
        'HTTP_X_APPENGINE_COUNTRY',
        'GEOIP_COUNTRY_CODE',
    ];

    foreach ($header_keys as $key) {
        $value = strtoupper(trim((string)($_SERVER[$key] ?? '')));
        if (preg_match('/^[A-Z]{2}$/', $value) && $value !== 'XX') {
            $country_code = $value;
            break;
        }
    }

    if ($country_code === '') {
        $ip = '';
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        ];

        foreach ($ip_keys as $key) {
            $value = trim((string)($_SERVER[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            foreach (explode(',', $value) as $part) {
                $candidate_ip = trim($part);
                if (filter_var($candidate_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $ip = $candidate_ip;
                    break 2;
                }
            }
        }

        if ($ip !== '') {
            $url = 'https://ipapi.co/' . rawurlencode($ip) . '/country/';
            $response = '';

            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 1,
                    CURLOPT_TIMEOUT => 2,
                    CURLOPT_USERAGENT => 'CarrotHome/1.0',
                ]);
                $response = (string)curl_exec($ch);
                $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($status >= 400) {
                    $response = '';
                }
            } elseif (ini_get('allow_url_fopen')) {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 2,
                        'header' => "User-Agent: CarrotHome/1.0\r\n",
                    ],
                ]);
                $response = (string)@file_get_contents($url, false, $context);
            }

            $country_code = strtoupper(trim($response));
            if (!preg_match('/^[A-Z]{2}$/', $country_code)) {
                $country_code = '';
            }
        }
    }

    $country_code = strtolower(trim((string)$country_code));
    if ($country_code === '') {
        return;
    }

    $stmt = $pdo->prepare('SELECT id, lang_key, lang_country FROM country WHERE LOWER(lang_country) = :country_code LIMIT 1');
    $stmt->execute([':country_code' => $country_code]);
    $country = $stmt->fetch();
    if (!$country) {
        $country_lang_map = [
            'ae' => 'ar', 'br' => 'pt', 'cn' => 'zh', 'de' => 'de', 'es' => 'es',
            'fr' => 'fr', 'gb' => 'en', 'id' => 'id', 'in' => 'hi', 'it' => 'it',
            'jp' => 'ja', 'kr' => 'ko', 'mx' => 'es', 'nl' => 'nl', 'ph' => 'en',
            'pt' => 'pt', 'ru' => 'ru', 'sa' => 'ar', 'th' => 'th', 'tr' => 'tr',
            'tw' => 'zh', 'us' => 'en', 'vn' => 'vi',
        ];
        $lang_key = $country_lang_map[$country_code] ?? '';

        if ($lang_key !== '') {
            $stmt = $pdo->prepare('SELECT id, lang_key, lang_country FROM country WHERE lang_key = :lang_key ORDER BY id ASC LIMIT 1');
            $stmt->execute([':lang_key' => $lang_key]);
            $country = $stmt->fetch();
        }
    }

    if (!$country) {
        return;
    }

    $_SESSION['key_lang'] = $country['lang_key'];
    $_SESSION['country_id'] = (int)$country['id'];
    $_SESSION['lang_country'] = $country['lang_country'];
}

function current_lang_key() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    initialize_language_from_ip($GLOBALS['pdo'] ?? null);

    return trim((string)($_SESSION['key_lang'] ?? 'en')) ?: 'en';
}

function ui_label($key, $default, $lang_key = null) {
    static $cache = [];

    $key = trim((string)$key);
    $default = (string)$default;
    $lang_key = trim((string)($lang_key ?? current_lang_key())) ?: 'en';

    if ($key === '') {
        return $default;
    }

    $cache_key = $lang_key . "\n" . $key;
    if (array_key_exists($cache_key, $cache)) {
        return $cache[$cache_key];
    }

    $cache[$cache_key] = $default;

    if (!isset($GLOBALS['pdo']) || !$GLOBALS['pdo'] instanceof PDO) {
        return $cache[$cache_key];
    }

    try {
        $stmt = $GLOBALS['pdo']->prepare('SELECT value FROM text_label WHERE `key` = :label_key AND lang_key = :lang_key LIMIT 1');
        $stmt->execute([
            ':label_key' => $key,
            ':lang_key' => $lang_key,
        ]);
        $value = $stmt->fetchColumn();

        if ($value !== false && trim((string)$value) !== '') {
            $cache[$cache_key] = (string)$value;
        }
    } catch (Throwable $e) {
        $cache[$cache_key] = $default;
    }

    return $cache[$cache_key];
}

function base_url($path = '') {
    $path = ltrim((string)$path, '/');
    return '/' . $path;
}

function seo_slug_text($slug) {
    $slug = trim((string)$slug);
    return preg_replace('/\s+/u', '-', $slug);
}

function seo_slug_encode($slug) {
    return rawurlencode(seo_slug_text($slug));
}

function slug_lookup_candidates($slug) {
    $decoded = trim(rawurldecode((string)$slug));
    $candidates = [$decoded];

    if (strpos($decoded, '+') !== false) {
        $candidates[] = str_replace('+', ' ', $decoded);
    }

    if (strpos($decoded, '-') !== false) {
        $candidates[] = str_replace('-', ' ', $decoded);
    }

    return array_values(array_unique(array_filter($candidates, static function ($value) {
        return trim((string)$value) !== '';
    })));
}

function app_url($slug) {
    return base_url(seo_slug_encode($slug));
}

function page_url($slug, $lang = '') {
    $url = base_url(seo_slug_encode($slug));
    if ((string)$lang !== '') {
        $url .= '?lang=' . rawurlencode((string)$lang);
    }
    return $url;
}

function app_icon($icon) {
    return asset_url(!empty($icon) ? $icon : 'images/logo_carrot.png');
}

function asset_url($path) {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    if (substr($path, 0, 3) === 'r2:') {
        return 'https://json-worker.tranthienthanh93.workers.dev/get_file?file=' . rawurlencode(substr($path, 3));
    }
    return base_url($path);
}

function country_icon_html($icon) {
    $icon = trim((string)$icon);
    if ($icon === '') {
        return '<span class="language-menu__emoji" aria-hidden="true">🌐</span>';
    }
    if (preg_match('#^(https?://|/|r2:)#i', $icon)) {
        return '<img src="' . h(asset_url($icon)) . '" alt="" loading="lazy">';
    }
    return '<span class="language-menu__emoji" aria-hidden="true">' . h($icon) . '</span>';
}

function json_array($json) {
    if (!$json) return [];
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function first_image($app) {
    $images = json_array($app['images'] ?? '');
    foreach (array_values($images) as $url) {
        if (!empty($url)) return $url;
    }
    return $app['icon'] ?? '';
}

function active_links($json) {
    $items = json_array($json);
    $links = [];
    foreach ($items as $key => $url) {
        if (!empty($url)) $links[$key] = $url;
    }
    return $links;
}

function app_download_links($app) {
    return active_column_links($app, ['apk_file', 'exe_file', 'deb_file', 'dmg_file', 'ipa_file']);
}

function app_store_links($app) {
    return active_column_links($app, ['google_play', 'microsoft_store', 'amazon_app_store', 'itch', 'uptodown', 'huawei_store', 'simmer']);
}

function app_video_links($app) {
    return active_column_links($app, ['youtube_link']);
}

function active_column_links($app, $keys) {
    $links = [];
    foreach ($keys as $key) {
        if (!empty($app[$key])) {
            $links[$key] = $app[$key];
        }
    }
    return $links;
}

function label_name($key) {
    $labels = [
        'apk_file' => ['download.apk', 'APK'],
        'exe_file' => ['download.windows', 'Windows'],
        'deb_file' => ['download.linux_deb', 'Linux DEB'],
        'dmg_file' => ['download.macos', 'macOS'],
        'ipa_file' => ['download.ios', 'iOS'],
        'google_play' => ['store.google_play', 'Google Play'],
        'amazon_app_store' => ['store.amazon', 'Amazon'],
        'microsoft_store' => ['store.microsoft', 'Microsoft'],
        'itch' => ['store.itch', 'Itch.io'],
        'uptodown' => ['store.uptodown', 'Uptodown'],
        'huawei_store' => ['store.huawei', 'Huawei'],
        'simmer' => ['store.simmer', 'Simmer'],
        'youtube_link' => ['store.youtube', 'YouTube'],
    ];
    if (isset($labels[$key])) {
        return ui_label($labels[$key][0], $labels[$key][1]);
    }

    return ui_label('label.' . preg_replace('/[^a-z0-9._-]+/', '_', (string)$key), ucwords(str_replace('_', ' ', (string)$key)));
}

function short_label($key) {
    $labels = [
        'apk_file' => 'APK',
        'exe_file' => 'EXE',
        'deb_file' => 'DEB',
        'dmg_file' => 'DMG',
        'ipa_file' => 'IPA',
    ];
    return $labels[$key] ?? label_name($key);
}

function download_icon($key) {
    $icons = [
        'apk_file' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 7h8a3 3 0 0 1 3 3v4a5 5 0 0 1-5 5h-4a5 5 0 0 1-5-5v-4a3 3 0 0 1 3-3Zm1-4 1.4 2.4M15 3l-1.4 2.4M9 11h.01M15 11h.01" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'exe_file' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H4V5Zm6 15h4m-6 0h8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'deb_file' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Zm0 9 8-4.5M12 12 4 7.5M12 12v9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'dmg_file' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4c-2 1.6-3 3.4-3 5.4 0 1.9 1.1 3.6 3 5.1 1.9-1.5 3-3.2 3-5.1 0-2-1-3.8-3-5.4Zm0 11v5m-3-3h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'ipa_file' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3h8a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm3 15h2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    ];
    return $icons[$key] ?? '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v10m0 0 4-4m-4 4-4-4M5 20h14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

function store_icon($key) {
    $icons = [
        'google_play' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3.8v16.4l9.3-8.2L5 3.8Zm10.8 6.9 2.2-1.2c1.2-.7 1.2-2.3 0-3L8 1.1l7.8 9.6Zm0 2.6L8 22.9l10-5.4c1.2-.7 1.2-2.3 0-3l-2.2-1.2Z" fill="currentColor"/></svg>',
        'microsoft_store' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h7.2v7.2H4V4Zm8.8 0H20v7.2h-7.2V4ZM4 12.8h7.2V20H4v-7.2Zm8.8 0H20V20h-7.2v-7.2Z" fill="currentColor"/></svg>',
        'amazon_app_store' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 16.8c3.1 1.9 7 1.9 10.2 0 .5-.3 1 .4.5.8-3.6 3.2-9.3 3.1-12.7-.1-.4-.4.1-1 .6-.7H7Zm10.8-2.4c-.4.3-.9 0-.8-.5l.4-1.7c-.9.9-2 1.4-3.3 1.4-2.4 0-4.1-1.4-4.1-3.5 0-2.4 1.9-3.8 5.1-3.8h2.1v-.5c0-1.1-.8-1.8-2.3-1.8-1.1 0-2.1.3-3.2.9-.5.3-1.1-.1-1.1-.7V3.5c0-.3.2-.6.5-.7 1.3-.6 2.8-.8 4.3-.8 3.4 0 5.3 1.6 5.3 4.5v6.8c0 .4-.2.7-.5.9l-2.4 1.2Zm-.6-5.9h-1.7c-1.5 0-2.4.5-2.4 1.5 0 .8.7 1.3 1.7 1.3 1.4 0 2.4-1 2.4-2.4v-.4Z" fill="currentColor"/></svg>',
        'itch' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.6 4h14.8L21 8.8v2.1c0 1.1-.8 2-1.9 2.1v5.5c0 .8-.7 1.5-1.5 1.5H6.4c-.8 0-1.5-.7-1.5-1.5V13A2.1 2.1 0 0 1 3 10.9V8.8L4.6 4Zm5 8.9c.9 0 1.6-.6 2-1.3.4.8 1.1 1.3 2 1.3s1.6-.5 2-1.3c.4.8 1.1 1.3 2 1.3.2 0 .4 0 .6-.1v5.4H5.8v-5.4c.2.1.4.1.6.1.9 0 1.6-.5 2-1.3.3.8 1.1 1.3 2 1.3Z" fill="currentColor"/></svg>',
        'github' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-3.2 19.5c.5.1.7-.2.7-.5v-1.8c-2.9.6-3.5-1.2-3.5-1.2-.5-1.1-1.1-1.4-1.1-1.4-.9-.6.1-.6.1-.6 1 0 1.6 1.1 1.6 1.1.9 1.6 2.5 1.1 3 .8.1-.7.4-1.1.7-1.3-2.3-.3-4.8-1.2-4.8-5A3.9 3.9 0 0 1 6.6 9c-.1-.3-.5-1.3.1-2.7 0 0 .8-.3 2.8 1a9.6 9.6 0 0 1 5 0c1.9-1.3 2.8-1 2.8-1 .6 1.4.2 2.4.1 2.7a3.9 3.9 0 0 1 1.1 2.7c0 3.9-2.4 4.8-4.8 5 .4.3.7 1 .7 2V21c0 .3.2.6.8.5A10 10 0 0 0 12 2Z" fill="currentColor"/></svg>',
        'paypal' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.1 21H4.5L7.8 3h7.1c3.2 0 5.2 1.8 4.7 4.8-.5 3.5-3 5.4-6.7 5.4h-2.2L10 17H7.3l.8 4Zm3.2-11h2c1.8 0 2.9-.8 3.2-2.3.2-1.3-.6-2-2.2-2h-2.4L11.3 10Z" fill="currentColor"/></svg>',
    ];
    return $icons[$key] ?? '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8h12l-1 12H7L6 8Zm3-2a3 3 0 0 1 6 0v2H9V6Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

function first_active_link($links) {
    foreach ($links as $url) {
        if (!empty($url)) return $url;
    }
    return '';
}

function app_card_icon($app) {
    $icons = json_array($app['icons'] ?? '');
    $candidates = array_values(array_filter($icons));
    if (count($candidates) > 0) {
        return asset_url($candidates[array_rand($candidates)]);
    }
    return app_icon($app['icon'] ?? '');
}

function type_icon($type) {
    if ($type === 'game') {
        return '<svg class="type-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path opacity=".35" d="M22 11.07v5.58A5.36 5.36 0 0 1 16.65 22h-9.3A5.36 5.36 0 0 1 2 16.65v-5.58a5.36 5.36 0 0 1 5.35-5.35h9.3A5.36 5.36 0 0 1 22 11.07Z" fill="currentColor"/><path d="m10.13 15.01-1.03-1.03.99-.99a.75.75 0 0 0-1.06-1.06l-.99.99-.96-.96a.75.75 0 1 0-1.06 1.06l.96.96-.99.99a.75.75 0 1 0 1.06 1.06l.99-.99 1.03 1.03a.75.75 0 1 0 1.06-1.06ZM13.54 15a1 1 0 1 1 0-2 1 1 0 0 1 0 2ZM17.48 15a1 1 0 1 1 0-2 1 1 0 0 1 0 2ZM15.5 16.97a1 1 0 0 1-1-1v-.01a1 1 0 1 1 1 1.01ZM15.5 13.03a1 1 0 0 1-1-1v-.01a1 1 0 1 1 1 1.01Z" fill="currentColor"/></svg>';
    }

    return '<svg class="type-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path opacity=".35" d="M16.19 2H7.82C4.18 2 2.01 4.17 2.01 7.81v8.37c0 3.64 2.17 5.81 5.81 5.81h8.37c3.64 0 5.81-2.17 5.81-5.81V7.81C22 4.17 19.83 2 16.19 2Z" fill="currentColor"/><path d="M10.75 14.15h2.51a.9.9 0 0 0 .9-.9v-2.51a.9.9 0 0 0-.9-.9h-2.51a.9.9 0 0 0-.9.9v2.51c0 .5.4.9.9.9ZM7.8 18a1.8 1.8 0 0 0 1.8-1.8v-.8a1 1 0 0 0-1-1h-.8A1.8 1.8 0 1 0 7.8 18ZM7.8 9.6h.8a1 1 0 0 0 1-1v-.8A1.8 1.8 0 1 0 7.8 9.6ZM15.4 9.6h.8A1.8 1.8 0 1 0 14.4 7.8v.8a1 1 0 0 0 1 1ZM16.2 18a1.8 1.8 0 1 0 0-3.6h-.8a1 1 0 0 0-1 1v.8a1.8 1.8 0 0 0 1.8 1.8Z" fill="currentColor"/></svg>';
}
