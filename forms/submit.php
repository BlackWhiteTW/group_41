<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/db.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /group_41/index.php');
    exit();
}

// 驗證CSRF令牌
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    header('Location: /group_41/index.php');
    exit();
}

$form_id = $_POST['form_id'] ?? 0;

if (!$form_id) {
    header('Location: /group_41/index.php');
    exit();
}

try {
    // 檢查表單是否存在
    $sql = "SELECT * FROM forms WHERE id = ? AND status = 'published'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$form_id]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$form) {
        header('Location: /group_41/index.php');
        exit();
    }

    // 伺服器端再次檢查私人表單權限
    if ($form['form_type'] === 'club_only') {
        if (!is_logged_in()) {
            throw new Exception('此私人表單需要登入後填寫');
        }
        $user = get_current_user_info();
        if (!$user || ($user['role'] !== 'admin' && $user['club_category'] !== $form['target_club_category'])) {
            throw new Exception('你不在此表單允許的社團內');
        }
    }
    
    // 開始事務
    $pdo->beginTransaction();
    
    // 建立填寫記錄
    $user_id = is_logged_in() ? $_SESSION['user_id'] : null;
    $ip_address = get_user_ip();
    
    $sql = "INSERT INTO form_submissions (form_id, user_id, ip_address) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$form_id, $user_id, $ip_address]);
    $submission_id = $pdo->lastInsertId();
    
    // 處理答案
    $sql = "SELECT * FROM form_questions WHERE form_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$form_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($questions as $question) {
        $answer_key = 'answer_' . $question['id'];
        $answer_value = $_POST[$answer_key] ?? '';
        $is_multi_choice = ($question['question_type'] === 'multi_choice');
        $selected_options = $is_multi_choice ? ($answer_value ?? []) : [];

        if ($is_multi_choice && !is_array($selected_options)) {
            $selected_options = [];
        }
        
        // 驗證必填項
        if ($question['is_required']) {
            if ($is_multi_choice && count($selected_options) === 0) {
                throw new Exception('必填項目不能為空');
            }
            if (!$is_multi_choice && empty($answer_value)) {
                throw new Exception('必填項目不能為空');
            }
        }

        if ($question['question_type'] === 'short_answer' || $question['question_type'] === 'long_answer') {
            $answer_text = trim((string)$answer_value);
            if ($answer_text === '') {
                continue;
            }

            $sql = "INSERT INTO answers (submission_id, question_id, answer_text) 
                    VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$submission_id, $question['id'], $answer_text]);
        } elseif ($question['question_type'] === 'multiple_choice') {
            if (empty($answer_value)) {
                continue;
            }

            $sql = "INSERT INTO answers (submission_id, question_id, option_id) 
                    VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$submission_id, $question['id'], (int)$answer_value]);
        } elseif ($question['question_type'] === 'multi_choice') {
            foreach ($selected_options as $opt_id) {
                $opt_id = (int)$opt_id;
                if ($opt_id <= 0) {
                    continue;
                }

                $sql = "INSERT INTO answers (submission_id, question_id, option_id) 
                        VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$submission_id, $question['id'], $opt_id]);
            }
        } else {
            throw new Exception('包含不支援的題目類型');
        }
    }
    
    $pdo->commit();
    
    // 重定向到成功頁面
    header('Location: /group_41/forms/success.php?form_id=' . $form_id);
    exit();
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: /group_41/forms/view.php?id=' . $form_id . '&error=' . urlencode($e->getMessage()));
    exit();
}
?>

