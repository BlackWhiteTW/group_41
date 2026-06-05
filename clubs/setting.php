<?php
session_start();
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/csrf.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$errors = [];
$success_msg = '';

if (!$user_raw) {
    header('Location: ../login.php');
    exit;
}

try {
    $pdo = get_db();
    $user_stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
    $user_stmt->execute([':u' => $user_raw]);
    $current_user = $user_stmt->fetch();

    $clubs = [];
    $mem_stmt = $pdo->prepare('SELECT club_id FROM club_memberships WHERE user_id = :id');
    $mem_stmt->execute([':id' => $current_user['id']]);
    $member_club_ids = array_map('intval', array_column($mem_stmt->fetchAll(), 'club_id'));

    if (!empty($member_club_ids)) {
        $placeholders = implode(',', array_fill(0, count($member_club_ids), '?'));
        $club_stmt = $pdo->prepare("SELECT c.id, c.name FROM clubs c WHERE c.id IN ($placeholders) ORDER BY c.name ASC");
        $club_stmt->execute($member_club_ids);
        $clubs = $club_stmt->fetchAll();
    }

    $selected_id = isset($_GET['id']) ? (int) $_GET['id'] : (count($clubs) > 0 ? (int)$clubs[0]['id'] : 0);
    $club = null;
    $members = [];
    $forms = [];
    $pending_invitations = [];
    $pending_requests = [];
    $announcements = [];
    $activity_log = [];

    if ($selected_id > 0) {
        $stmt = $pdo->prepare('SELECT c.* FROM clubs c WHERE c.id = :id AND EXISTS (SELECT 1 FROM club_memberships m WHERE m.club_id = c.id AND m.user_id = :u)');
        $stmt->execute([':id' => $selected_id, ':u' => $current_user['id']]);
        $club = $stmt->fetch();

        if ($club) {
            $is_admin = ($current_user['role'] ?? '') === 'admin';
            $officer_roles = ['owner', 'club_officer'];
            $user_member_stmt = $pdo->prepare('SELECT role FROM club_memberships WHERE club_id = :c AND user_id = :u LIMIT 1');
            $user_member_stmt->execute([':c' => $club['id'], ':u' => $current_user['id']]);
            $user_member = $user_member_stmt->fetch();
            $is_officer = $user_member && in_array($user_member['role'], $officer_roles, true);
            if (!$is_admin && !$is_officer) {
                $_SESSION['flash_error'] = '你沒有管理此社團的權限。';
                header('Location: manage.php?id=' . (int)$club['id']);
                exit;
            }

            $member_stmt = $pdo->prepare('SELECT u.id, u.username, m.role FROM club_memberships m JOIN users u ON u.id = m.user_id WHERE m.club_id = :club ORDER BY m.role DESC, u.username ASC');
            $member_stmt->execute([':club' => $club['id']]);
            $members = $member_stmt->fetchAll();

            $form_stmt = $pdo->prepare('SELECT id, title, status, form_type FROM forms WHERE club_id = :club ORDER BY created_at DESC');
            $form_stmt->execute([':club' => $club['id']]);
            $forms = $form_stmt->fetchAll();

            $invitations = $pdo->prepare('SELECT ci.id, ci.status, ci.created_at, u.username FROM club_invitations ci JOIN users u ON u.id = ci.user_id WHERE ci.club_id = :c AND ci.status = "pending" ORDER BY ci.created_at DESC');
            $invitations->execute([':c' => $club['id']]);
            $pending_invitations = $invitations->fetchAll();

            $requests = $pdo->prepare('SELECT cr.id, cr.status, cr.created_at, u.username FROM club_join_requests cr JOIN users u ON u.id = cr.user_id WHERE cr.club_id = :c AND cr.status = "pending" ORDER BY cr.created_at ASC');
            $requests->execute([':c' => $club['id']]);
            $pending_requests = $requests->fetchAll();

            $ann_stmt = $pdo->prepare('SELECT a.id, a.title, a.content, a.created_at, u.username FROM club_announcements a JOIN users u ON u.id = a.user_id WHERE a.club_id = :c ORDER BY a.created_at DESC LIMIT 20');
            $ann_stmt->execute([':c' => $club['id']]);
            $announcements = $ann_stmt->fetchAll();

            $log_stmt = $pdo->prepare('SELECT al.action, al.details, al.created_at, u.username FROM club_activity_log al JOIN users u ON u.id = al.user_id WHERE al.club_id = :c ORDER BY al.created_at DESC LIMIT 50');
            $log_stmt->execute([':c' => $club['id']]);
            $activity_log = $log_stmt->fetchAll();
        }
    }
} catch (Throwable $e) {
    $errors[] = '系統錯誤：' . $e->getMessage();
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>社團設定 | 社團表單系統</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../css/app.css" />
</head>
<body>
    <?php $base_url = '../'; require __DIR__ . '/../includes/header.php'; ?>

    <main class="section">
        <div class="container setting-layout">
            <?php require __DIR__ . '/../includes/right.php'; ?>

            <div>
                <h1>社團設定</h1>
                <?php if (!empty($errors)) : ?>
                    <div class="error"><ul><?php foreach ($errors as $error) : ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>

                <?php if (empty($clubs)) : ?>
                    <div class="panel" style="padding: 40px; text-align: center;">
                        <p class="muted">您尚未加入任何社團，無法進行設定。</p>
                    </div>
                <?php else: ?>
                    <div class="panel club-selector">
                        <label for="club-select">選擇社團：</label>
                        <form method="GET" action="setting.php" style="display:contents">
                            <select id="club-select" name="id" onchange="this.form.submit()">
                                <?php foreach($clubs as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $selected_id == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>

                    <?php if ($club): ?>
                        <h2 style="margin-top: 8px;"><?php echo htmlspecialchars($club['name']); ?></h2>

                        <?php if ($club['owner_user_id'] == $current_user['id'] || ($current_user['role'] ?? '') === 'admin'): ?>
                        <div style="margin-bottom:16px">
                            <form method="POST" action="update_setting.php" style="display:inline">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                <input type="hidden" name="action" value="delete_club">
                                <button type="submit" class="btn btn-small" style="background:#dc3545;color:#fff" data-confirm="確定要刪除社團「<?php echo htmlspecialchars($club['name']); ?>」嗎？此操作無法復原！">刪除社團</button>
                            </form>
                        </div>
                        <?php endif; ?>

                        <div class="mgmt-grid">
                            <!-- 基本設定 -->
                            <section class="panel">
                                <div class="section-title"><h3>基本設定</h3></div>
                                <form method="POST" action="update_setting.php">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                    <input type="hidden" name="action" value="update_club_settings">
                                    <div class="field">
                                        <label for="club_name">社團名稱</label>
                                        <input id="club_name" name="club_name" required minlength="2" value="<?php echo htmlspecialchars($club['name']); ?>" />
                                    </div>
                                    <div class="field">
                                        <label for="club_desc">社團簡介</label>
                                        <textarea id="club_desc" name="description" rows="3" placeholder="介紹你的社團..."><?php echo htmlspecialchars($club['description'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="field">
                                        <label for="join_mode">加入模式</label>
                                        <select id="join_mode" name="join_mode">
                                            <option value="open" <?php echo ($club['join_mode'] ?? 'request') === 'open' ? 'selected' : ''; ?>>開放加入（任何人可直接加入）</option>
                                            <option value="request" <?php echo ($club['join_mode'] ?? 'request') === 'request' ? 'selected' : ''; ?>>申請制（需幹部審核）</option>
                                            <option value="invite_only" <?php echo ($club['join_mode'] ?? '') === 'invite_only' ? 'selected' : ''; ?>>僅邀請（不接受申請）</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label for="visibility">社團可見性</label>
                                        <select id="visibility" name="visibility">
                                            <option value="public" <?php echo ($club['visibility'] ?? 'public') === 'public' ? 'selected' : ''; ?>>公開（所有人可見）</option>
                                            <option value="private" <?php echo ($club['visibility'] ?? '') === 'private' ? 'selected' : ''; ?>>私人（僅成員可見）</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-small">儲存設定</button>
                                </form>
                            </section>

                            <!-- 公告管理 -->
                            <section class="panel">
                                <div class="section-title"><h3>社團公告</h3></div>
                                <form method="POST" action="update_setting.php" style="margin-bottom:12px">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                    <input type="hidden" name="action" value="create_announcement">
                                    <div class="field">
                                        <label for="announce_title">公告標題</label>
                                        <input id="announce_title" name="announce_title" required placeholder="輸入公告標題" />
                                    </div>
                                    <div class="field">
                                        <label for="announce_content">公告內容</label>
                                        <textarea id="announce_content" name="announce_content" rows="2" placeholder="輸入公告內容"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-small">發布公告</button>
                                </form>
                                <?php if (!empty($announcements)): ?>
                                <div style="max-height:300px;overflow-y:auto">
                                    <?php foreach ($announcements as $ann): ?>
                                    <div style="padding:8px 0;border-bottom:1px solid #e4efe8">
                                        <strong><?php echo htmlspecialchars($ann['title']); ?></strong>
                                        <?php if (!empty($ann['content'])): ?>
                                            <p class="muted" style="margin:4px 0;font-size:0.88rem"><?php echo nl2br(htmlspecialchars($ann['content'])); ?></p>
                                        <?php endif; ?>
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <span class="meta" style="margin-top:0"><?php echo htmlspecialchars($ann['username']); ?> · <?php echo date('m/d H:i', strtotime($ann['created_at'])); ?></span>
                                            <form method="POST" action="update_setting.php" onsubmit="return confirm('確定刪除？')">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                                <input type="hidden" name="announce_id" value="<?php echo $ann['id']; ?>">
                                                <input type="hidden" name="action" value="delete_announcement">
                                                <button type="submit" class="btn btn-small btn-danger" style="font-size:0.75rem;padding:2px 6px">刪除</button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                    <p class="muted">尚無公告。</p>
                                <?php endif; ?>
                            </section>

                            <!-- 自行退出 -->
                            <?php if ($club['owner_user_id'] != $current_user['id']): ?>
                            <section class="panel">
                                <div class="section-title"><h3>退出社團</h3></div>
                                <form method="POST" action="update_setting.php">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                    <input type="hidden" name="action" value="leave_club">
                                    <button type="submit" class="btn btn-danger" data-confirm="確定要退出此社團嗎？">退出社團</button>
                                </form>
                            </section>
                            <?php endif; ?>

                            <!-- 成員管理 -->
                            <section class="panel">
                                <div class="section-title">
                                    <h3>成員與角色</h3>
                                </div>
                                <table class="data-table">
                                    <thead>
                                        <tr><th>使用者</th><th>角色</th><th>操作</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($members as $m): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($m['username']); ?></strong>
                                                    <?php if ($club['owner_user_id'] == $m['id']): ?>
                                                        <span class="badge-status badge-open" style="margin-left:6px;">持有人</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($club['owner_user_id'] != $m['id']): ?>
                                                        <form method="POST" action="update_setting.php" style="display:inline">
                                                            <?php echo csrf_field(); ?>
                                                            <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                                            <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                                            <input type="hidden" name="action" value="role">
                                                            <select name="role" onchange="this.form.submit()">
                                                                <option value="member" <?php echo $m['role'] == 'member' ? 'selected' : ''; ?>>成員</option>
                                                                <option value="club_officer" <?php echo $m['role'] == 'club_officer' ? 'selected' : ''; ?>>幹部</option>
                                                            </select>
                                                        </form>
                                                    <?php else: ?>
                                                        <span>持有者</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($club['owner_user_id'] == $current_user['id'] && $club['owner_user_id'] != $m['id']): ?>
                                                        <div class="action-group">
                                                            <form method="POST" action="update_setting.php" style="display:inline">
                                                                <?php echo csrf_field(); ?>
                                                                <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                                                <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                                                <input type="hidden" name="action" value="transfer">
                                                                <button type="submit" class="btn btn-small" data-confirm="確定將社團擁有權轉移給 <?php echo htmlspecialchars($m['username']); ?>？">轉移擁有權</button>
                                                            </form>
                                                            <form method="POST" action="update_setting.php" style="display:inline">
                                                                <?php echo csrf_field(); ?>
                                                                <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                                                <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                                                <input type="hidden" name="action" value="remove">
                                                                <button type="submit" class="btn btn-small btn-danger" data-confirm="確定移除成員 <?php echo htmlspecialchars($m['username']); ?>？">移除</button>
                                                            </form>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </section>

                            <!-- 邀請成員 -->
                            <section class="panel" style="margin-top:16px">
                                <div class="section-title"><h3>邀請成員</h3></div>
                                <form method="POST" action="update_setting.php" style="display:flex;gap:8px;align-items:end">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                    <input type="hidden" name="action" value="invite_member">
                                    <div class="field" style="margin-bottom:0;flex:1">
                                        <label for="invite_username">使用者名稱</label>
                                        <input id="invite_username" name="invite_username" required placeholder="輸入要邀請的使用者 ID" />
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-small">送出邀請</button>
                                </form>
                                <?php if (!empty($pending_invitations)): ?>
                                    <p class="muted" style="margin-top:8px">等待回覆：</p>
                                    <?php foreach ($pending_invitations as $inv): ?>
                                        <div class="meta" style="display:flex;align-items:center;gap:8px">
                                            <span><?php echo htmlspecialchars($inv['username']); ?> (<?php echo date('m/d', strtotime($inv['created_at'])); ?>)</span>
                                            <form method="POST" action="update_setting.php" style="display:inline">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                                <input type="hidden" name="invitation_id" value="<?php echo $inv['id']; ?>">
                                                <input type="hidden" name="action" value="cancel_invitation">
                                                <button type="submit" class="btn btn-small" style="background:#fce8e8;color:#b33;font-size:0.72rem;padding:2px 6px" data-confirm="確定取消邀請？">取消</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </section>

                            <!-- 加入申請 -->
                            <?php if (!empty($pending_requests)): ?>
                            <section class="panel" style="margin-top:16px">
                                <div class="section-title"><h3>待審核加入申請</h3></div>
                                <table class="data-table">
                                    <tbody>
                                        <?php foreach ($pending_requests as $req): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($req['username']); ?></strong></td>
                                            <td class="meta"><?php echo date('m/d', strtotime($req['created_at'])); ?></td>
                                            <td>
                                                <form method="POST" action="update_setting.php" style="display:inline">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                                    <input type="hidden" name="action" value="approve_request">
                                                    <button type="submit" class="btn btn-small btn-primary">核准</button>
                                                </form>
                                                <form method="POST" action="update_setting.php" style="display:inline">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                                    <input type="hidden" name="action" value="reject_request">
                                                    <button type="submit" class="btn btn-small btn-danger" data-confirm="確定拒絕申請？">拒絕</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </section>
                            <?php endif; ?>

                            <!-- 表單管理 -->
                            <section class="panel">
                                <div class="section-title">
                                    <h3>社團表單管理</h3>
                                </div>
                                <?php if (empty($forms)): ?>
                                    <p class="muted">此社團尚無任何表單。</p>
                                <?php else: ?>
                                    <table class="data-table">
                                        <thead>
                                            <tr><th>表單標題</th><th>填答狀態</th><th>公開範圍</th><th>操作</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($forms as $f): ?>
                                                <tr>
                                                    <td>
                                                        <a href="../forms/view.php?id=<?php echo $f['id']; ?>" style="font-weight:600;">
                                                            <?php echo htmlspecialchars($f['title']); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <span class="badge-status <?php echo $f['status'] === 'published' ? 'badge-open' : 'badge-closed'; ?>">
                                                            <?php echo $f['status'] === 'published' ? '開放填答' : '已關閉'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge-status <?php echo $f['form_type'] === 'public' ? 'badge-public' : 'badge-club-only'; ?>">
                                                            <?php echo $f['form_type'] === 'public' ? '公開' : '限定社團'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <form method="POST" action="update_setting.php" style="margin-bottom:4px;">
                                                            <?php echo csrf_field(); ?>
                                                            <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                                            <input type="hidden" name="form_id" value="<?php echo $f['id']; ?>">
                                                            <input type="hidden" name="action" value="update_form_status">
                                                            <select name="status" onchange="this.form.submit()">
                                                                <option value="published" <?php echo $f['status'] == 'published' ? 'selected' : ''; ?>>開放填答</option>
                                                                <option value="closed" <?php echo $f['status'] == 'closed' ? 'selected' : ''; ?>>關閉填答</option>
                                                            </select>
                                                        </form>
                                                        <form method="POST" action="update_setting.php" style="margin-bottom:0;">
                                                            <?php echo csrf_field(); ?>
                                                            <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                                            <input type="hidden" name="form_id" value="<?php echo $f['id']; ?>">
                                                            <input type="hidden" name="action" value="update_form_type">
                                                            <select name="form_type" onchange="this.form.submit()">
                                                                <option value="public" <?php echo $f['form_type'] == 'public' ? 'selected' : ''; ?>>公開（所有人可填）</option>
                                                                <option value="club_only" <?php echo $f['form_type'] == 'club_only' ? 'selected' : ''; ?>>限定社團</option>
                                                            </select>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </section>

                            <!-- 活動紀錄 -->
                            <section class="panel">
                                <div class="section-title"><h3>操作紀錄</h3></div>
                                <?php if (empty($activity_log)): ?>
                                    <p class="muted">尚無操作紀錄。</p>
                                <?php else: ?>
                                    <div style="max-height:300px;overflow-y:auto">
                                        <table class="data-table">
                                            <thead><tr><th>時間</th><th>使用者</th><th>操作</th><th>詳情</th></tr></thead>
                                            <tbody>
                                                <?php foreach ($activity_log as $log): ?>
                                                <tr>
                                                    <td class="meta" style="white-space:nowrap"><?php echo date('m/d H:i', strtotime($log['created_at'])); ?></td>
                                                    <td><?php echo htmlspecialchars($log['username']); ?></td>
                                                    <td>
                                                        <?php
                                                        $action_labels = [
                                                            'remove_member' => '移除成員',
                                                            'transfer_ownership' => '轉移擁有權',
                                                            'change_role' => '變更角色',
                                                            'invite_member' => '邀請成員',
                                                            'cancel_invitation' => '取消邀請',
                                                            'join_club' => '加入社團',
                                                            'leave_club' => '退出社團',
                                                            'accept_invitation' => '接受邀請',
                                                            'approve_request' => '核准申請',
                                                            'reject_request' => '拒絕申請',
                                                            'update_settings' => '更新設定',
                                                            'create_announcement' => '發布公告',
                                                            'delete_announcement' => '刪除公告',
                                                        ];
                                                        echo htmlspecialchars($action_labels[$log['action']] ?? $log['action']);
                                                        ?>
                                                    </td>
                                                    <td class="muted" style="font-size:0.85rem"><?php echo htmlspecialchars($log['details']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </section>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="footer container">社團表單系統</footer>
    <script src="../js/app.js"></script>
</body>
</html>

<?php
exit();
