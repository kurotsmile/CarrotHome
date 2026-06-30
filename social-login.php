<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

function social_login_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . ($path === '' ? '' : $path);
}

function social_login_error(string $message): void
{
    header('Location: login.php?mode=register&oauth_error=' . rawurlencode($message));
    exit;
}

$provider = trim((string)($_GET['provider'] ?? ''));
$allowed = ['google', 'github', 'twitter_x'];
if (!in_array($provider, $allowed, true)) {
    social_login_error('Provider không hợp lệ.');
}

if (!$pdo instanceof PDO) {
    social_login_error($db_error ?? 'Không thể kết nối database.');
}

$stmt = $pdo->prepare('SELECT * FROM api_config WHERE provider = ? AND enabled = 1 ORDER BY id DESC LIMIT 1');
$stmt->execute([$provider]);
$config = $stmt->fetch();
if (!$config) {
    social_login_error('Chưa bật API config cho ' . $provider . '.');
}

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$_SESSION['oauth_provider'] = $provider;

$callbackUrl = social_login_base_url() . '/oauth-callback.php';
$projectUrl = rtrim(trim((string)($config['project_url'] ?? '')), '/');
$apiKey = trim((string)($config['api_key'] ?? ''));

if ($projectUrl !== '' && $apiKey !== '') {
    $supabaseProvider = $provider === 'twitter_x' ? 'x' : $provider;
    $_SESSION['oauth_mode'] = 'supabase';
    $_SESSION['oauth_supabase_project_url'] = $projectUrl;
    $_SESSION['oauth_supabase_api_key'] = $apiKey;
    $_SESSION['oauth_supabase_provider'] = $supabaseProvider;

    $url = $projectUrl . '/auth/v1/authorize?' . http_build_query([
        'provider' => $supabaseProvider,
        'redirect_to' => $callbackUrl,
    ]);
    header('Location: ' . $url);
    exit;
}

$clientId = trim((string)($config['client_id'] ?? ''));
$clientSecret = trim((string)($config['client_secret'] ?? ''));
if ($clientId === '' || $clientSecret === '') {
    social_login_error('API config cần Client ID và Client Secret.');
}

$_SESSION['oauth_mode'] = 'direct';
$_SESSION['oauth_client_id'] = $clientId;
$_SESSION['oauth_client_secret'] = $clientSecret;

$scopes = trim((string)($config['scopes'] ?? ''));
if ($provider === 'github') {
    $scopes = $scopes !== '' ? $scopes : 'read:user user:email';
    $authUrl = 'https://github.com/login/oauth/authorize?' . http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $callbackUrl,
        'scope' => $scopes,
        'state' => $state,
    ]);
} elseif ($provider === 'google') {
    $scopes = $scopes !== '' ? $scopes : 'openid email profile';
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $callbackUrl,
        'response_type' => 'code',
        'scope' => $scopes,
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account',
    ]);
} else {
    social_login_error('Twitter/X nên cấu hình qua Supabase Auth hoặc PKCE riêng.');
}

header('Location: ' . $authUrl);
exit;
