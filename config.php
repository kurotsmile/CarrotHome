<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

define('APP_NAME', 'CarrotHome');
define('APP_VERSION', '1.0.0');
define('APP_OWNER', 'CarrotHome Server');

function base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

    if ($path === '/' || $path === '\\') {
        $path = '';
    }

    return $protocol . $host . $path;
}

function app_url($path = '') {
    return base_url() . '/' . ltrim($path, '/');
}
