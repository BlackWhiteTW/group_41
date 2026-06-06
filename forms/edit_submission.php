<?php
session_start();

require '../includes/db.php';
require '../includes/csrf.php';
require '../includes/functions.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$user = !empty($user_raw) ? htmlspecialchars($user_raw) : null;
$current_user = null;
$errors = [];
$submission_id = isset($_GET['sid']) ? (int) $_GET['sid'] : 0;
$submission = null;
$form = null;
$questions = [];
$options_map = [];
$prefill_text = [];
$prefill_options = [];

if (!$user_raw) {
    header('Location: ../login.php');
    exit();
}

if ($submission_id <= 0) {
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

    $sub = $pdo->prepare('SELECT s.id, s.form_id, s.user_id, s.submitted_at, f.status, f.title, f.creator_id FROM form_submissions s JOIN forms f ON f.id = s.form_id WHERE s.id = :id LIMIT 1');
    $sub->execute([':id' => $submission_id]);
    $submission = $sub->fetch();

    if (!$submission) {
        $errors[] = '找不到指定的填寫記錄。';
    } elseif ((int) $submission['user_id'] !== (int) $current_user['id'] && $current_user['role'] !== 'admin') {
        $errors[] = '你只能編輯自己的填寫記錄。';
    } elseif ($submission['status'] !== 'published') {
        $errors[] = '此表單已關閉，無法編輯回覆。';
    } else {
        $stmt = $pdo->prepare('SELECT f.*, u.username FROM forms f JOIN users u ON u.id = f.creator_id WHERE f.id = :id LIMIT 1');
        $stmt->execute([':id' => (int) $submission['form_id']]);
        $form = $stmt->fetch();

        if ($form) {
            $q_stmt = $pdo->prepare('SELECT * FROM form_questions WHERE form_id = :id ORDER BY question_order ASC');
            $q_stmt->execute([':id' => (int) $submission['form_id']]);
            $questions = $q_stmt->fetchAll();

            if (!empty($questions)) {
                $question_ids = array_column($questions, 'id');
                $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
                $o_stmt = $pdo->prepare('SELECT * FROM question_options WHERE question_id IN (' . $placeholders . ') ORDER BY option_order ASC');
                $o_stmt->execute($question_ids);
                $options = $o_stmt->fetchAll();
                foreach ($options as $opt) {
                    $options_map[$opt['question_id']][] = $opt;
                }
            }

            $a_stmt = $pdo->prepare('SELECT * FROM answers WHERE submission_id = :sid');
            $a_stmt->execute([':sid' => $submission_id]);
            $existing = $a_stmt->fetchAll();
            foreach ($existing as $ans) {
                if ($ans['answer_text'] !== null) {
                    $prefill_text[(int) $ans['question_id']] = $ans['answer_text'];
                }
                if ($ans['option_id'] !== null) {
                    $prefill_options[(int) $ans['question_id']][] = (int) $ans['option_id'];
                }
            }
        }
    }
} catch (Throwable $e) {
    $errors[] = '資料載入失敗，請稍後再試。';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $form && empty($errors)) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = '表單驗證失敗，請重新整理後再試。';
    } else {
        $answers = (isset($_POST['answers']) && is_array($_POST['answers'])) ? $_POST['answers'] : [];
        $valid_option_ids = [];
        foreach ($options_map as $qid => $opts) {
            $valid_option_ids[$qid] = array_column($opts, 'id');
        }

        foreach ($questions as $q) {
            $qid = $q['id'];
            $required = (bool) $q['is_required'];
            $type = $q['question_type'];
            $value = isset($answers[$qid]) ? $answers[$qid] : null;

            if (in_array($type, ['short_answer', 'long_answer'], true)) {
                $text = is_string($value) ? trim($value) : '';
                if ($required && $text === '') {
                    $errors[] = '題目「' . $q['question_text'] . '」為必填。';
                }
            } elseif ($type === 'multiple_choice') {
                $option_id = (int) $value;
                if ($required && $option_id === 0) {
                    $errors[] = '題目「' . $q['question_text'] . '」為必填。';
                } elseif ($option_id !== 0 && (!isset($valid_option_ids[$qid]) || !in_array($option_id, $valid_option_ids[$qid], true))) {
                    $errors[] = '題目「' . $q['question_text'] . '」的選項無效。';
                }
            } elseif ($type === 'multi_choice') {
                $option_ids = is_array($value) ? $value : [];
                $option_ids = array_map('intval', $option_ids);
                if ($required && empty($option_ids)) {
                    $errors[] = '題目「' . $q['question_text'] . '」為必填。';
                } else {
                    foreach ($option_ids as $oid) {
                        if (!isset($valid_option_ids[$qid]) || !in_array($oid, $valid_option_ids[$qid], true)) {
                            $errors[] = '題目「' . $q['question_text'] . '」的選項無效。';
                            break;
                        }
                    }
                }
            } elseif ($type === 'file_upload') {
                $file = isset($_FILES['files']['name'][$qid]) ? $_FILES['files']['name'][$qid] : '';
                if (!empty($file)) {
                    $tmp = $_FILES['files']['tmp_name'][$qid];
                    $size = $_FILES['files']['size'][$qid];
                    $error = $_FILES['files']['error'][$qid];
                    if ($error !== UPLOAD_ERR_OK) {
                        $errors[] = '題目「' . $q['question_text'] . '」檔案上傳失敗。';
                    } elseif ($size > 5 * 1024 * 1024) {
                        $errors[] = '題目「' . $q['question_text'] . '」檔案不可超過 5 MB。';
                    } elseif (!is_allowed_upload_extension($file)) {
                        $errors[] = '題目「' . $q['question_text'] . '」不支援此檔案類型，僅允許圖片、PDF、文件檔。';
                    } elseif (!validate_upload_content($tmp, $file)) {
                        $errors[] = '題目「' . $q['question_text'] . '」檔案內容驗證失敗，可能為偽造檔案。';
                    }
                }
            }
        }

        if (empty($errors)) {
            try {
                $pdo = get_db();
                $pdo->beginTransaction();

                $del = $pdo->prepare('DELETE FROM answers WHERE submission_id = :sid');
                $del->execute([':sid' => $submission_id]);

                $ins = $pdo->prepare('INSERT INTO answers (submission_id, question_id, answer_text, option_id, file_path) VALUES (:s, :q, :t, :o, :fp)');
                foreach ($questions as $q) {
                    $qid = $q['id'];
                    $type = $q['question_type'];
                    $value = isset($answers[$qid]) ? $answers[$qid] : null;

                    if (in_array($type, ['short_answer', 'long_answer'], true)) {
                        $text = is_string($value) ? trim($value) : '';
                        if ($text !== '') {
                            $ins->execute([':s' => $submission_id, ':q' => $qid, ':t' => $text, ':o' => null, ':fp' => null]);
                        }
                    } elseif ($type === 'multiple_choice') {
                        $option_id = (int) $value;
                        if ($option_id) {
                            $ins->execute([':s' => $submission_id, ':q' => $qid, ':t' => null, ':o' => $option_id, ':fp' => null]);
                        }
                    } elseif ($type === 'multi_choice') {
                        $option_ids = is_array($value) ? $value : [];
                        foreach ($option_ids as $oid) {
                            if ((int) $oid) {
                                $ins->execute([':s' => $submission_id, ':q' => $qid, ':t' => null, ':o' => (int) $oid, ':fp' => null]);
                            }
                        }
                    } elseif ($type === 'file_upload') {
                        $file_name = isset($_FILES['files']['name'][$qid]) ? $_FILES['files']['name'][$qid] : '';
                        if (!empty($file_name) && $_FILES['files']['error'][$qid] === UPLOAD_ERR_OK && is_allowed_upload_extension($file_name)) {
                            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            $stored_name = $submission_id . '_' . $qid . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                            $dest = __DIR__ . '/../uploads/' . $stored_name;
                            if (move_uploaded_file($_FILES['files']['tmp_name'][$qid], $dest)) {
                                $ins->execute([':s' => $submission_id, ':q' => $qid, ':t' => $file_name, ':o' => null, ':fp' => $stored_name]);
                            }
                        }
                    }
                }

                $pdo->commit();
                header('Location: ./view.php?id=' . (int) $submission['form_id']);
                exit();
            } catch (Throwable $e) {
                if (!empty($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = '更新失敗，請稍後再試。';
            }
        }
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>編輯回覆 | 社團表單系統</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../css/app.css" />
</head>
<body>
    <?php $base_url = '../'; require '../includes/header.php'; ?>
    <?php require __DIR__ . '/../includes/right.php'; ?>

    <main class="section">
        <div class="container">
            <h1>編輯回覆</h1>
            <?php if (!empty($errors)): ?>
                <div class="error"><ul><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>

            <?php if (!$form): ?>
                <div class="panel" style="padding:20px"><p class="muted">找不到指定的表單。</p><a class="btn btn-ghost" href="./list.php">返回列表</a></div>
            <?php else: ?>
                <div class="panel" style="padding:20px">
                    <h2><?php echo htmlspecialchars($form['title']); ?></h2>
                    <p class="muted">編輯你之前送出的回覆。送出日：<?php echo !empty($submission['submitted_at']) ? date('Y-m-d H:i', strtotime($submission['submitted_at'])) : ''; ?></p>
                    <form method="post" action="./edit_submission.php?sid=<?php echo $submission_id; ?>" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <?php foreach ($questions as $q): ?>
                            <div class="field" style="margin-top:16px">
                                <label><?php echo htmlspecialchars($q['question_text']); ?><?php if ($q['is_required']): ?><span class="muted">(必填)</span><?php endif; ?></label>
                                <?php if (in_array($q['question_type'], ['short_answer', 'long_answer'], true)): ?>
                                    <?php $val = $prefill_text[$q['id']] ?? ''; ?>
                                    <?php if ($q['question_type'] === 'short_answer'): ?>
                                        <input name="answers[<?php echo $q['id']; ?>]" value="<?php echo htmlspecialchars($val); ?>" />
                                    <?php else: ?>
                                        <textarea name="answers[<?php echo $q['id']; ?>]" rows="3"><?php echo htmlspecialchars($val); ?></textarea>
                                    <?php endif; ?>
                                <?php elseif ($q['question_type'] === 'multiple_choice'): ?>
                                    <?php $sel = $prefill_options[$q['id']][0] ?? 0; ?>
                                    <?php foreach ($options_map[$q['id']] ?? [] as $opt): ?>
                                        <label style="display:block;margin-top:6px">
                                            <input type="radio" name="answers[<?php echo $q['id']; ?>]" value="<?php echo $opt['id']; ?>" <?php echo (int) $sel === (int) $opt['id'] ? 'checked' : ''; ?> />
                                            <?php echo htmlspecialchars($opt['option_text']); ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php elseif ($q['question_type'] === 'multi_choice'): ?>
                                    <?php $sel_ids = $prefill_options[$q['id']] ?? []; ?>
                                    <?php foreach ($options_map[$q['id']] ?? [] as $opt): ?>
                                        <label style="display:block;margin-top:6px">
                                            <input type="checkbox" name="answers[<?php echo $q['id']; ?>][]" value="<?php echo $opt['id']; ?>" <?php echo in_array((int) $opt['id'], $sel_ids, true) ? 'checked' : ''; ?> />
                                            <?php echo htmlspecialchars($opt['option_text']); ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php elseif ($q['question_type'] === 'file_upload'): ?>
                                    <?php $val = $prefill_text[$q['id']] ?? ''; ?>
                                    <?php if ($val): ?><p class="muted" style="font-size:0.85rem">原檔案：<?php echo htmlspecialchars($val); ?></p><?php endif; ?>
                                    <input type="file" name="files[<?php echo $q['id']; ?>]" accept="<?php echo htmlspecialchars(get_upload_accept_string()); ?>" />
                                    <p class="muted" style="font-size:0.85rem">支援圖片、PDF、文件檔，上限 5 MB</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <button class="btn btn-primary" type="submit" style="margin-top:16px">更新回覆</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="footer container">社團表單系統</footer>
    <script src="../js/app.js"></script>
</body>
</html>
<?php exit();
