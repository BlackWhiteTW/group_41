<?php
session_start();

require __DIR__ . '/includes/db.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
if (empty($user_raw)) {
    header('Location: /group_41/login.php');
    exit();
}

$current_user = null;
$u = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
$u->execute([':u' => $user_raw]);
$current_user = $u->fetch();

if (!$current_user || $current_user['role'] !== 'admin') {
    $_SESSION['flash_error'] = '需要管理員權限才能執行安裝程序。';
    header('Location: /group_41/index.php');
    exit();
}

if (!isset($_GET['confirm']) || $_GET['confirm'] !== '1') {
    echo "要匯入資料庫請加上 ?confirm=1 參數。此腳本會在執行後嘗試建立 `group_41` 資料表。";
    exit();
}

$sql_file = __DIR__ . '/database.sql';
if (!file_exists($sql_file)) {
    echo '找不到 database.sql';
    exit();
}

$sql = file_get_contents($sql_file);
if ($sql === false) {
    echo '無法讀取 database.sql';
    exit();
}

$stmts = preg_split('/;\s*\n/', $sql);
$pdo->beginTransaction();
try {
    foreach ($stmts as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;
        $pdo->exec($stmt);
    }
    $pdo->commit();
    echo '已嘗試匯入 database.sql （請檢查錯誤訊息以確認結果）';
} catch (PDOException $e) {
    $pdo->rollBack();
    echo '匯入失敗：' . htmlspecialchars($e->getMessage());
}
