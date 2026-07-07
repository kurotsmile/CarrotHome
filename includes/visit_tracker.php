<?php

function visit_client_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_REAL_IP'] ?? '',
        strtok((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''), ',') ?: '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $ip = trim((string) $candidate);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return '';
}

function visit_request_path(): string
{
    return substr((string) ($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? '')), 0, 1024);
}

function visit_track_daily_ip(?PDO $pdo): void
{
    if (!$pdo instanceof PDO || PHP_SAPI === 'cli') {
        return;
    }

    $ip = visit_client_ip();
    if ($ip === '') {
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO visit_daily_ip (
              site, visit_date, ip_address, ip_text, first_seen_at, last_seen_at,
              hits, user_agent, referer, request_path
            )
            VALUES ('home', CURRENT_DATE, INET6_ATON(:ip), :ip_text, NOW(), NOW(), 1, :user_agent, :referer, :request_path)
            ON DUPLICATE KEY UPDATE
              hits = hits + 1,
              last_seen_at = NOW(),
              user_agent = VALUES(user_agent),
              referer = VALUES(referer),
              request_path = VALUES(request_path),
              updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':ip' => $ip,
            ':ip_text' => $ip,
            ':user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512) ?: null,
            ':referer' => substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 1024) ?: null,
            ':request_path' => visit_request_path(),
        ]);
    } catch (Throwable $e) {
        error_log('visit_track_daily_ip failed: ' . $e->getMessage());
    }
}

function app_track_view(?PDO $pdo, string $app_id): void
{
    $app_id = trim($app_id);
    if (!$pdo instanceof PDO || PHP_SAPI === 'cli' || $app_id === '') {
        return;
    }

    $ip = visit_client_ip();
    if ($ip === '') {
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO app_view (
              app_id, view_date, ip_address, ip_text, first_seen_at, last_seen_at,
              hits, user_agent, referer, request_path
            )
            VALUES (:app_id, CURRENT_DATE, INET6_ATON(:ip), :ip_text, NOW(), NOW(), 1, :user_agent, :referer, :request_path)
            ON DUPLICATE KEY UPDATE
              hits = hits + 1,
              last_seen_at = NOW(),
              user_agent = VALUES(user_agent),
              referer = VALUES(referer),
              request_path = VALUES(request_path),
              updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':app_id' => $app_id,
            ':ip' => $ip,
            ':ip_text' => $ip,
            ':user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512) ?: null,
            ':referer' => substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 1024) ?: null,
            ':request_path' => visit_request_path(),
        ]);
    } catch (Throwable $e) {
        error_log('app_track_view failed: ' . $e->getMessage());
    }
}

function app_view_count(?PDO $pdo, string $app_id): int
{
    $app_id = trim($app_id);
    if (!$pdo instanceof PDO || $app_id === '') {
        return 0;
    }

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM app_view WHERE app_id = :app_id');
        $stmt->execute([':app_id' => $app_id]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('app_view_count failed: ' . $e->getMessage());
        return 0;
    }
}
