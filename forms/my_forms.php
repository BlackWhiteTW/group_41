<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/header.php';

// 檢查權限
if (!in_array($current_user['role'], ['club_officer', 'admin'])) {
    redirect_to_login();
}

// 刪除表單
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete') {
    $form_id = $_POST['form_id'] ?? 0;
    
    try {
        $sql = "DELETE FROM forms WHERE id = ? AND creator_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$form_id, $current_user['id']]);
        
        header('Location: /group_41/forms/my_forms.php?deleted=1');
        exit();
    } catch (Exception $e) {
        echo '錯誤：' . $e->getMessage();
    }
}

try {
    $sql = "SELECT f.*, COUNT(fs.id) as submission_count 
            FROM forms f 
            LEFT JOIN form_submissions fs ON f.id = fs.form_id
            WHERE f.creator_id = ?
            GROUP BY f.id
            ORDER BY f.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$current_user['id']]);
    $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $forms = [];
}
?>

<h2 class="mb-4">📋 我的表單</h2>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        表單已刪除
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="mb-3">
    <a href="/group_41/forms/create.php" class="btn btn-primary">
        ➕ 建立新表單
    </a>
</div>

<?php if (empty($forms)): ?>
    <div class="alert alert-info">
        <p>您還沒有建立任何表單 <a href="/group_41/forms/create.php">立即建立</a></p>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover" id="formsTable">
            <thead class="table-light">
                <tr>
                    <th>表單標題</th>
                    <th>狀態</th>
                    <th>類型</th>
                    <th>題數</th>
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
                            <small class="text-muted"><?php echo escape(substr($form['description'] ?? '', 0, 50)); ?></small>
                        </td>
                        <td>
                            <?php if ($form['status'] === 'draft'): ?>
                                <span class="badge bg-secondary">草稿</span>
                            <?php elseif ($form['status'] === 'published'): ?>
                                <span class="badge bg-success">已發布</span>
                            <?php else: ?>
                                <span class="badge bg-danger">已關閉</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo ($form['form_type'] === 'public') ? '公開' : '私人（社團內）'; ?>
                        </td>
                        <td>
                            <?php
                            $sql = "SELECT COUNT(*) as count FROM form_questions WHERE form_id = ?";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$form['id']]);
                            echo $stmt->fetch()['count'];
                            ?>
                        </td>
                        <td><?php echo $form['submission_count']; ?></td>
                        <td><?php echo date('Y-m-d', strtotime($form['created_at'])); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="/group_41/forms/view.php?id=<?php echo $form['id']; ?>" 
                                   class="btn btn-outline-info" title="預覽">👁️</a>
                                <a href="/group_41/forms/create.php?id=<?php echo $form['id']; ?>" 
                                   class="btn btn-outline-primary" title="編輯">✏️</a>
                                <a href="/group_41/forms/statistics.php?id=<?php echo $form['id']; ?>" 
                                   class="btn btn-outline-warning" title="統計">📊</a>
                                <button type="button" class="btn btn-outline-danger delete-form" 
                                        data-form-id="<?php echo $form['id']; ?>" 
                                        data-form-title="<?php echo escape($form['title']); ?>"
                                        title="刪除">🗑️</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script src="/group_41/js/jquery-3.7.1.min.js"></script>
    <script src="/group_41/js/sweetalert2@11.js"></script>
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
            pageLength: 10
        });
        
        $('.delete-form').on('click', function() {
            const formId = $(this).data('form-id');
            const formTitle = $(this).data('form-title');
            
            Swal.fire({
                title: '確定要刪除？',
                text: `您確定要刪除表單 "${formTitle}" 嗎？此操作無法復原。`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '是，刪除',
                cancelButtonText: '取消'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = $('<form method="POST" style="display:none;"></form>');
                    form.html(`
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="form_id" value="${formId}">
                    `);
                    $('body').append(form);
                    form.submit();
                }
            });
        });
    });
    </script>
<?php endif; ?>

<?php include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/footer.php'; ?>

