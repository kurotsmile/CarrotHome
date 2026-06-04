<?php
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function base_url($path = '') {
    $path = ltrim((string)$path, '/');
    return '/' . $path;
}

function app_url($slug) {
    return base_url('app.php?slug=' . urlencode((string)$slug));
}

function app_icon($icon) {
    return !empty($icon) ? $icon : base_url('assets/no-image.png');
}

function json_array($json) {
    if (!$json) return [];
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function first_image($app) {
    $images = json_array($app['images'] ?? '');
    foreach ($images as $url) {
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

function label_name($key) {
    $labels = [
        'apk_file' => 'APK',
        'exe_file' => 'Windows',
        'deb_file' => 'Linux DEB',
        'dmg_file' => 'macOS',
        'ipa_file' => 'iOS',
        'google_play' => 'Google Play',
        'amazon_app_store' => 'Amazon',
        'microsoft_store' => 'Microsoft',
        'itch' => 'Itch.io',
        'uptodown' => 'Uptodown',
        'huawei_store' => 'Huawei',
        'simmer' => 'Simmer',
        'youtube_link' => 'YouTube',
    ];
    return $labels[$key] ?? ucwords(str_replace('_', ' ', (string)$key));
}
