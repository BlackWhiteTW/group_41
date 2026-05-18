<?php
// 數據庫連接配置
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'group_41';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('數據庫連接失敗：' . $e->getMessage());
}
?>
