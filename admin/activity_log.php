<?php
require __DIR__ . '/../includes/admin_auth.php';

$errors = [];
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

$activity_list = [];
$total = 0;

try {
    $total = (int) $pdo->query('SELECT COUNT(*) FROM club_activity_log')->fetchColumn();

    $stmt = $pdo->prepare('SELECT al.*, u.username, c.name AS club_name FROM club_activity_log al JOIN users u ON u.id = al.user_id JOIN clubs c ON c.id = al.club_id ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $activity_list = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = '活動記錄載入失敗，請稍後再試。';
}

$total_pages = max(1, (int) ceil($total / $per_page));
?>
<!doctype html>
<html lang="zh-Hant">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>活動記錄 | 管理</title>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;600;700&display=swap" rel="stylesheet" />
        <link rel="stylesheet" href="../css/app.css" />
    </head>
    <body>
        <?php $base_url = '../'; require __DIR__ . '/../includes/header.php'; ?>
        <?php require __DIR__ . '/../includes/right.php'; ?>

        <main class="section">
            <div class="container">
                <h1>活動記錄</h1>
                <p class="muted">全系統社團操作稽核日誌（最近 <?php echo number_format($total); ?> 筆）。</p>

                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin: 16px 0 20px">
                    <a class="btn btn-ghost" href="./admin_index.php">回管理控制台</a>
                    <a class="btn btn-ghost" href="./clubs_CRUD.php">社團管理</a>
                </div>

                <?php if (!empty($errors)) : ?>
                    <div class="error"><ul><?php foreach ($errors as $e) : ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>

                <?php if (empty($activity_list)) : ?>
                    <div class="panel" style="padding: 20px"><p class="muted">尚無活動記錄。</p></div>
                <?php else : ?>
                    <div class="panel" style="padding: 0; overflow: auto; margin-bottom: 16px">
                        <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 13px">
                            <thead>
                                <tr>
                                    <th style="text-align: left; padding: 10px 12px; border-bottom: 2px solid #2a7d4f; background: #f5faf7; white-space: nowrap">時間</th>
                                    <th style="text-align: left; padding: 10px 12px; border-bottom: 2px solid #2a7d4f; background: #f5faf7; white-space: nowrap">社團</th>
                                    <th style="text-align: left; padding: 10px 12px; border-bottom: 2px solid #2a7d4f; background: #f5faf7; white-space: nowrap">操作者</th>
                                    <th style="text-align: left; padding: 10px 12px; border-bottom: 2px solid #2a7d4f; background: #f5faf7; white-space: nowrap">操作</th>
                                    <th style="text-align: left; padding: 10px 12px; border-bottom: 2px solid #2a7d4f; background: #f5faf7">明細</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activity_list as $idx => $log) : ?>
                                    <tr style="background: <?php echo $idx % 2 === 0 ? '#fff' : '#f9fbfa'; ?>">
                                        <td style="padding: 8px 12px; border-bottom: 1px solid #e4efe8; white-space: nowrap; vertical-align: top"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                        <td style="padding: 8px 12px; border-bottom: 1px solid #e4efe8; white-space: nowrap; vertical-align: top"><?php echo htmlspecialchars($log['club_name']); ?></td>
                                        <td style="padding: 8px 12px; border-bottom: 1px solid #e4efe8; white-space: nowrap; vertical-align: top"><?php echo htmlspecialchars($log['username']); ?></td>
                                        <td style="padding: 8px 12px; border-bottom: 1px solid #e4efe8; white-space: nowrap; vertical-align: top"><?php echo htmlspecialchars($log['action']); ?></td>
                                        <td style="padding: 8px 12px; border-bottom: 1px solid #e4efe8; vertical-align: top; word-break: break-all"><?php echo !empty($log['details']) ? htmlspecialchars($log['details']) : '<span style="color:#c0c8c3;font-style:italic">—</span>'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1) : ?>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; margin-top: 12px">
                            <?php if ($page > 1) : ?>
                                <a class="btn btn-ghost" href="./activity_log.php?page=<?php echo $page - 1; ?>">上一頁</a>
                            <?php endif; ?>
                            <span class="muted" style="display: flex; align-items: center; padding: 0 8px">第 <?php echo $page; ?> / <?php echo $total_pages; ?> 頁</span>
                            <?php if ($page < $total_pages) : ?>
                                <a class="btn btn-ghost" href="./activity_log.php?page=<?php echo $page + 1; ?>">下一頁</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>

        <footer class="footer container">社團表單系統</footer>
        <script src="../js/app.js"></script>
    </body>
</html>
<?php exit(); ?>
