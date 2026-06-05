<?php
/**
 * 集中式管理員授權檢查。
 * 任何 admin 頁面頂端只需：
 *   require __DIR__ . '/../includes/admin_auth.php';
 * 即可取得 $current_user (含 id, username, role) 並確保僅管理員可存取。
 * 未登入 → ../login.php，非 admin → ../index.php (附 flash_error)。
 */

require_once __DIR__ . '/cookies.php';
require_once __DIR__ . '/db.php';

$user_raw = $GLOBALS['user_raw'] ?? null;

if (empty($user_raw)) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $user_raw = $_SESSION['user'] ?? null;
}

if (empty($user_raw)) {
    header('Location: ../login.php');
    exit();
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
$stmt->execute([':u' => $user_raw]);
$current_user = $stmt->fetch();

if (!$current_user || $current_user['role'] !== 'admin') {
    $_SESSION['flash_error'] = '需要管理員權限才能進入管理介面。';
    header('Location: ../index.php');
    exit();
}
