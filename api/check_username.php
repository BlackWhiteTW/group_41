<?php
header('Content-Type: application/json; charset=utf-8');

session_start();
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_key = 'rate_' . $ip;
$now = time();
$window = 5;
$max_requests = 10;

if (!isset($_SESSION[$rate_key])) {
    $_SESSION[$rate_key] = ['count' => 0, 'start' => $now];
}
$rate = &$_SESSION[$rate_key];
if ($now - $rate['start'] > $window) {
    $rate = ['count' => 0, 'start' => $now];
}
if ($rate['count'] >= $max_requests) {
    http_response_code(429);
    echo json_encode(['available' => false, 'message' => '請求過於頻繁，請稍後再試'], JSON_UNESCAPED_UNICODE);
    exit();
}
$rate['count']++;

require '../includes/db.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    echo json_encode(['available' => false, 'message' => '缺少查詢參數'], JSON_UNESCAPED_UNICODE);
    exit();
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
$stmt->execute([':u' => $q]);
$exists = $stmt->fetch();

if ($exists) {
    echo json_encode(['available' => false, 'message' => '帳號已存在'], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['available' => true, 'message' => '可使用'], JSON_UNESCAPED_UNICODE);
}
exit();
