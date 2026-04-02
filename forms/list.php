<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/header.php';

try {
    // 如果已登入，顯示更多表單選項
    if (is_logged_in()) {
        if ($current_user['role'] === 'admin') {
            // 管理員可瀏覽所有已發布表單（公開+私人）
            $sql = "SELECT f.*, u.username, COUNT(fs.id) as submission_count 
                    FROM forms f 
                    LEFT JOIN users u ON f.creator_id = u.id 
                    LEFT JOIN form_submissions fs ON f.id = fs.form_id
                    WHERE f.status = 'published'
                    GROUP BY f.id
                    ORDER BY f.created_at DESC";
            $stmt = $pdo->query($sql);
        } else {
            // 一般登入用戶：公開表單 + 自己社團的私人表單
            $sql = "SELECT f.*, u.username, COUNT(fs.id) as submission_count 
                    FROM forms f 
                    LEFT JOIN users u ON f.creator_id = u.id 
                    LEFT JOIN form_submissions fs ON f.id = fs.form_id
                    WHERE f.status = 'published' 
                    AND (
                        f.form_type = 'public' 
                        OR (f.form_type = 'club_only' AND f.target_club_category = ?)
                    )
                    GROUP BY f.id
                    ORDER BY f.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$current_user['club_category']]);
        }
    } else {
        // 未登入只顯示公開表單
        $sql = "SELECT f.*, u.username, COUNT(fs.id) as submission_count 
                FROM forms f 
                LEFT JOIN users u ON f.creator_id = u.id 
                LEFT JOIN form_submissions fs ON f.id = fs.form_id
                WHERE f.status = 'published' AND f.form_type = 'public'
                GROUP BY f.id
                ORDER BY f.created_at DESC";
        $stmt = $pdo->query($sql);
    }
    
    $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $forms = [];
}
?>

<h2 class="mb-4">📋 可用表單</h2>

<?php if (empty($forms)): ?>
    <div class="alert alert-info">目前沒有可用的表單</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover" id="formsTable">
            <thead class="table-light">
                <tr>
                    <th>表單標題</th>
                    <th>出題者</th>
                    <th>類型</th>
                    <th>填寫數</th>
                    <th>建立日期</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($forms as $form): ?>
                    <tr>
                        <td>
                            <strong><?php echo escape($form['title']); ?></strong><br>
                            <small class="text-muted"><?php echo escape(substr($form['description'] ?? '', 0, 50)); ?>
                                <?php if (strlen($form['description'] ?? '') > 50): ?>...<?php endif; ?>
                            </small>
                        </td>
                        <td><?php echo escape($form['username']); ?></td>
                        <td>
                            <?php if ($form['form_type'] === 'public'): ?>
                                <span class="badge bg-info">公開</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">私人</span><br>
                                <small><?php echo escape($form['target_club_category']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $form['submission_count']; ?></td>
                        <td><?php echo date('Y-m-d', strtotime($form['created_at'])); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="/group_41/forms/view.php?id=<?php echo $form['id']; ?>" 
                                   class="btn btn-primary">填寫</a>
                                <?php if (is_logged_in() && in_array($current_user['role'], ['admin', 'club_officer'])): ?>
                                    <a href="/group_41/forms/statistics.php?id=<?php echo $form['id']; ?>" 
                                       class="btn btn-outline-info">統計</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script src="/group_41/js/jquery-3.7.1.min.js"></script>
    <script src="/group_41/js/datatables.min.js"></script>
    
    <script>
    $(document).ready(function() {
        const dataTableZhHant = {
            processing: '處理中...',
            loadingRecords: '載入中...',
            lengthMenu: '每頁顯示 _MENU_ 筆',
            zeroRecords: '沒有符合的資料',
            info: '顯示第 _START_ 到 _END_ 筆，共 _TOTAL_ 筆',
            infoEmpty: '顯示第 0 到 0 筆，共 0 筆',
            infoFiltered: '(從 _MAX_ 筆資料中過濾)',
            search: '搜尋：',
            paginate: {
                first: '第一頁',
                previous: '上一頁',
                next: '下一頁',
                last: '最後一頁'
            },
            aria: {
                sortAscending: ': 升冪排序',
                sortDescending: ': 降冪排序'
            }
        };

        $('#formsTable').DataTable({
            language: dataTableZhHant,
            pageLength: 15
        });
    });
    </script>
<?php endif; ?>

<?php include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/footer.php'; ?>

