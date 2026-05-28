<?php
session_start();
require '../includes/db.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
if (empty($user_raw)) {
    header('Location: ../login.php');
    exit();
}

$pdo = null;
try {
    $pdo = get_db();
} catch (Throwable $e) {
    // ignore for now - we'll report below
}

$current_user = null;
$current_role = null;
if ($pdo) {
    try {
        $u = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
        $u->execute([':u' => $user_raw]);
        $current_user = $u->fetch();
        if ($current_user && isset($current_user['role'])) {
            $current_role = $current_user['role'];
        }
    } catch (Throwable $e) {
        // ignore
    }
}

// 允許資料庫查到的 admin，或 session 內明確是 admin 帳號時通過。
$is_admin = false;
if ($current_user && isset($current_user['role']) && strtolower((string) $current_user['role']) === 'admin') {
    $is_admin = true;
} elseif (strtolower((string) $user_raw) === 'admin') {
    $is_admin = true;
}

if (!$is_admin) {
    $_SESSION['flash_error'] = '需要管理員權限才能使用除錯工具。';
    header('Location: ../index.php');
    exit();
}

header('Content-Type: text/plain; charset=utf-8');

$base = '../';

echo "DEBUG REPORT - admin/debug_assets.php\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";
echo "Auth check: session user = " . $user_raw . "\n";
echo "Auth check: db role = " . ($current_role !== null ? $current_role : '(unknown)') . "\n\n";

// 1) 檔案存在性與實際路徑
$checks = [
    'index' => $base . 'index.php',
    'login' => $base . 'login.php',
    'register' => $base . 'register.php',
    'css' => $base . 'css/app.css',
    'js' => $base . 'js/app.js',
    'db' => $base . 'includes/db.php',
    'database_sql' => $base . 'database.sql'
];

echo "1) 檔案存在性與路徑檢查\n";
foreach ($checks as $k => $p) {
    $exists = file_exists($p);
    $real = $exists ? realpath($p) : 'MISSING';
    echo sprintf("- %-12s : %s - %s\n", $k, $p, $exists ? 'FOUND' : 'MISSING');
    echo sprintf("  Resolved: %s\n", $real);
}

// 2) 解析 index.php 中的資源連結並檢查是否使用相對路徑
echo "\n2) 解析 index.php 中的資源連結（檢查是否為相對路徑）\n";
$index_path = $base . 'index.php';
if (file_exists($index_path)) {
    $index_content = file_get_contents($index_path);
    preg_match_all('/(?:href|src)\s*=\s*(?:"([^"]+)"|\'([^\']+)\')/i', $index_content, $matches, PREG_SET_ORDER);
    if (empty($matches)) {
        echo "- 未找到 href/src 欄位，或格式不標準。\n";
    } else {
        foreach ($matches as $m) {
            $p = !empty($m[1]) ? $m[1] : $m[2];
            $type = 'relative';
            if (preg_match('#^https?://#i', $p) || strpos($p, '//') === 0) {
                $type = 'absolute_url';
            } elseif (strpos($p, '/') === 0) {
                $type = 'absolute_root';
            }
            $resolved = 'n/a';
            if ($type === 'relative') {
                $candidate = $base . ltrim($p, './');
                $resolved = file_exists($candidate) ? realpath($candidate) : 'MISSING';
            }
            echo sprintf("- %s (type=%s, resolved=%s)\n", $p, $type, $resolved ?? 'n/a');
        }
    }
} else {
    echo "- index.php 不存在，無法解析資源。\n";
}

