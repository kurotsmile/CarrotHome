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

function app_rate_key(): string
{
    $userId = (int) ($_SESSION['home_user_id'] ?? 0);
    if ($userId > 0) {
        return 'user:' . $userId;
    }

    $ip = visit_client_ip();
    $agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
    return 'guest:' . sha1($ip . '|' . $agent);
}

function app_rate_submit(?PDO $pdo, string $app_id, int $rating, string $review_text = ''): array
{
    $app_id = trim($app_id);
    $rating = max(1, min(5, $rating));
    if (!$pdo instanceof PDO || $app_id === '') {
        return ['success' => false, 'message' => 'Không thể lưu đánh giá lúc này.'];
    }

    try {
        $ip = visit_client_ip();
        $rateKey = app_rate_key();
        $userId = !empty($_SESSION['home_user_id']) ? (int) $_SESSION['home_user_id'] : null;
        $review_text = trim($review_text);
        $stmt = $pdo->prepare("
            INSERT INTO app_rate (
              app_id, user_id, rate_key, rating, review_text, lang, ip_address, ip_text, user_agent, status
            )
            VALUES (
              :app_id, :user_id, :rate_key, :rating, :review_text, :lang,
              INET6_ATON(NULLIF(:ip, '')),
              :ip_text, :user_agent, 'active'
            )
            ON DUPLICATE KEY UPDATE
              user_id = VALUES(user_id),
              rating = VALUES(rating),
              review_text = VALUES(review_text),
              lang = VALUES(lang),
              ip_address = VALUES(ip_address),
              ip_text = VALUES(ip_text),
              user_agent = VALUES(user_agent),
              status = 'active',
              updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':app_id' => $app_id,
            ':user_id' => $userId,
            ':rate_key' => $rateKey,
            ':rating' => $rating,
            ':review_text' => $review_text !== '' ? $review_text : null,
            ':lang' => function_exists('current_lang_key') ? current_lang_key() : null,
            ':ip' => $ip,
            ':ip_text' => $ip !== '' ? $ip : null,
            ':user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512) ?: null,
        ]);
        return ['success' => true, 'message' => 'Cảm ơn bạn đã đánh giá.'];
    } catch (Throwable $e) {
        error_log('app_rate_submit failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Không thể lưu đánh giá lúc này.'];
    }
}

function app_rate_summary(?PDO $pdo, string $app_id): array
{
    $empty = [
        'average' => 0.0,
        'count' => 0,
        'distribution' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
    ];
    $app_id = trim($app_id);
    if (!$pdo instanceof PDO || $app_id === '') {
        return $empty;
    }

    try {
        $stmt = $pdo->prepare("SELECT rating, COUNT(*) AS total FROM app_rate WHERE app_id = :app_id AND status = 'active' GROUP BY rating");
        $stmt->execute([':app_id' => $app_id]);
        $count = 0;
        $sum = 0;
        $distribution = $empty['distribution'];
        foreach ($stmt->fetchAll() as $row) {
            $rating = max(1, min(5, (int) ($row['rating'] ?? 0)));
            $total = (int) ($row['total'] ?? 0);
            $distribution[$rating] = $total;
            $count += $total;
            $sum += $rating * $total;
        }
        return [
            'average' => $count > 0 ? round($sum / $count, 1) : 0.0,
            'count' => $count,
            'distribution' => $distribution,
        ];
    } catch (Throwable $e) {
        error_log('app_rate_summary failed: ' . $e->getMessage());
        return $empty;
    }
}

function app_user_rate(?PDO $pdo, string $app_id): int
{
    $app_id = trim($app_id);
    if (!$pdo instanceof PDO || $app_id === '') {
        return 0;
    }

    try {
        $stmt = $pdo->prepare("SELECT rating FROM app_rate WHERE app_id = :app_id AND rate_key = :rate_key AND status = 'active' LIMIT 1");
        $stmt->execute([
            ':app_id' => $app_id,
            ':rate_key' => app_rate_key(),
        ]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('app_user_rate failed: ' . $e->getMessage());
        return 0;
    }
}
