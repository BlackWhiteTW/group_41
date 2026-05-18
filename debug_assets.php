<?php
// 簡易診斷頁面：檢查重要資源是否存在並顯示路徑
header('Content-Type: text/plain; charset=utf-8');

$base = __DIR__ . '/';
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