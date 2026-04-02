<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => '只支持POST請求']);
    exit();
}

$username = $_POST['username'] ?? '';

if (empty($username) || strlen($username) < 3) {
    echo json_encode(['available' => false, 'message' => '帳號至少需要3個字符']);
    exit();
}

try {
    $sql = "SELECT id FROM users WHERE username = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    
    if ($stmt->fetch()) {
        echo json_encode(['available' => false, 'message' => '該帳號已被使用']);
    } else {
        echo json_encode(['available' => true, 'message' => '帳號可用']);
    }
} catch (Exception $e) {
    echo json_encode(['available' => false, 'message' => '檢查失敗，請稍後重試']);
}
?>

