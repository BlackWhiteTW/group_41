<?php
session_start();
require __DIR__ . '/../includes/db.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
if (empty($user_raw)) {
    header('Location: /group_41/login.php');
    exit();
}

$pdo = get_db();
$u = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
$u->execute([':u' => $user_raw]);
$current_user = $u->fetch();
if (!$current_user || $current_user['role'] !== 'admin') {
    $_SESSION['flash_error'] = '需要管理員權限才能使用除錯工具。';
    header('Location: /group_41/index.php');
    exit();
}

header('Content-Type: text/plain; charset=utf-8');

$base = __DIR__ . '/../';
$checks = [
    'index' => $base . 'index.php',
    'login' => $base . 'login.php',
    'register' => $base . 'register.php',
    'css' => $base . 'css/app.css',
    'js' => $base . 'js/app.js',
    'db' => $base . 'includes/db.php',
    'database_sql' => $base . 'database.sql'
];

echo "Debug: 檔案存在性檢查\n";
foreach ($checks as $k => $p) {
    echo sprintf("%s: %s - %s\n", $k, $p, file_exists($p) ? 'FOUND' : 'MISSING');
}

echo "\nPHP 語意檢查：CSS 連結字串（index.php）\n";
$index = file_get_contents($base . 'index.php');
if ($index !== false) {
    if (preg_match('/<link[^>]+href=["\']([^"\']+css\/app\.css)["\']/i', $index, $m)) {
        echo "index.php link -> " . $m[1] . "\n";
    } else {
        echo "index.php: 未找到 CSS 連結字串\n";
    }
}

// 顯示其他實用資訊
echo "\n環境資訊\n";
echo "PHP version: " . phpversion() . "\n";
echo "Server OS: " . PHP_OS . "\n";

exit();
