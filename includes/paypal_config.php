<?php

function paypal_config_from_db(?PDO $pdo, string $site): array
{
    $defaults = [
        'enabled' => false,
        'sandbox' => true,
        'client_id' => '',
        'client_secret' => '',
        'currency' => 'USD',
        'amount' => '0.00',
    ];

    if (!$pdo instanceof PDO) {
        return $defaults;
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM paypal_config WHERE site = ? LIMIT 1');
        $stmt->execute([$site]);
        $row = $stmt->fetch();
        if (!$row) {
            return $defaults;
        }

        $mode = ($row['active_mode'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
        $prefix = $mode === 'live' ? 'live' : 'sandbox';

        return [
            'enabled' => !empty($row['enabled']),
            'sandbox' => $mode !== 'live',
            'client_id' => (string) ($row[$prefix . '_client_id'] ?? ''),
            'client_secret' => (string) ($row[$prefix . '_client_secret'] ?? ''),
            'currency' => (string) ($row['currency'] ?? 'USD'),
            'amount' => (string) ($row['amount'] ?? '0.00'),
        ];
    } catch (Throwable $e) {
        return $defaults;
    }
}
