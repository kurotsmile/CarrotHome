<?php
session_start();

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/paypal_config.php';

initialize_language_from_ip($pdo ?? null);

function paypal_return_fetch_order(?PDO $pdo, string $orderId, string $slug): ?array
{
    if (!$pdo instanceof PDO || $orderId === '') {
        return null;
    }

    $sql = '
        SELECT o.*, a.github, a.icon AS app_icon, a.decription AS app_description, ac.title AS app_title
        FROM app_orders o
        LEFT JOIN app a ON a.id = o.app_id
        LEFT JOIN app_content ac ON ac.app_id = o.app_id AND ac.lang_key = ?
        WHERE o.paypal_order_id = ?
    ';
    $params = [current_lang_key(), $orderId];

    if ($slug !== '') {
        $sql .= ' AND o.app_id IN (' . implode(',', array_fill(0, count(slug_lookup_candidates($slug)), '?')) . ')';
        $params = array_merge($params, slug_lookup_candidates($slug));
    }

    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function paypal_return_capture_order(PDO $pdo, array $paypalConfig, string $orderId): array
{
    if (empty($paypalConfig['client_id']) || empty($paypalConfig['client_secret'])) {
        throw new RuntimeException('PayPal config is missing.');
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is required for PayPal.');
    }

    $baseApi = !empty($paypalConfig['sandbox']) ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    $auth = base64_encode($paypalConfig['client_id'] . ':' . $paypalConfig['client_secret']);

    $ch = curl_init($baseApi . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);
    $tokenResponse = curl_exec($ch);
    $tokenStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $tokenData = json_decode((string)$tokenResponse, true);
    if ($tokenStatus >= 300 || empty($tokenData['access_token'])) {
        throw new RuntimeException('Unable to verify PayPal payment.');
    }

    $ch = curl_init($baseApi . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => '{}',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $tokenData['access_token'],
            'Content-Type: application/json',
        ],
    ]);
    $captureResponse = curl_exec($ch);
    $captureStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $captureData = json_decode((string)$captureResponse, true);
    if (!is_array($captureData)) {
        $captureData = ['status' => 'FAILED', 'raw_response' => (string)$captureResponse];
    }

    if ($captureStatus >= 300 || ($captureData['status'] ?? '') !== 'COMPLETED') {
        $stmt = $pdo->prepare('UPDATE app_orders SET status = ?, paypal_payload = ? WHERE paypal_order_id = ?');
        $stmt->execute([
            (string)($captureData['status'] ?? 'FAILED'),
            json_encode($captureData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $orderId,
        ]);
        throw new RuntimeException('PayPal payment was not completed.');
    }

    $payerEmail = (string)($captureData['payer']['email_address'] ?? '');
    $stmt = $pdo->prepare('
        UPDATE app_orders
        SET status = ?, payer_email = ?, paypal_payload = ?, paid_at = COALESCE(paid_at, NOW())
        WHERE paypal_order_id = ?
    ');
    $stmt->execute([
        (string)($captureData['status'] ?? 'COMPLETED'),
        $payerEmail,
        json_encode($captureData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $orderId,
    ]);

    return $captureData;
}

$paypalConfig = paypal_config_from_db($pdo ?? null, 'home');
$slug = trim((string)($_GET['slug'] ?? ''));
$orderId = trim((string)($_GET['token'] ?? ''));
$payerId = trim((string)($_GET['PayerID'] ?? ''));
$errorMessage = $db_error ?? '';
$noticeMessage = '';
$order = null;

if ($orderId === '') {
    http_response_code(400);
    $errorMessage = 'Missing PayPal order token.';
} elseif (!$pdo instanceof PDO) {
    http_response_code(500);
    $errorMessage = $errorMessage ?: 'Database connection is unavailable.';
} else {
    try {
        $order = paypal_return_fetch_order($pdo, $orderId, $slug);

        if (!$order) {
            http_response_code(404);
            $errorMessage = 'Không tìm thấy đơn hàng PayPal này.';
        } elseif (($order['status'] ?? '') !== 'COMPLETED' && $payerId !== '') {
            paypal_return_capture_order($pdo, $paypalConfig, $orderId);
            unset($_SESSION['paypal_app_orders'][$orderId]);
            $_SESSION['paid_github_apps'][(string)$order['app_id']] = true;
            $order = paypal_return_fetch_order($pdo, $orderId, (string)$order['app_id']);
            $noticeMessage = 'Thanh toán PayPal đã hoàn tất.';
        } elseif (($order['status'] ?? '') !== 'COMPLETED') {
            $noticeMessage = 'Đơn hàng chưa hoàn tất thanh toán trên PayPal.';
        } else {
            $_SESSION['paid_github_apps'][(string)$order['app_id']] = true;
        }
    } catch (Throwable $e) {
        http_response_code(402);
        $errorMessage = $e->getMessage();
        $order = paypal_return_fetch_order($pdo, $orderId, $slug);
    }
}

$appTitle = trim((string)($order['app_title'] ?? '')) ?: trim((string)($order['app_id'] ?? $slug));
$githubUrl = trim((string)($order['github'] ?? ''));
$isCompleted = (($order['status'] ?? '') === 'COMPLETED');
$page_title = ($appTitle !== '' ? $appTitle . ' - ' : '') . 'PayPal Order - CarrotHome';
$page_description = 'PayPal order result for CarrotHome app source purchase.';
$extra_head = '<style>
.paypal-result{width:min(100% - 32px,980px);margin:44px auto 72px}
.paypal-result-card{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:28px;align-items:stretch;padding:28px;border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:0 18px 48px rgba(20,20,24,.08)}
.paypal-result-main{display:flex;flex-direction:column;gap:18px;min-width:0}
.paypal-result-status{display:inline-flex;align-items:center;gap:8px;width:max-content;padding:7px 12px;border-radius:999px;background:#ecfdf3;color:#087443;font-size:13px;font-weight:900}
.paypal-result-status.is-pending{background:#fff7e6;color:#8a5200}
.paypal-result-status.is-error{background:#fff0f0;color:#b42318}
.paypal-result h1{margin:0;color:var(--text);font-size:34px;line-height:1.1}
.paypal-result p{margin:0;color:var(--muted);line-height:1.7}
.paypal-result-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:6px}
.paypal-result-button{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:46px;padding:0 18px;border-radius:8px;background:var(--accent);color:#fff;font-weight:900;white-space:nowrap}
.paypal-result-button:hover{background:var(--accent-dark);color:#fff}
.paypal-result-button.is-secondary{border:1px solid var(--line);background:#fff;color:var(--text)}
.paypal-result-button.is-secondary:hover{border-color:var(--accent);color:var(--accent)}
.paypal-result-side{display:grid;gap:14px;align-content:start;padding:20px;border-radius:8px;background:#f7f7f8}
.paypal-result-app{display:flex;gap:14px;align-items:center;min-width:0}
.paypal-result-app img{width:64px;height:64px;border-radius:14px;object-fit:cover;background:#fff}
.paypal-result-app strong{display:block;color:var(--text);font-size:18px}
.paypal-result-app span{display:block;margin-top:3px;color:var(--muted);font-size:13px}
.paypal-result-meta{display:grid;gap:10px;padding-top:8px;border-top:1px solid var(--line)}
.paypal-result-meta div{display:flex;justify-content:space-between;gap:16px;color:var(--muted);font-size:13px}
.paypal-result-meta strong{color:var(--text);text-align:right;word-break:break-word}
@media (max-width:820px){.paypal-result{margin-top:28px}.paypal-result-card{grid-template-columns:1fr;padding:20px}.paypal-result h1{font-size:27px}.paypal-result-actions{display:grid}.paypal-result-button{width:100%;padding-inline:14px;font-size:14px}}
</style>';

include __DIR__ . '/includes/header.php';
?>

<section class="paypal-result">
  <div class="paypal-result-card">
    <div class="paypal-result-main">
      <?php if ($errorMessage): ?>
        <span class="paypal-result-status is-error"><?= h(ui_label('paypal.status_error', 'Payment issue')) ?></span>
        <h1><?= h(ui_label('paypal.result_error_title', 'Không thể xác nhận đơn hàng')) ?></h1>
        <p><?= h($errorMessage) ?></p>
      <?php elseif ($isCompleted): ?>
        <span class="paypal-result-status"><?= h(ui_label('paypal.status_completed', 'Payment completed')) ?></span>
        <h1><?= h(ui_label('paypal.result_success_title', 'Đơn hàng đã sẵn sàng')) ?></h1>
        <p><?= h($noticeMessage ?: ui_label('paypal.result_success_body', 'Cảm ơn bạn đã thanh toán. Link GitHub của app đã được mở cho đơn hàng này.')) ?></p>
      <?php else: ?>
        <span class="paypal-result-status is-pending"><?= h(ui_label('paypal.status_pending', 'Payment pending')) ?></span>
        <h1><?= h(ui_label('paypal.result_pending_title', 'Đơn hàng chưa hoàn tất')) ?></h1>
        <p><?= h($noticeMessage ?: ui_label('paypal.result_pending_body', 'PayPal chưa trả về trạng thái hoàn tất cho đơn hàng này.')) ?></p>
      <?php endif; ?>

      <div class="paypal-result-actions">
        <?php if ($isCompleted && $githubUrl !== ''): ?>
          <a class="paypal-result-button" href="<?= h($githubUrl) ?>" target="_blank" rel="noopener noreferrer">
            <?= store_icon('github') ?>
            <?= h(ui_label('paypal.open_github', 'Mở link GitHub')) ?>
          </a>
        <?php endif; ?>
        <?php if (!empty($order['app_id'])): ?>
          <a class="paypal-result-button is-secondary" href="<?= h(app_url($order['app_id'])) ?>">
            <?= h(ui_label('paypal.back_to_app', 'Quay lại app')) ?>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <aside class="paypal-result-side" aria-label="<?= h(ui_label('paypal.order_detail', 'Order detail')) ?>">
      <div class="paypal-result-app">
        <?php if (!empty($order['app_icon'])): ?>
          <img src="<?= h(app_icon($order['app_icon'])) ?>" alt="">
        <?php endif; ?>
        <div>
          <strong><?= h($appTitle ?: ui_label('paypal.order', 'PayPal Order')) ?></strong>
          <span><?= h($order['app_id'] ?? $slug) ?></span>
        </div>
      </div>
      <div class="paypal-result-meta">
        <div><span><?= h(ui_label('paypal.order_id', 'PayPal Order')) ?></span><strong><?= h($orderId) ?></strong></div>
        <div><span><?= h(ui_label('paypal.status', 'Status')) ?></span><strong><?= h($order['status'] ?? '-') ?></strong></div>
        <div><span><?= h(ui_label('paypal.amount', 'Amount')) ?></span><strong><?= h(number_format((float)($order['amount'] ?? 0), 2)) ?> <?= h($order['currency'] ?? 'USD') ?></strong></div>
        <div><span><?= h(ui_label('paypal.payer', 'Payer')) ?></span><strong><?= h($order['payer_email'] ?? '-') ?></strong></div>
        <div><span><?= h(ui_label('paypal.created', 'Created')) ?></span><strong><?= h($order['created_at'] ?? '-') ?></strong></div>
        <div><span><?= h(ui_label('paypal.paid', 'Paid')) ?></span><strong><?= h($order['paid_at'] ?? '-') ?></strong></div>
      </div>
    </aside>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
