<?php
// PDO 連線設定（預設 XAMPP MySQL: root / 空密碼）
$host = '127.0.0.1';
$db   = 'group_41';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo 'DB 連線失敗：' . htmlspecialchars($e->getMessage());
    exit();
}

/**
 * 取得 PDO 實例
 */
function get_db()
{
    global $pdo;
    return $pdo;
}
