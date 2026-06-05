<?php
require __DIR__ . '/../includes/admin_auth.php';

header('Content-Type: text/plain; charset=utf-8');

$base = '../';

echo "=== 管理員簡易測試報告 ===\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";
echo "管理員帳號：" . htmlspecialchars($current_user['username']) . " (ID: " . (int)$current_user['id'] . ", role: " . htmlspecialchars($current_user['role']) . ")\n\n";

$checks = [
    'index' => $base . 'index.php',
    'login' => $base . 'login.php',
    'register' => $base . 'register.php',
    'css' => $base . 'css/app.css',
    'js' => $base . 'js/app.js',
    'db' => $base . 'includes/db.php',
    'database_sql' => $base . 'group_41.sql'
];

echo "1) 檔案存在性與路徑檢查\n";
foreach ($checks as $k => $p) {
    $exists = file_exists($p);
    $real = $exists ? realpath($p) : 'MISSING';
    echo sprintf("- %-12s : %s - %s\n", $k, $p, $exists ? 'FOUND' : 'MISSING');
}

echo "\n2) 資料庫連線檢查\n";
try {
    $current_db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    echo "- 已連線到資料庫：" . ($current_db ?: '(none)') . "\n";
    $tbl_count = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    echo "- 資料表數量：" . $tbl_count . "\n";
    echo "- 連線測試：OK\n";
} catch (Throwable $e) {
    echo "- 資料庫探測失敗：" . htmlspecialchars($e->getMessage()) . "\n";
}

echo "\n3) 使用者總數\n";
try {
    $user_count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    echo "- 使用者總數：" . $user_count . "\n";
    $admin_count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    echo "- 管理員數量：" . $admin_count . "\n";
} catch (Throwable $e) {
    echo "- 查詢失敗：" . htmlspecialchars($e->getMessage()) . "\n";
}

echo "\n4) group_41.sql 狀態\n";
$sql_path = $base . 'group_41.sql';
if (file_exists($sql_path)) {
    $sql = file_get_contents($sql_path);
    echo "- 檔案存在，大小：" . strlen($sql) . " bytes\n";
} else {
    echo "- 檔案不存在。\n";
}

echo "\nEnd of report.\n";
exit();
