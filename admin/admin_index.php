<?php
require __DIR__ . '/../includes/admin_auth.php';

$errors = [];
$stats = [
    'users' => 0,
    'clubs' => 0,
    'forms' => 0,
    'submissions' => 0
];

try {
    $stats['users'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['clubs'] = (int) $pdo->query('SELECT COUNT(*) FROM clubs')->fetchColumn();
    $stats['forms'] = (int) $pdo->query('SELECT COUNT(*) FROM forms')->fetchColumn();
    $stats['submissions'] = (int) $pdo->query('SELECT COUNT(*) FROM form_submissions')->fetchColumn();
} catch (Throwable $e) {
    $errors[] = '管理資訊載入失敗，請稍後再試。';
}
?>
<!doctype html>
<html lang="zh-Hant">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>管理控制台 | 社團表單系統</title>
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

        <main class="section">
            <div class="container" style="display: grid; gap: 20px; grid-template-columns: minmax(0, 1fr); align-items: start">
                <?php require __DIR__ . '/../includes/right.php'; ?>

                <div>
                    <h1>管理控制台</h1>
                    <p class="muted">管理員專用功能與資料檢視。</p>
                    <?php if (!empty($errors)) : ?>
                        <div class="error">
                            <ul>
                                <?php foreach ($errors as $e) : ?>
                                    <li><?php echo htmlspecialchars($e); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="panel" style="padding: 20px; margin-bottom: 16px">
                        <h2 style="margin-top: 0">系統概況</h2>
                        <div style="display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr))">
                            <div class="stat">
                                <div class="muted">使用者</div>
                                <strong><?php echo number_format($stats['users']); ?></strong>
                            </div>
                            <div class="stat">
                                <div class="muted">社團</div>
                                <strong><?php echo number_format($stats['clubs']); ?></strong>
                            </div>
                            <div class="stat">
                                <div class="muted">表單</div>
                                <strong><?php echo number_format($stats['forms']); ?></strong>
                            </div>
                            <div class="stat">
                                <div class="muted">填寫紀錄</div>
                                <strong><?php echo number_format($stats['submissions']); ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="panel" style="padding: 20px">
                        <h2 style="margin-top: 0">管理工具</h2>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px">
                            <a class="btn btn-primary" href="./user_CRUD.php">使用者管理</a>
                            <a class="btn btn-ghost" href="./forms_CRUD.php">表單管理</a>
                            <a class="btn btn-ghost" href="./clubs_CRUD.php">社團管理</a>
                            <a class="btn btn-ghost" href="./activity_log.php">活動記錄</a>
                            <a class="btn btn-ghost" href="./sql_view.php">SQL 資料檢視</a>
                            <a class="btn btn-ghost" href="./sql_reset.php">重新匯入資料庫</a>
                            <a class="btn btn-ghost" href="./test.php">簡易測試</a>
                        </div>
                        <p class="muted" style="margin-top: 12px">重新匯入資料庫將執行 group_41.sql，請確認後再使用。</p>
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
