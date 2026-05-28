<?php
// API 端點：檢查使用者名稱是否可用（AJAX 用）
header('Content-Type: application/json; charset=utf-8');
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
