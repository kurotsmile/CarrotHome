<?php
session_start();

require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$country_id = (int)($_POST['country_id'] ?? 0);
$lang_key = trim((string)($_POST['lang_key'] ?? ''));

if ($country_id <= 0 && $lang_key === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing language']);
    exit;
}

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database unavailable']);
    exit;
}

try {
    if ($country_id > 0) {
        $stmt = $pdo->prepare("SELECT id, lang_key, lang_country FROM country WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $country_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id, lang_key, lang_country FROM country WHERE lang_key = :lang_key LIMIT 1");
        $stmt->execute([':lang_key' => $lang_key]);
    }
    $country = $stmt->fetch();

    if (!$country) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Language not found']);
        exit;
    }

    $_SESSION['key_lang'] = $country['lang_key'];
    $_SESSION['country_id'] = (int)$country['id'];
    $_SESSION['lang_country'] = $country['lang_country'];

    echo json_encode([
        'success' => true,
        'key_lang' => $_SESSION['key_lang'],
        'country_id' => $_SESSION['country_id'],
        'lang_country' => $_SESSION['lang_country'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Cannot change language']);
}
