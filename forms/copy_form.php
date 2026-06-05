<?php
session_start();

require '../includes/db.php';
require '../includes/csrf.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$current_user = null;
$is_admin = false;
$managed_clubs = [];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./my_forms.php');
    exit();
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = '表單驗證失敗，請重新整理後再試。';
    header('Location: ./my_forms.php');
    exit();
}

$source_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if (!$user_raw) {
    header('Location: ../login.php');
    exit();
}

if ($source_id <= 0) {
    header('Location: ./my_forms.php');
    exit();
}

try {
    $pdo = get_db();
    $u = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
    $u->execute([':u' => $user_raw]);
    $current_user = $u->fetch();
    if (!$current_user) {
        header('Location: ../login.php');
        exit();
    }

    $is_admin = ($current_user['role'] === 'admin');
    if (!$is_admin) {
        $mem_stmt = $pdo->prepare('SELECT club_id, role FROM club_memberships WHERE user_id = :id');
        $mem_stmt->execute([':id' => $current_user['id']]);
        foreach ($mem_stmt->fetchAll() as $row) {
            if (in_array($row['role'], ['owner', 'club_officer'], true)) {
                $managed_clubs[] = (int) $row['club_id'];
            }
        }
    }

    $stmt = $pdo->prepare('SELECT * FROM forms WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $source_id]);
    $source = $stmt->fetch();

    if (!$source) {
        header('Location: ./my_forms.php');
        exit();
    }

    $can_copy = $is_admin || ($source['club_id'] && in_array((int) $source['club_id'], $managed_clubs, true));
    if (!$can_copy) {
        header('Location: ./my_forms.php');
        exit();
    }

    $questions = $pdo->prepare('SELECT * FROM form_questions WHERE form_id = :id ORDER BY question_order ASC');
    $questions->execute([':id' => $source_id]);
    $q_rows = $questions->fetchAll();

    $pdo->beginTransaction();

    $new_title = $source['title'] . ' (複製)';
    $ins = $pdo->prepare('INSERT INTO forms (creator_id, club_id, title, description, form_type, target_club_ids, status, open_at, close_at, allow_resubmit, require_login) VALUES (:c, :club, :t, :d, :ft, :tc, :s, :oa, :ca, :ar, :rl)');
    $ins->execute([
        ':c'    => $current_user['id'],
        ':club' => $source['club_id'],
        ':t'    => $new_title,
        ':d'    => $source['description'],
        ':ft'   => $source['form_type'],
        ':tc'   => $source['target_club_ids'],
        ':s'    => 'draft',
        ':oa'   => null,
        ':ca'   => null,
        ':ar'   => (int) ($source['allow_resubmit'] ?? 1),
        ':rl'   => (int) ($source['require_login'] ?? 0)
    ]);
    $new_form_id = (int) $pdo->lastInsertId();

    if (!empty($q_rows)) {
        $q_ins = $pdo->prepare('INSERT INTO form_questions (form_id, question_order, question_text, question_type, is_required) VALUES (:f, :o, :t, :qt, :r)');
        foreach ($q_rows as $q) {
            $q_ins->execute([
                ':f'  => $new_form_id,
                ':o'  => $q['question_order'],
                ':t'  => $q['question_text'],
                ':qt' => $q['question_type'],
                ':r'  => $q['is_required']
            ]);
            $new_qid = (int) $pdo->lastInsertId();

            $opts = $pdo->prepare('SELECT * FROM question_options WHERE question_id = :qid ORDER BY option_order ASC');
            $opts->execute([':qid' => $q['id']]);
            $o_rows = $opts->fetchAll();

            if (!empty($o_rows)) {
                $o_ins = $pdo->prepare('INSERT INTO question_options (question_id, option_text, option_order) VALUES (:q, :t, :o)');
                foreach ($o_rows as $o) {
                    $o_ins->execute([
                        ':q' => $new_qid,
                        ':t' => $o['option_text'],
                        ':o' => $o['option_order']
                    ]);
                }
            }
        }
    }

    $pdo->commit();
    $_SESSION['flash_success'] = '表單已複製為草稿：' . $new_title;
    header('Location: ./edit.php?id=' . $new_form_id);
    exit();

} catch (Throwable $e) {
    if (!empty($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = '複製表單失敗。';
    header('Location: ./my_forms.php');
    exit();
}
