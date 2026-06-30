<?php
session_start();

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/paypal_config.php';

$paypal_config = paypal_config_from_db($pdo ?? null, 'home');
$slug = trim($_GET['slug'] ?? '');
$order_id = trim($_GET['token'] ?? '');

if ($slug === '' || $order_id === '' || empty($_SESSION['paypal_app_orders'][$order_id]) || $_SESSION['paypal_app_orders'][$order_id] !== $slug) {
    http_response_code(400);
    exit('Invalid PayPal return.');
}

if (empty($paypal_config['client_id']) || empty($paypal_config['client_secret'])) {
    http_response_code(500);
    exit('PayPal config is missing.');
}

if (!function_exists('curl_init')) {
    http_response_code(500);
    exit('PHP cURL extension is required for PayPal.');
}

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
    exit('Unable to verify PayPal payment.');
}

$ch = curl_init($base_api . '/v2/checkout/orders/' . rawurlencode($order_id) . '/capture');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => '{}',
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token_data['access_token'],
        'Content-Type: application/json',
    ],
]);
$capture_response = curl_exec($ch);
$capture_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$capture_data = json_decode((string)$capture_response, true);
if ($capture_status >= 300 || ($capture_data['status'] ?? '') !== 'COMPLETED') {
    if ($pdo instanceof PDO) {
        $stmt = $pdo->prepare('UPDATE app_orders SET status = ?, paypal_payload = ? WHERE paypal_order_id = ?');
        $stmt->execute([
            (string)($capture_data['status'] ?? 'FAILED'),
            json_encode($capture_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $order_id,
        ]);
    }
    http_response_code(402);
    exit('PayPal payment was not completed.');
}

if ($pdo instanceof PDO) {
    $payerEmail = (string)($capture_data['payer']['email_address'] ?? '');
    $stmt = $pdo->prepare('
        UPDATE app_orders
        SET status = ?, payer_email = ?, paypal_payload = ?, paid_at = NOW()
        WHERE paypal_order_id = ?
    ');
    $stmt->execute([
        (string)($capture_data['status'] ?? 'COMPLETED'),
        $payerEmail,
        json_encode($capture_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $order_id,
    ]);
}

unset($_SESSION['paypal_app_orders'][$order_id]);
$_SESSION['paid_github_apps'][$slug] = true;

header('Location: ' . app_url($slug) . '?paid=1');
exit;
