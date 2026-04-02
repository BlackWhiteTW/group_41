<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/header.php';

// 獲取公開表單列表
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = max(0, ($page - 1) * $per_page);

try {
    // 獲取公開表單
    $sql = "SELECT f.*, u.username, COUNT(fs.id) as submission_count 
            FROM forms f 
            LEFT JOIN users u ON f.creator_id = u.id 
            LEFT JOIN form_submissions fs ON f.id = fs.form_id
            WHERE f.status = 'published' AND f.form_type = 'public'
            GROUP BY f.id
            ORDER BY f.created_at DESC
            LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
        $stmt->bindValue(1, (int)$per_page, PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
    $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 獲取總數
    $sql = "SELECT COUNT(*) as total FROM forms WHERE status = 'published' AND form_type = 'public'";
    $stmt = $pdo->query($sql);
    $total_rows = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_rows / $per_page);
} catch (Exception $e) {
    $forms = [];
    $total_pages = 1;
}
?>

<div class="row mb-5">
    <div class="col-md-8">
        <div class="jumbotron bg-primary text-white p-5 rounded">
            <h1 class="display-4">🎓 歡迎使用社團表單系統</h1>
            <p class="lead">校內活動問卷統計平台 - 快速建立和發布活動滿意度問卷</p>
            <hr class="my-4">
            <p>無論您是社團幹部還是普通成員，都可以輕鬆創建表單、發起問卷調查。</p>
            <?php if (!is_logged_in()): ?>
                <a class="btn btn-light btn-lg" href="/group_41/login.php" role="button">
                    登入帳號
                </a>
                <a class="btn btn-outline-light btn-lg ms-2" href="/group_41/register.php" role="button">
                    註冊帳號
                </a>
            <?php else: ?>
                <a class="btn btn-light btn-lg" href="/group_41/forms/list.php" role="button">
                    瀏覽所有表單
                </a>
                <?php if (in_array($current_user['role'], ['club_officer', 'admin'])): ?>
                    <a class="btn btn-outline-light btn-lg ms-2" href="/group_41/forms/create.php" role="button">
                        建立新表單
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">📊 系統統計</h5>
                <hr>
                <p class="mb-1">
                    <strong>總表單數：</strong> 
                    <?php 
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM forms");
                        echo $stmt->fetch()['count'];
                    ?>
                </p>
                <p class="mb-1">
                    <strong>已發布表單：</strong> 
                    <?php 
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM forms WHERE status = 'published'");
                        echo $stmt->fetch()['count'];
                    ?>
                </p>
                <p class="mb-0">
                    <strong>總填寫次數：</strong> 
                    <?php 
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM form_submissions");
                        echo $stmt->fetch()['count'];
                    ?>
                </p>
            </div>
        </div>
    </div>
</div>

<h2 class="mb-4">📋 公開的表單</h2>

<?php if (empty($forms)): ?>
    <div class="alert alert-info">目前沒有公開的表單</div>
<?php else: ?>
    <div class="row">
        <?php foreach ($forms as $form): ?>
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo escape($form['title']); ?>
                        </h5>
                        <p class="card-text text-muted small">
                            出題者: <strong><?php echo escape($form['username']); ?></strong><br>
                            已填寫: <strong><?php echo $form['submission_count']; ?></strong> 份
                        </p>
                        <?php if ($form['description']): ?>
                            <p class="card-text">
                                <?php echo escape(substr($form['description'], 0, 100)); ?>
                                <?php if (strlen($form['description']) > 100): ?>...<?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <div class="d-flex gap-2">
                            <a href="/group_41/forms/view.php?id=<?php echo $form['id']; ?>" 
                                class="btn btn-sm btn-primary">查看表單</a>
                            <?php if (is_logged_in() && in_array($current_user['role'], ['admin', 'club_officer'])): ?>
                                <a href="/group_41/forms/statistics.php?id=<?php echo $form['id']; ?>" 
                                    class="btn btn-sm btn-outline-info">統計結果</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer text-muted small">
                        建立於: <?php echo date('Y-m-d', strtotime($form['created_at'])); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-5">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">上一頁</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">下一頁</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/footer.php'; ?>