// 3) group_41.sql 檔案內容檢查
echo "3) database.sql 檔案內容檢查\n";
$sql_path = $base . 'database.sql';
if (file_exists($sql_path)) {
    $sql = file_get_contents($sql_path);
    $len = strlen($sql);
    echo "- database.sql 長度: {$len} bytes\n";
    if (preg_match('/CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m) || preg_match('/CREATE\s+DATABASE\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m)) {
        echo "- Found CREATE DATABASE -> " . $m[1] . "\n";
    } elseif (preg_match('/USE\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m2)) {
        echo "- Found USE -> " . $m2[1] . "\n";
    } else {
        echo "- 未在 SQL 檔找到 CREATE DATABASE 或 USE 宣告，請確認資料庫名稱是否在檔案中。\n";
    }
    echo "- database.sql 存在";
} else {
    echo "- database.sql 不存在。\n";
}

// 4) includes/db.php 與實際 DB 連線檢查
echo "\n4) includes/db.php 與實際 DB 連線檢查\n";
$db_file = $base . 'includes/db.php';
if (file_exists($db_file)) {
    $db_content = file_get_contents($db_file);
    $db_name = null; $db_user = null; $db_pass = null;
    if (preg_match('/\$db\s*=\s*["\']([a-zA-Z0-9_]+)["\']\s*;/', $db_content, $m)) {
        $db_name = $m[1];
    }
    if (preg_match('/\$user\s*=\s*["\']([^"\']*)["\']\s*;/', $db_content, $m2)) {
        $db_user = $m2[1];
    }
    if (preg_match('/\$pass\s*=\s*["\']([^"\']*)["\']\s*;/', $db_content, $m3)) {
        $db_pass = $m3[1];
    }
    echo sprintf("- includes/db.php: db=%s, user=%s, pass=%s\n", $db_name?:'(unknown)', $db_user?:'(unknown)', $db_pass !== null ? '(' . strlen($db_pass) . ' chars)' : '(unknown)');

    if ($pdo) {
        try {
            $current_db = $pdo->query('SELECT DATABASE()')->fetchColumn();
            echo "- PDO connected to database: " . ($current_db ?: '(none)') . "\n";
            $ok = $pdo->query('SELECT 1')->fetchColumn();
            echo "- Simple query test: " . ($ok ? 'OK' : 'NO') . "\n";
        } catch (Throwable $e) {
            echo "- DB probe failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "- 無法建立 PDO 連線（get_db() 回傳 null 或拋出例外）。\n";
    }
} else {
    echo "- includes/db.php 不存在，無法檢查資料庫設定。\n";
}

// 5) 檢查是否存在預設帳號並驗證密碼
echo "\n5) 帳號檢查（member/admin）\n";
$accounts = [
    ['username' => 'member', 'password' => 'member123456'],
    ['username' => 'admin', 'password' => 'admin123456']
];
if ($pdo) {
    foreach ($accounts as $a) {
        try {
            $s = $pdo->prepare('SELECT id, username, password, role FROM users WHERE username = :u LIMIT 1');
            $s->execute([':u' => $a['username']]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                echo sprintf("- %s: NOT FOUND\n", $a['username']);
                continue;
            }
            $pw_ok = password_verify($a['password'], $row['password']);
            echo sprintf("- %s: FOUND (id=%s, role=%s) - password match: %s\n", $a['username'], $row['id'], $row['role'], $pw_ok ? 'YES' : 'NO');
        } catch (Throwable $e) {
            echo sprintf("- %s: ERROR querying users: %s\n", $a['username'], $e->getMessage());
        }
    }
} else {
    echo "- 無法檢查使用者（資料庫連線不可用）。\n";
}

// 6) 搜尋檔案是否仍有站點絕對路徑或 root-absolute 使用
echo "\n6) 檔案中是否仍有站點絕對路徑或 root-absolute 使用（建議使用 ./ 或 ../）\n";
$files_to_search = [
    $base . 'index.php',
    $base . 'includes/header.php',
    $base . 'forms/list.php',
    $base . 'admin/index.php'
];
foreach ($files_to_search as $f) {
    if (!file_exists($f)) { echo sprintf("- %s : MISSING\n", $f); continue; }
    $c = file_get_contents($f);
    if (preg_match('#href\s*=\s*["\']/#i', $c) || preg_match('#src\s*=\s*["\']/#i', $c)) {
        echo sprintf("- %s : contains root-absolute links\n", $f);
    } else {
        echo sprintf("- %s : no obvious root-absolute occurrences\n", $f);
    }
}

echo "\nEnd of report.\n";
exit();
