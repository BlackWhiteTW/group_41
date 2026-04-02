<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/db.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$current_user = get_current_user_info();

if (!is_logged_in() || !$current_user || !in_array($current_user['role'], ['club_officer', 'admin'])) {
    echo json_encode(['success' => false, 'message' => '權限不足']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '只支持POST請求']);
    exit();
}

// 驗證CSRF令牌
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => '安全驗證失敗']);
    exit();
}

$form_id = $_POST['form_id'] ?? null;
$is_edit = !empty($form_id);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$form_type = $_POST['form_type'] ?? 'public';
$status = $_POST['status'] ?? 'draft';
$questions_json = $_POST['questions_json'] ?? '[]';
$allowed_question_types = ['short_answer', 'long_answer', 'multiple_choice', 'multi_choice'];

// 驗證
if (empty($title)) {
    echo json_encode(['success' => false, 'message' => '表單標題不能為空']);
    exit();
}

if (!in_array($form_type, ['public', 'club_only'])) {
    echo json_encode(['success' => false, 'message' => '表單類型不正確']);
    exit();
}

if (!in_array($status, ['draft', 'published', 'closed'])) {
    echo json_encode(['success' => false, 'message' => '表單狀態不正確']);
    exit();
}

$target_club_category = ($form_type === 'club_only') ? $current_user['club_category'] : null;

try {
    $questions = json_decode($questions_json, true);
    
    if (empty($questions) || !is_array($questions)) {
        echo json_encode(['success' => false, 'message' => '至少需要新增一個題目']);
        exit();
    }

    foreach ($questions as $question) {
        $question_text = trim($question['text'] ?? '');
        $question_type = $question['type'] ?? '';

        if ($question_text === '') {
            echo json_encode(['success' => false, 'message' => '題目文字不能為空']);
            exit();
        }

        if (!in_array($question_type, $allowed_question_types)) {
            echo json_encode(['success' => false, 'message' => '包含不支援的題目類型']);
            exit();
        }

        if (in_array($question_type, ['multiple_choice', 'multi_choice'])) {
            $options = $question['options'] ?? [];
            $clean_options = [];
            foreach ($options as $opt) {
                $opt = trim((string)$opt);
                if ($opt !== '') {
                    $clean_options[] = $opt;
                }
            }

            if (count($clean_options) < 2) {
                echo json_encode(['success' => false, 'message' => '單選/多選題至少需要 2 個選項']);
                exit();
            }
        }
    }
    
    $pdo->beginTransaction();
    
    if ($form_id) {
        // 編輯模式
        $sql = "UPDATE forms SET title = ?, description = ?, form_type = ?, target_club_category = ?, status = ? 
                WHERE id = ? AND creator_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $description, $form_type, $target_club_category, $status, $form_id, $current_user['id']]);
        
        // 刪除舊題目
        $sql = "DELETE FROM form_questions WHERE form_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$form_id]);
    } else {
        // 新增模式
        $sql = "INSERT INTO forms (creator_id, title, description, form_type, target_club_category, status) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$current_user['id'], $title, $description, $form_type, $target_club_category, $status]);
        $form_id = $pdo->lastInsertId();
    }
    
    // 插入題目和選項
    foreach ($questions as $order => $question) {
        $sql = "INSERT INTO form_questions (form_id, question_order, question_text, question_type, is_required) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $form_id,
            $order + 1,
            trim($question['text']),
            $question['type'],
            !empty($question['required']) ? 1 : 0
        ]);
        
        $question_id = $pdo->lastInsertId();
        
        // 如果是選擇題，插入選項
        if (in_array($question['type'], ['multiple_choice', 'multi_choice']) && !empty($question['options'])) {
            $sql = "INSERT INTO question_options (question_id, option_text, option_order) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            foreach ($question['options'] as $opt_order => $option_text) {
                $option_text = trim((string)$option_text);
                if ($option_text !== '') {
                    $stmt->execute([$question_id, $option_text, $opt_order + 1]);
                }
            }
        }
    }
    
    $pdo->commit();
    
    $message = $is_edit ? '表單已更新' : '表單已建立';
    echo json_encode(['success' => true, 'message' => $message, 'form_id' => $form_id]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => '保存失敗：' . $e->getMessage()]);
}
?>

