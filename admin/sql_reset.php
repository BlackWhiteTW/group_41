<?php
require __DIR__ . '/../includes/admin_auth.php';

$message = '';
$status = 'info';
$ready = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../includes/csrf.php';
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $message = '表單驗證失敗，請重新整理後再試。';
        $status = 'error';
    } else {
        $sql_file = __DIR__ . '/../group_41.sql';
        if (!file_exists($sql_file)) {
            $message = '找不到 group_41.sql';
            $status = 'error';
        } else {
            $sql = file_get_contents($sql_file);
            if ($sql === false) {
                $message = '無法讀取 group_41.sql';
                $status = 'error';
            } else {
                $stmts = preg_split('/;\s*\n/', $sql);
                try {
                    foreach ($stmts as $stmt) {
                        $stmt = trim($stmt);
                        if ($stmt === '') {
                            continue;
                        }
                        $pdo->exec($stmt);
                    }
                    $message = '資料庫已成功重新匯入（group_41.sql）。';
                    $status = 'success';
                } catch (PDOException $e) {
                    $message = '匯入失敗，請檢查 SQL 檔案格式是否正確。';
                    $status = 'error';
                }
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
        <?php $base_url = '../'; require __DIR__ . '/../includes/header.php'; ?>

        <?php require __DIR__ . '/../includes/right.php'; ?>

        <main class="section">
            <div class="container">
                <h1>安裝資料庫（管理區）</h1>
                <p class="muted">執行 group_41.sql 以建立 / 重建資料表。此操作無法復原，請謹慎使用。</p>
                <?php if ($status === 'error') : ?>
                    <div class="error"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <div class="panel" style="padding: 20px">
                    <p class="muted"><?php echo $message !== '' ? htmlspecialchars($message) : '請點擊下方按鈕確認執行 group_41.sql。'; ?></p>
                    <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap">
                        <a class="btn btn-ghost" href="./admin_index.php">返回控制台</a>
                        <?php if ($status !== 'success') : ?>
                            <form method="post" action="./sql_reset.php" style="display: inline">
                                <?php
                                require_once __DIR__ . '/../includes/csrf.php';
                                echo csrf_field();
                                ?>
                                <button class="btn btn-primary" type="submit" onclick="return confirm('確定要重新匯入 group_41.sql 嗎？所有資料將被覆蓋。');">確認匯入</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>

        <footer class="footer container">社團表單系統</footer>
        <script src="../js/app.js"></script>
    </body>
</html>

<?php
exit();
