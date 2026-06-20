<?php
session_start();

require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$lang_key = trim((string)($_POST['lang_key'] ?? ''));

if ($lang_key === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing lang_key']);
    exit;
}

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database unavailable']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT lang_key FROM country WHERE lang_key = :lang_key LIMIT 1");
    $stmt->execute([':lang_key' => $lang_key]);
    $country = $stmt->fetch();

    if (!$country) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Language not found']);
        exit;
    }

    $_SESSION['key_lang'] = $country['lang_key'];

    echo json_encode(['success' => true, 'key_lang' => $_SESSION['key_lang']]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Cannot change language']);
}
