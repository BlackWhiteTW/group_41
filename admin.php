<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/header.php';

// 檢查是否為管理員
if ($current_user['role'] !== 'admin') {
    redirect_to_login();
}

// 處理刪除用戶
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete_user') {
    $user_id = $_POST['user_id'] ?? 0;
    
    if ($user_id !== $current_user['id']) {
        try {
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $success = '用戶已刪除';
        } catch (Exception $e) {
            $error = '刪除失敗';
        }
    }
}

try {
    $sql = "SELECT * FROM users ORDER BY created_at DESC";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $sql = "SELECT COUNT(*) as count FROM forms";
    $stmt = $pdo->query($sql);
    $total_forms = $stmt->fetch()['count'];
    
    $sql = "SELECT COUNT(*) as count FROM form_submissions";
    $stmt = $pdo->query($sql);
    $total_submissions = $stmt->fetch()['count'];
    
    $sql = "SELECT COUNT(*) as count FROM users";
    $stmt = $pdo->query($sql);
    $total_users = $stmt->fetch()['count'];
} catch (Exception $e) {
    $users = [];
}
?>

<h2 class="mb-4">⚙️ 系統管理員面板</h2>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">總用戶數</h5>
                <h3><?php echo $total_users; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">總表單數</h5>
                <h3><?php echo $total_forms; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">總填寫次數</h5>
                <h3><?php echo $total_submissions; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h5 class="card-title">平均填寫</h5>
                <h3><?php echo $total_forms > 0 ? round($total_submissions / $total_forms, 1) : 0; ?></h3>
            </div>
        </div>
    </div>
</div>

<hr class="my-4">

<h3 class="mb-3">👥 用戶管理</h3>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-hover" id="usersTable">
        <thead class="table-light">
            <tr>
                <th>帳號</th>
                <th>電子郵件</th>
                <th>社團分類</th>
                <th>身份</th>
                <th>注冊日期</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><strong><?php echo escape($user['username']); ?></strong></td>
                    <td><?php echo escape($user['email']); ?></td>
                    <td><?php echo escape($user['club_category']); ?></td>
                    <td>
                        <?php
                        $role_badges = [
                            'admin' => '<span class="badge bg-danger">管理員</span>',
                            'club_officer' => '<span class="badge bg-warning">社團幹部</span>',
                            'member' => '<span class="badge bg-info">成員</span>',
                            'guest' => '<span class="badge bg-secondary">訪客</span>'
                        ];
                        echo $role_badges[$user['role']] ?? '';
                        ?>
                    </td>
                    <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                    <td>
                        <?php if ($user['id'] !== $current_user['id']): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger delete-user" 
                                    data-user-id="<?php echo $user['id']; ?>"
                                    data-user-name="<?php echo escape($user['username']); ?>">
                                🗑️ 刪除
                            </button>
                        <?php else: ?>
                            <span class="text-muted small">（當前帳號）</span>
                        <?php endif; ?>
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

    $('#usersTable').DataTable({
        language: dataTableZhHant,
        pageLength: 15
    });
    
    $('.delete-user').on('click', function() {
        const userId = $(this).data('user-id');
        const userName = $(this).data('user-name');
        
        Swal.fire({
            title: '確定要刪除？',
            text: `您確定要刪除用戶 "${userName}" 嗎？此操作無法復原。`,
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
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `);
                $('body').append(form);
                form.submit();
            }
        });
    });
});
</script>

<?php include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/footer.php'; ?>

