<?php
session_start();

require '../includes/db.php';
require '../includes/csrf.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;

if (!$user_raw) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./my_forms.php');
    exit();
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = '表單驗證失敗，請重新整理後再試。';
    header('Location: ./my_forms.php');
    exit();
}

$form_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($form_id <= 0) {
    header('Location: ./my_forms.php');
    exit();
}

try {
    $pdo = get_db();

    $u = $pdo->prepare('SELECT id, role FROM users WHERE username = :u LIMIT 1');
    $u->execute([':u' => $user_raw]);
    $user_row = $u->fetch();
    if (!$user_row) {
        header('Location: ../login.php');
        exit();
    }

    $stmt = $pdo->prepare('SELECT f.* FROM forms f WHERE f.id = :id LIMIT 1');
    $stmt->execute([':id' => $form_id]);
    $form = $stmt->fetch();
    if (!$form) {
        $_SESSION['flash_error'] = '找不到指定的表單。';
        header('Location: ./my_forms.php');
        exit();
    }

    $can_delete = false;
    if ($user_row['role'] === 'admin') {
        $can_delete = true;
    } elseif ((int) $form['creator_id'] === (int) $user_row['id']) {
        $can_delete = true;
    } elseif ($form['club_id']) {
        $cm = $pdo->prepare("SELECT 1 FROM club_memberships WHERE user_id = ? AND club_id = ? AND role IN ('owner', 'club_officer') LIMIT 1");
        $cm->execute([(int) $user_row['id'], (int) $form['club_id']]);
        if ($cm->fetch()) {
            $can_delete = true;
        }
    }

    if (!$can_delete) {
        $_SESSION['flash_error'] = '你沒有權限刪除此表單。';
        header('Location: ./my_forms.php');
        exit();
    }

    // Delete uploaded files from disk
    $files = $pdo->prepare('SELECT a.file_path FROM answers a JOIN form_questions q ON q.id = a.question_id WHERE q.form_id = :fid AND a.file_path IS NOT NULL AND a.file_path <> ""');
    $files->execute([':fid' => $form_id]);
    foreach ($files->fetchAll() as $f) {
        $path = __DIR__ . '/../uploads/' . $f['file_path'];
        if (file_exists($path)) {
            unlink($path);
        }
    }

    // Cascade delete: answers -> submissions -> question_options -> form_questions -> form
    // (FK CASCADE handles most, but we delete explicitly for clarity)
    $pdo->prepare('DELETE FROM forms WHERE id = :id')->execute([':id' => $form_id]);

    $_SESSION['flash_success'] = '表單「' . $form['title'] . '」已刪除。';
    $redirect_to = isset($_POST['redirect']) ? $_POST['redirect'] : 'my_forms.php';
    if (!in_array($redirect_to, ['my_forms.php', 'list.php', 'edit.php'], true)) {
        $redirect_to = 'my_forms.php';
    }
    header('Location: ./' . $redirect_to);
    exit();

} catch (Throwable $e) {
    $_SESSION['flash_error'] = '刪除表單失敗，請稍後再試。';
    header('Location: ./my_forms.php');
    exit();
}
