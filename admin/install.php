<?php
// 安裝腳本（管理區）：執行 database.sql 以建立資料表（僅限管理員使用）
session_start();

require '../includes/db.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
if (empty($user_raw)) {
    header('Location: ../login.php');
    exit();
}

$current_user = null;
$pdo = get_db();
$u = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
$u->execute([':u' => $user_raw]);
$current_user = $u->fetch();

if (!$current_user || $current_user['role'] !== 'admin') {
    $_SESSION['flash_error'] = '需要管理員權限才能執行安裝程序。';
    header('Location: ../index.php');
    exit();
}

if (!isset($_GET['confirm']) || $_GET['confirm'] !== '1') {
    $message = '要匯入資料庫請加上 ?confirm=1 參數。此腳本會執行 database.sql。';
    $status = 'info';
    $ready = false;
    $show_page = true;
}

$message = isset($message) ? $message : '';
$status = isset($status) ? $status : 'info';
$ready = isset($ready) ? $ready : true;

if ($ready) {
    $sql_file = '../database.sql';
    if (!file_exists($sql_file)) {
        $message = '找不到 database.sql';
        $status = 'error';
    } else {
        $sql = file_get_contents($sql_file);
        if ($sql === false) {
            $message = '無法讀取 database.sql';
            $status = 'error';
        } else {
            $stmts = preg_split('/;\s*\n/', $sql);
            $pdo->beginTransaction();
            try {
                foreach ($stmts as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt === '') {
                        continue;
                    }
                    $pdo->exec($stmt);
                }
                $pdo->commit();
                $message = '已嘗試匯入 database.sql（請檢查錯誤訊息以確認結果）。';
                $status = 'success';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = '匯入失敗：' . htmlspecialchars($e->getMessage());
                $status = 'error';
            }
        }
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>安裝資料庫 | 管理</title>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;600;700&display=swap"
            rel="stylesheet"
        />
        <link rel="stylesheet" href="../css/app.css" />
    </head>
    <body>
        <?php $base_url = '../'; require '../includes/header.php'; ?>

        <main class="section">
            <div class="container">
                <h1>安裝資料庫（管理區）</h1>
                <p class="muted">執行 database.sql 以建立/重建資料表。</p>
                <?php if ($status === 'error') : ?>
                    <div class="error"><?php echo $message; ?></div>
                <?php else : ?>
                    <div class="panel" style="padding: 20px">
                        <p class="muted"><?php echo htmlspecialchars($message); ?></p>
                        <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap">
                            <a class="btn btn-ghost" href="./">返回控制台</a>
                            <?php if ($status !== 'success') : ?>
                                <a class="btn btn-primary" href="./install.php?confirm=1">再次執行</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <footer class="footer container">社團表單系統</footer>
        <script src="../js/app.js"></script>
    </body>
</html>

<?php
exit();
