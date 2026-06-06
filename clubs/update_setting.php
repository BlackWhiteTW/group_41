<?php
session_start();
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/csrf.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
if (!$user_raw) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: setting.php');
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = '表單驗證失敗，請重新整理後再試。';
    header('Location: setting.php');
    exit;
}

$club_id = (int)$_POST['club_id'];
$action = $_POST['action'];

function log_activity($pdo, $club_id, $user_id, $action, $details = '') {
    $stmt = $pdo->prepare('INSERT INTO club_activity_log (club_id, user_id, action, details) VALUES (:c, :u, :a, :d)');
    $stmt->execute([':c' => $club_id, ':u' => $user_id, ':a' => $action, ':d' => $details]);
}

try {
    $pdo = get_db();

    $user_stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
    $user_stmt->execute([':u' => $user_raw]);
    $current_user = $user_stmt->fetch();

    $stmt = $pdo->prepare('SELECT 1 FROM club_memberships WHERE club_id = :cid AND user_id = :uid AND role IN ("owner", "club_officer")');
    $stmt->execute([':cid' => $club_id, ':uid' => $current_user['id']]);
    $is_authorized = ($current_user['role'] === 'admin' || $stmt->fetch());

    $public_actions = ['request_join', 'accept_invitation', 'decline_invitation'];
    $member_actions = ['leave_club'];
    $needs_auth = !in_array($action, array_merge($public_actions, $member_actions), true);

    if ($needs_auth && !$is_authorized) {
        die('無權限進行此操作');
    }

    if (in_array($action, $member_actions, true)) {
        $mem_check = $pdo->prepare('SELECT 1 FROM club_memberships WHERE club_id = :cid AND user_id = :uid');
        $mem_check->execute([':cid' => $club_id, ':uid' => $current_user['id']]);
        if (!$mem_check->fetch()) {
            die('無權限進行此操作');
        }
    }

    if ($action === 'remove') {
        $user_id = (int)$_POST['user_id'];
        $target = $pdo->prepare('SELECT username FROM users WHERE id = :id');
        $target->execute([':id' => $user_id]);
        $target_user = $target->fetch();
        $stmt = $pdo->prepare('DELETE FROM club_memberships WHERE club_id = :club_id AND user_id = :user_id');
        $stmt->execute([':club_id' => $club_id, ':user_id' => $user_id]);
        log_activity($pdo, $club_id, $current_user['id'], 'remove_member', '移除成員 ' . ($target_user['username'] ?? $user_id));
    }
    elseif ($action === 'transfer') {
        $user_id = (int)$_POST['user_id'];
        $target = $pdo->prepare('SELECT username FROM users WHERE id = :id');
        $target->execute([':id' => $user_id]);
        $target_user = $target->fetch();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE clubs SET owner_user_id = :new_owner WHERE id = :club_id');
        $stmt->execute([':new_owner' => $user_id, ':club_id' => $club_id]);
        $stmt = $pdo->prepare('UPDATE club_memberships SET role = "club_officer" WHERE club_id = :club_id AND user_id = :user_id');
        $stmt->execute([':club_id' => $club_id, ':user_id' => $user_id]);
        $stmt = $pdo->prepare('UPDATE club_memberships SET role = "member" WHERE club_id = :club_id AND user_id = :uid AND role = "club_officer"');
        $stmt->execute([':club_id' => $club_id, ':uid' => $current_user['id']]);
        $pdo->commit();
        log_activity($pdo, $club_id, $current_user['id'], 'transfer_ownership', '轉移擁有權給 ' . ($target_user['username'] ?? $user_id));
    }
    elseif ($action === 'role') {
        $user_id = (int)$_POST['user_id'];
        $new_role = $_POST['role'];
        $target = $pdo->prepare('SELECT username FROM users WHERE id = :id');
        $target->execute([':id' => $user_id]);
        $target_user = $target->fetch();
        $stmt = $pdo->prepare('UPDATE club_memberships SET role = :role WHERE club_id = :club_id AND user_id = :user_id');
        $stmt->execute([':role' => $new_role, ':club_id' => $club_id, ':user_id' => $user_id]);
        $role_label = $new_role === 'club_officer' ? '幹部' : '成員';
        log_activity($pdo, $club_id, $current_user['id'], 'change_role', '將 ' . ($target_user['username'] ?? $user_id) . ' 角色改為 ' . $role_label);
    }
    elseif ($action === 'update_form_status') {
        $form_id = (int)$_POST['form_id'];
        $new_status = $_POST['status'];
        if (!in_array($new_status, ['draft', 'published', 'closed'], true)) {
            $new_status = 'draft';
        }
        $stmt = $pdo->prepare('UPDATE forms SET status = :status WHERE id = :form_id AND club_id = :club_id');
        $stmt->execute([':status' => $new_status, ':form_id' => $form_id, ':club_id' => $club_id]);
    }
    elseif ($action === 'update_form_type') {
        $form_id = (int)$_POST['form_id'];
        $new_type = $_POST['form_type'];
        if (!in_array($new_type, ['public', 'club_only'], true)) {
            $new_type = 'public';
        }
        $stmt = $pdo->prepare('UPDATE forms SET form_type = :form_type WHERE id = :form_id AND club_id = :club_id');
        $stmt->execute([':form_type' => $new_type, ':form_id' => $form_id, ':club_id' => $club_id]);
    }
    elseif ($action === 'invite_member') {
        $invite_username = trim($_POST['invite_username'] ?? '');
        if ($invite_username === '') {
            $_SESSION['flash_error'] = '請輸入使用者名稱。';
        } else {
            $inv = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
            $inv->execute([':u' => $invite_username]);
            $target = $inv->fetch();
            if (!$target) {
                $_SESSION['flash_error'] = '找不到使用者：' . $invite_username;
            } else {
                $exist = $pdo->prepare('SELECT id FROM club_memberships WHERE club_id = :c AND user_id = :u');
                $exist->execute([':c' => $club_id, ':u' => $target['id']]);
                if ($exist->fetch()) {
                    $_SESSION['flash_error'] = '該使用者已在社團中。';
                } else {
                    $ins = $pdo->prepare('INSERT INTO club_invitations (club_id, user_id, invited_by) VALUES (:c, :u, :b)');
                    $ins->execute([':c' => $club_id, ':u' => $target['id'], ':b' => $current_user['id']]);
                    $_SESSION['flash_success'] = '已邀請 ' . $invite_username . ' 加入社團。';
                    log_activity($pdo, $club_id, $current_user['id'], 'invite_member', '邀請 ' . $invite_username);
                }
            }
        }
    }
    elseif ($action === 'cancel_invitation') {
        $inv_id = (int)$_POST['invitation_id'];
        $inv = $pdo->prepare('SELECT ci.id, u.username FROM club_invitations ci JOIN users u ON u.id = ci.user_id WHERE ci.id = :id AND ci.club_id = :c AND ci.status = "pending"');
        $inv->execute([':id' => $inv_id, ':c' => $club_id]);
        $invite = $inv->fetch();
        if ($invite) {
            $pdo->prepare('UPDATE club_invitations SET status = "declined" WHERE id = :id')->execute([':id' => $inv_id]);
            log_activity($pdo, $club_id, $current_user['id'], 'cancel_invitation', '取消對 ' . $invite['username'] . ' 的邀請');
            $_SESSION['flash_success'] = '已取消邀請。';
        }
    }
    elseif ($action === 'request_join') {
        $club_info = $pdo->prepare('SELECT join_mode FROM clubs WHERE id = :id LIMIT 1');
        $club_info->execute([':id' => $club_id]);
        $ci = $club_info->fetch();
        if (!$ci) {
            $_SESSION['flash_error'] = '找不到社團。';
        } else {
            $exist = $pdo->prepare('SELECT id FROM club_memberships WHERE club_id = :c AND user_id = :u');
            $exist->execute([':c' => $club_id, ':u' => $current_user['id']]);
            if ($exist->fetch()) {
                $_SESSION['flash_error'] = '你已是社團成員。';
            } elseif ($ci['join_mode'] === 'open') {
                $pdo->prepare('INSERT IGNORE INTO club_memberships (user_id, club_id, role) VALUES (:u, :c, "member")')->execute([':u' => $current_user['id'], ':c' => $club_id]);
                $_SESSION['flash_success'] = '已加入社團。';
                log_activity($pdo, $club_id, $current_user['id'], 'join_club', '直接加入（開放加入模式）');
            } elseif ($ci['join_mode'] === 'invite_only') {
                $_SESSION['flash_error'] = '此社團僅接受邀請加入。';
            } else {
                $ins = $pdo->prepare('INSERT IGNORE INTO club_join_requests (club_id, user_id) VALUES (:c, :u)');
                $ins->execute([':c' => $club_id, ':u' => $current_user['id']]);
                $_SESSION['flash_success'] = '已送出加入申請。';
            }
        }
    }
    elseif ($action === 'leave_club') {
        $owner_check = $pdo->prepare('SELECT owner_user_id FROM clubs WHERE id = :id LIMIT 1');
        $owner_check->execute([':id' => $club_id]);
        $club_row = $owner_check->fetch();
        if ($club_row && (int)$club_row['owner_user_id'] === (int)$current_user['id']) {
            $_SESSION['flash_error'] = '社團持有人無法自行退出，請先轉移擁有權。';
        } else {
            $stmt = $pdo->prepare('DELETE FROM club_memberships WHERE club_id = :club_id AND user_id = :user_id');
            $stmt->execute([':club_id' => $club_id, ':user_id' => $current_user['id']]);
            log_activity($pdo, $club_id, $current_user['id'], 'leave_club', '自行退出社團');
            $_SESSION['flash_success'] = '已退出社團。';
        }
    }
    elseif ($action === 'accept_invitation') {
        $inv_id = (int)$_POST['invitation_id'];
        $inv = $pdo->prepare('SELECT * FROM club_invitations WHERE id = :id AND user_id = :uid AND status = "pending"');
        $inv->execute([':id' => $inv_id, ':uid' => $current_user['id']]);
        $invite = $inv->fetch();
        if ($invite) {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE club_invitations SET status = "accepted" WHERE id = :id')->execute([':id' => $inv_id]);
            $pdo->prepare('INSERT IGNORE INTO club_memberships (user_id, club_id, role) VALUES (:u, :c, "member")')->execute([':u' => $current_user['id'], ':c' => (int)$invite['club_id']]);
            $pdo->commit();
            $_SESSION['flash_success'] = '已加入社團。';
            log_activity($pdo, (int)$invite['club_id'], $current_user['id'], 'accept_invitation', '接受邀請加入');
        }
    }
    elseif ($action === 'decline_invitation') {
        $inv_id = (int)$_POST['invitation_id'];
        $inv = $pdo->prepare('UPDATE club_invitations SET status = "declined" WHERE id = :id AND user_id = :uid AND status = "pending"');
        $inv->execute([':id' => $inv_id, ':uid' => $current_user['id']]);
        $_SESSION['flash_success'] = '已拒絕邀請。';
    }
    elseif ($action === 'approve_request') {
        $req_id = (int)$_POST['request_id'];
        $req = $pdo->prepare('SELECT * FROM club_join_requests WHERE id = :id AND club_id = :club_id AND status = "pending"');
        $req->execute([':id' => $req_id, ':club_id' => $club_id]);
        $request = $req->fetch();
        if ($request) {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE club_join_requests SET status = "approved" WHERE id = :id')->execute([':id' => $req_id]);
            $pdo->prepare('INSERT IGNORE INTO club_memberships (user_id, club_id, role) VALUES (:u, :c, "member")')->execute([':u' => (int)$request['user_id'], ':c' => $club_id]);
            $pdo->commit();
            $_SESSION['flash_success'] = '已核准加入申請。';
            $target_u = $pdo->prepare('SELECT username FROM users WHERE id = :id');
            $target_u->execute([':id' => (int)$request['user_id']]);
            $tu = $target_u->fetch();
            log_activity($pdo, $club_id, $current_user['id'], 'approve_request', '核准 ' . ($tu['username'] ?? $request['user_id']) . ' 加入');
        }
    }
    elseif ($action === 'reject_request') {
        $req_id = (int)$_POST['request_id'];
        $req = $pdo->prepare('SELECT cr.id, u.username FROM club_join_requests cr JOIN users u ON u.id = cr.user_id WHERE cr.id = :id AND cr.club_id = :club_id AND cr.status = "pending"');
        $req->execute([':id' => $req_id, ':club_id' => $club_id]);
        $request = $req->fetch();
        $pdo->prepare('UPDATE club_join_requests SET status = "rejected" WHERE id = :id AND club_id = :club_id AND status = "pending"')->execute([':id' => $req_id, ':club_id' => $club_id]);
        $_SESSION['flash_success'] = '已拒絕申請。';
        if ($request) {
            log_activity($pdo, $club_id, $current_user['id'], 'reject_request', '拒絕 ' . $request['username'] . ' 的加入申請');
        }
    }
    elseif ($action === 'delete_club') {
        $owner_stmt = $pdo->prepare('SELECT owner_user_id FROM clubs WHERE id = :id LIMIT 1');
        $owner_stmt->execute([':id' => $club_id]);
        $club_row = $owner_stmt->fetch();
        $is_owner = ($club_row && (int)$club_row['owner_user_id'] === (int)$current_user['id']);
        if (!$is_owner && $current_user['role'] !== 'admin') {
            die('只有社團持有人可以刪除社團。');
        }
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM club_memberships WHERE club_id = :id')->execute([':id' => $club_id]);
        $pdo->prepare('UPDATE forms SET club_id = NULL WHERE club_id = :id')->execute([':id' => $club_id]);
        $pdo->prepare('DELETE FROM clubs WHERE id = :id')->execute([':id' => $club_id]);
        $pdo->commit();
        header('Location: clubs_index.php');
        exit;
    }
    elseif ($action === 'update_club_settings') {
        $club_name = trim($_POST['club_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $join_mode = in_array($_POST['join_mode'] ?? '', ['open', 'request', 'invite_only'], true) ? $_POST['join_mode'] : 'request';
        $visibility = in_array($_POST['visibility'] ?? '', ['public', 'private'], true) ? $_POST['visibility'] : 'public';

        if ($club_name === '') {
            $_SESSION['flash_error'] = '社團名稱不可空白。';
        } elseif (mb_strlen($club_name) < 2) {
            $_SESSION['flash_error'] = '社團名稱至少需要 2 個字元。';
        } else {
            $dup = $pdo->prepare('SELECT id FROM clubs WHERE name = :n AND id != :id LIMIT 1');
            $dup->execute([':n' => $club_name, ':id' => $club_id]);
            if ($dup->fetch()) {
                $_SESSION['flash_error'] = '此社團名稱已被使用。';
            } else {
                $stmt = $pdo->prepare('UPDATE clubs SET name = :n, description = :d, join_mode = :j, visibility = :v WHERE id = :id');
                $stmt->execute([':n' => $club_name, ':d' => $description, ':j' => $join_mode, ':v' => $visibility, ':id' => $club_id]);
                $_SESSION['flash_success'] = '社團設定已更新。';
                log_activity($pdo, $club_id, $current_user['id'], 'update_settings', '更新社團設定');
            }
        }
    }
    elseif ($action === 'create_announcement') {
        $title = trim($_POST['announce_title'] ?? '');
        $content = trim($_POST['announce_content'] ?? '');
        if ($title === '') {
            $_SESSION['flash_error'] = '公告標題不可空白。';
        } else {
            $stmt = $pdo->prepare('INSERT INTO club_announcements (club_id, user_id, title, content) VALUES (:c, :u, :t, :ct)');
            $stmt->execute([':c' => $club_id, ':u' => $current_user['id'], ':t' => $title, ':ct' => $content]);
            $_SESSION['flash_success'] = '公告已發布。';
            log_activity($pdo, $club_id, $current_user['id'], 'create_announcement', '發布公告：' . $title);
        }
    }
    elseif ($action === 'delete_announcement') {
        $ann_id = (int)$_POST['announce_id'];
        $ann = $pdo->prepare('SELECT title FROM club_announcements WHERE id = :id AND club_id = :c');
        $ann->execute([':id' => $ann_id, ':c' => $club_id]);
        $ann_row = $ann->fetch();
        if ($ann_row) {
            $pdo->prepare('DELETE FROM club_announcements WHERE id = :id')->execute([':id' => $ann_id]);
            $_SESSION['flash_success'] = '公告已刪除。';
            log_activity($pdo, $club_id, $current_user['id'], 'delete_announcement', '刪除公告：' . $ann_row['title']);
        }
    }

    if (in_array($action, $public_actions, true)) {
        header('Location: clubs_index.php');
    } else {
        header('Location: setting.php?id=' . $club_id);
    }

} catch (Throwable $e) {
    $_SESSION['flash_error'] = '操作失敗，請稍後再試。';
    if (in_array($action, $public_actions, true)) {
        header('Location: clubs_index.php');
    } else {
        header('Location: setting.php?id=' . $club_id);
    }
}
