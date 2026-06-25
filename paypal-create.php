<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/paypal_config.php';

$paypal_config = paypal_config_from_db($pdo ?? null, 'home');
$slug = trim($_GET['slug'] ?? '');

if ($slug === '' || empty($paypal_config['enabled'])) {
    http_response_code(400);
    exit('Invalid payment request.');
}

if (empty($paypal_config['client_id']) || empty($paypal_config['client_secret'])) {
    http_response_code(500);
    exit('PayPal config is missing.');
}

if (!function_exists('curl_init')) {
    http_response_code(500);
    exit('PHP cURL extension is required for PayPal.');
}

$app = null;
if ($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM app WHERE id = :slug AND status != 'trash' LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $app = $stmt->fetch();
}

if (!$app || empty($app['github'])) {
    http_response_code(404);
    exit('Paid link is not available.');
}

$source_price = trim((string)($app['price'] ?? ''));
if ($source_price === '' || (float)$source_price <= 0) {
    $source_price = trim((string)($paypal_config['amount'] ?? ''));
}
if ($source_price === '' || (float)$source_price <= 0) {
    http_response_code(400);
    exit('Source price is not configured.');
}
$source_price = number_format((float)$source_price, 2, '.', '');

$base_api = !empty($paypal_config['sandbox']) ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
$auth = base64_encode($paypal_config['client_id'] . ':' . $paypal_config['client_secret']);

$ch = curl_init($base_api . '/v1/oauth2/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
    CURLOPT_HTTPHEADER => [
        'Authorization: Basic ' . $auth,
        'Content-Type: application/x-www-form-urlencoded',
    ],
]);
$token_response = curl_exec($ch);
$token_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$token_data = json_decode((string)$token_response, true);
if ($token_status >= 300 || empty($token_data['access_token'])) {
    http_response_code(502);
    exit('Unable to connect to PayPal.');
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'home.carrot28.com';
$return_url = $scheme . '://' . $host . base_url('paypal-return.php?slug=' . urlencode($slug));
$cancel_url = $scheme . '://' . $host . app_url($slug);

$payload = [
    'intent' => 'CAPTURE',
    'purchase_units' => [[
        'reference_id' => $slug,
        'description' => 'GitHub source link for ' . $slug,
        'amount' => [
            'currency_code' => $paypal_config['currency'],
            'value' => $source_price,
        ],
    ]],
    'application_context' => [
        'return_url' => $return_url,
        'cancel_url' => $cancel_url,
        'user_action' => 'PAY_NOW',
    ],
];

$ch = curl_init($base_api . '/v2/checkout/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token_data['access_token'],
        'Content-Type: application/json',
    ],
]);
$order_response = curl_exec($ch);
$order_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$order_data = json_decode((string)$order_response, true);
if ($order_status >= 300 || empty($order_data['id']) || empty($order_data['links'])) {
    http_response_code(502);
    exit('Unable to create PayPal order.');
}

$_SESSION['paypal_app_orders'][$order_data['id']] = $slug;

foreach ($order_data['links'] as $link) {
    if (($link['rel'] ?? '') === 'approve' && !empty($link['href'])) {
        header('Location: ' . $link['href']);
        exit;
    }
}

http_response_code(502);
exit('PayPal approval link is missing.');
