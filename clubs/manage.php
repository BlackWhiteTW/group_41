<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/header.php';

if (!is_logged_in()) {
    redirect_to_login();
}

if (!in_array($current_user['role'], ['club_officer', 'admin'])) {
    redirect_to_login();
}

$success = '';
$error = '';
$is_admin = ($current_user['role'] === 'admin');
$managed_club = null;
$clubs_for_admin = [];

try {
    if ($is_admin) {
        $sql = "SELECT c.id, c.name, c.owner_user_id, u.username AS owner_username
                FROM clubs c
                LEFT JOIN users u ON c.owner_user_id = u.id
                ORDER BY c.name ASC";
        $stmt = $pdo->query($sql);
        $clubs_for_admin = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $selected_club_id = (int)($_GET['club_id'] ?? 0);
        if ($selected_club_id <= 0 && !empty($clubs_for_admin)) {
            $selected_club_id = (int)$clubs_for_admin[0]['id'];
        }

        foreach ($clubs_for_admin as $club_item) {
            if ((int)$club_item['id'] === $selected_club_id) {
                $managed_club = $club_item;
                break;
            }
        }
    } else {
        $sql = "SELECT c.id, c.name, c.owner_user_id, u.username AS owner_username
                FROM clubs c
                LEFT JOIN users u ON c.owner_user_id = u.id
                WHERE c.owner_user_id = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$current_user['id']]);
        $managed_club = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $managed_club = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    $target_user_id = (int)($_POST['target_user_id'] ?? 0);
    $club_id = (int)($_POST['club_id'] ?? 0);

    $action_club = null;
    if ($club_id > 0) {
        try {
            $sql = "SELECT c.id, c.name, c.owner_user_id, u.username AS owner_username
                    FROM clubs c
                    LEFT JOIN users u ON c.owner_user_id = u.id
                    WHERE c.id = ?
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$club_id]);
            $action_club = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $action_club = null;
        }
    }

    if (!verify_csrf_token($token)) {
        $error = '表單已過期，請重新整理再試一次';
    } elseif (!$action_club) {
        $error = '找不到指定社團';
    } elseif (!$is_admin && (int)$action_club['owner_user_id'] !== (int)$current_user['id']) {
        $error = '你不是此社團擁有者，無法執行此操作';
    } elseif (!$target_user_id) {
        $error = '無效的目標用戶';
    } else {
        try {
            $pdo->beginTransaction();

            $sql = "SELECT id, username, role, club_category FROM users WHERE id = ? FOR UPDATE";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$target_user_id]);
            $target_user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$target_user) {
                throw new Exception('找不到目標用戶');
            }

            if ($target_user['club_category'] !== $action_club['name']) {
                throw new Exception('只能管理同社團成員');
            }

            if ((int)$action_club['owner_user_id'] === (int)$target_user['id'] && $action !== 'transfer_owner') {
                throw new Exception('社團擁有者無法被升降職');
            }

            if ($target_user['role'] === 'admin') {
                throw new Exception('系統管理員不可在此頁面調整身份');
            }

            if ($action === 'promote_officer') {
                $sql = "UPDATE users SET role = 'club_officer' WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$target_user_id]);
                $success = '已將 ' . $target_user['username'] . ' 設為社團幹部';
            } elseif ($action === 'demote_member') {
                $sql = "UPDATE users SET role = 'member' WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$target_user_id]);
                $success = '已將 ' . $target_user['username'] . ' 調整為成員';
            } elseif ($action === 'transfer_owner') {
                if ((int)$target_user['id'] === (int)$action_club['owner_user_id']) {
                    throw new Exception('該用戶已是社團擁有者');
                }

                $sql = "UPDATE users SET role = 'club_officer' WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$target_user_id]);

                $sql = "UPDATE clubs SET owner_user_id = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$target_user_id, $action_club['id']]);

                $action_club['owner_user_id'] = $target_user_id;
                $success = '已將社團擁有者移轉給 ' . $target_user['username'];
            } else {
                throw new Exception('未知操作');
            }

            $pdo->commit();

            $redirect = '/group_41/clubs/manage.php';
            if ($is_admin) {
                $redirect .= '?club_id=' . (int)$action_club['id'];
                if ($action === 'transfer_owner') {
                    $redirect .= '&transferred=1';
                }
            } elseif ($action === 'transfer_owner') {
                $redirect .= '?transferred=1';
            }
            header('Location: ' . $redirect);
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

if (isset($_GET['transferred']) && $_GET['transferred'] == '1') {
    $success = '你已完成社團擁有者移轉，管理權限已更新';
}

