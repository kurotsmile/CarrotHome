<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

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

function visit_ensure_tracking_tables(PDO $pdo, string $site): void
{
    $site = preg_replace('/[^a-z0-9_-]/i', '', $site) ?: 'web';
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS visit_daily_ip (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          site VARCHAR(32) NOT NULL DEFAULT '{$site}',
          visit_date DATE NOT NULL,
          ip_address VARBINARY(16) NOT NULL,
          ip_text VARCHAR(45) NOT NULL,
          first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          hits INT UNSIGNED NOT NULL DEFAULT 1,
          user_agent VARCHAR(512) DEFAULT NULL,
          referer VARCHAR(1024) DEFAULT NULL,
          request_path VARCHAR(1024) DEFAULT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_visit_daily_ip (site, visit_date, ip_address),
          KEY idx_visit_site_date (site, visit_date),
          KEY idx_visit_date (visit_date),
          KEY idx_visit_last_seen_at (last_seen_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS visit_hourly_ip (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          site VARCHAR(32) NOT NULL DEFAULT '{$site}',
          visit_date DATE NOT NULL,
          visit_hour TINYINT UNSIGNED NOT NULL,
          ip_address VARBINARY(16) NOT NULL,
          ip_text VARCHAR(45) NOT NULL,
          first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          hits INT UNSIGNED NOT NULL DEFAULT 1,
          user_agent VARCHAR(512) DEFAULT NULL,
          referer VARCHAR(1024) DEFAULT NULL,
          request_path VARCHAR(1024) DEFAULT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_visit_hourly_ip (site, visit_date, visit_hour, ip_address),
          KEY idx_visit_hourly_site_date_hour (site, visit_date, visit_hour),
          KEY idx_visit_hourly_date_hour (visit_date, visit_hour),
          KEY idx_visit_hourly_last_seen_at (last_seen_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
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
        visit_ensure_tracking_tables($pdo, 'home');
        $visitDate = date('Y-m-d');
        $seenAt = date('Y-m-d H:i:s');
        $visitHour = (int) date('G');
        $stmt = $pdo->prepare("
            INSERT INTO visit_daily_ip (
              site, visit_date, ip_address, ip_text, first_seen_at, last_seen_at,
              hits, user_agent, referer, request_path
            )
            VALUES ('home', :visit_date, INET6_ATON(:ip), :ip_text, :first_seen_at, :last_seen_at, 1, :user_agent, :referer, :request_path)
            ON DUPLICATE KEY UPDATE
              hits = hits + 1,
              last_seen_at = VALUES(last_seen_at),
              user_agent = VALUES(user_agent),
              referer = VALUES(referer),
              request_path = VALUES(request_path),
              updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':visit_date' => $visitDate,
            ':first_seen_at' => $seenAt,
            ':last_seen_at' => $seenAt,
            ':ip' => $ip,
            ':ip_text' => $ip,
            ':user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512) ?: null,
            ':referer' => substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 1024) ?: null,
            ':request_path' => visit_request_path(),
        ]);
    } catch (Throwable $e) {
        error_log('visit_track_daily_ip daily failed: ' . $e->getMessage());
        return;
    }

    try {
        $hourlyStmt = $pdo->prepare("
            INSERT INTO visit_hourly_ip (
              site, visit_date, visit_hour, ip_address, ip_text, first_seen_at, last_seen_at,
              hits, user_agent, referer, request_path
            )
            VALUES ('home', :visit_date, :visit_hour, INET6_ATON(:ip), :ip_text, :first_seen_at, :last_seen_at, 1, :user_agent, :referer, :request_path)
            ON DUPLICATE KEY UPDATE
              hits = hits + 1,
              last_seen_at = VALUES(last_seen_at),
              user_agent = VALUES(user_agent),
              referer = VALUES(referer),
              request_path = VALUES(request_path),
              updated_at = CURRENT_TIMESTAMP
        ");
        $hourlyStmt->execute([
            ':visit_date' => $visitDate,
            ':visit_hour' => $visitHour,
            ':first_seen_at' => $seenAt,
            ':last_seen_at' => $seenAt,
            ':ip' => $ip,
            ':ip_text' => $ip,
            ':user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512) ?: null,
            ':referer' => substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 1024) ?: null,
            ':request_path' => visit_request_path(),
        ]);
    } catch (Throwable $e) {
        error_log('visit_track_daily_ip hourly failed: ' . $e->getMessage());
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
        $viewDate = date('Y-m-d');
        $seenAt = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            INSERT INTO app_view (
              app_id, view_date, ip_address, ip_text, first_seen_at, last_seen_at,
              hits, user_agent, referer, request_path
            )
            VALUES (:app_id, :view_date, INET6_ATON(:ip), :ip_text, :first_seen_at, :last_seen_at, 1, :user_agent, :referer, :request_path)
            ON DUPLICATE KEY UPDATE
              hits = hits + 1,
              last_seen_at = VALUES(last_seen_at),
              user_agent = VALUES(user_agent),
              referer = VALUES(referer),
              request_path = VALUES(request_path),
              updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':app_id' => $app_id,
            ':view_date' => $viewDate,
            ':first_seen_at' => $seenAt,
            ':last_seen_at' => $seenAt,
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