$members = [];
if ($managed_club) {
    try {
        $sql = "SELECT id, username, email, role, created_at
                FROM users
                WHERE club_category = ?
                ORDER BY FIELD(role, 'admin', 'club_officer', 'member', 'guest'), created_at ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$managed_club['name']]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $members = [];
    }
}

$csrf_token = generate_csrf_token();
?>

<h2 class="mb-4">🏷️ 社團管理</h2>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo escape($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo escape($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($is_admin && !empty($clubs_for_admin)): ?>
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label for="club_id" class="form-label">管理社團</label>
                    <select class="form-select" id="club_id" name="club_id" onchange="this.form.submit()">
                        <?php foreach ($clubs_for_admin as $club_item): ?>
                            <option value="<?php echo (int)$club_item['id']; ?>" <?php echo ($managed_club && (int)$managed_club['id'] === (int)$club_item['id']) ? 'selected' : ''; ?>>
                                <?php echo escape($club_item['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 text-muted small">
                    管理員可切換並操作所有社團。
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if (!$managed_club): ?>
    <div class="alert alert-warning" role="alert">
        <?php if ($is_admin): ?>
            目前沒有可管理的社團。
        <?php else: ?>
            目前你不是任何社團的擁有者，因此無法管理幹部身份。
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-2">社團資訊</h5>
            <p class="mb-1"><strong>社團名稱：</strong><?php echo escape($managed_club['name']); ?></p>
            <p class="mb-1"><strong>目前擁有者：</strong><?php echo escape($managed_club['owner_username'] ?? '未知'); ?></p>
            <p class="mb-0 text-muted">
                <?php if ($is_admin): ?>
                    你是管理員，可操作所有社團成員的幹部身份與擁有權。
                <?php else: ?>
                    你是目前的社團擁有者。只有你可以升降幹部或移轉擁有權。
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>帳號</th>
                    <th>Email</th>
                    <th>身份</th>
                    <th>加入時間</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $member): ?>
                    <?php $is_owner = ((int)$member['id'] === (int)$managed_club['owner_user_id']); ?>
                    <tr>
                        <td>
                            <strong><?php echo escape($member['username']); ?></strong>
                            <?php if ($is_owner): ?>
                                <span class="badge bg-danger ms-2">擁有者</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo escape($member['email']); ?></td>
                        <td>
                            <?php if ($member['role'] === 'club_officer'): ?>
                                <span class="badge bg-warning text-dark">社團幹部</span>
                            <?php elseif ($member['role'] === 'member'): ?>
                                <span class="badge bg-info text-dark">成員</span>
                            <?php elseif ($member['role'] === 'admin'): ?>
                                <span class="badge bg-danger">管理員</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?php echo escape($member['role']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($member['created_at'])); ?></td>
                        <td>
                            <?php if ($is_owner): ?>
                                <span class="text-muted small">擁有者不可被他人修改</span>
                            <?php elseif ($member['role'] === 'admin'): ?>
                                <span class="text-muted small">管理員不可調整</span>
                            <?php else: ?>
                                <div class="d-flex gap-2">
                                    <?php if ($member['role'] === 'member'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo escape($csrf_token); ?>">
                                            <input type="hidden" name="club_id" value="<?php echo (int)$managed_club['id']; ?>">
                                            <input type="hidden" name="action" value="promote_officer">
                                            <input type="hidden" name="target_user_id" value="<?php echo $member['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success">升為幹部</button>
                                        </form>
                                    <?php elseif ($member['role'] === 'club_officer'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo escape($csrf_token); ?>">
                                            <input type="hidden" name="club_id" value="<?php echo (int)$managed_club['id']; ?>">
                                            <input type="hidden" name="action" value="demote_member">
                                            <input type="hidden" name="target_user_id" value="<?php echo $member['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">降為成員</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="POST" class="d-inline transfer-owner-form" data-name="<?php echo escape($member['username']); ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo escape($csrf_token); ?>">
                                        <input type="hidden" name="club_id" value="<?php echo (int)$managed_club['id']; ?>">
                                        <input type="hidden" name="action" value="transfer_owner">
                                        <input type="hidden" name="target_user_id" value="<?php echo $member['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">移轉擁有權</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
document.querySelectorAll('.transfer-owner-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        const targetName = form.dataset.name || '該用戶';
        const ok = confirm('確認要把社團擁有者移轉給「' + targetName + '」嗎？移轉後你將失去擁有者權限。');
        if (!ok) {
            e.preventDefault();
        }
    });
});
</script>

<?php include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/footer.php'; ?>
