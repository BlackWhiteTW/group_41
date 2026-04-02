<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/header.php';

$form_id = $_GET['id'] ?? 0;
$form = null;
$questions = [];
$question_options = [];
$submission_count = 0;
$access_error = '';

if (!$form_id) {
    header('Location: /group_41/index.php');
    exit();
}

try {
    $sql = "SELECT f.*, u.username FROM forms f 
            LEFT JOIN users u ON f.creator_id = u.id
            WHERE f.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$form_id]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$form) {
        header('Location: /group_41/index.php');
        exit();
    }
    
    // 檢查權限：如果是限定社團的表單，檢查當前用戶是否屬於該社團
    if (!is_logged_in() && $form['form_type'] === 'club_only') {
        $access_error = '此表單僅限 ' . $form['target_club_category'] . ' 的成員填寫，請登入您的帳號';
    }
    
    if (
        is_logged_in()
        && $form['form_type'] === 'club_only'
        && $current_user['role'] !== 'admin'
        && $current_user['club_category'] !== $form['target_club_category']
    ) {
        $access_error = '此表單僅限 ' . $form['target_club_category'] . ' 的成員填寫';
    }

    if (!$access_error) {
        // 獲取題目
        $sql = "SELECT * FROM form_questions WHERE form_id = ? ORDER BY question_order";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$form_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($questions)) {
            $question_ids = array_map(static function ($q) {
                return (int)$q['id'];
            }, $questions);

            $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
            $sql = "SELECT * FROM question_options WHERE question_id IN ($placeholders) ORDER BY option_order";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($question_ids);
            $all_options = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($all_options as $option) {
                $qid = (int)$option['question_id'];
                if (!isset($question_options[$qid])) {
                    $question_options[$qid] = [];
                }
                $question_options[$qid][] = $option;
            }
        }

        $sql = "SELECT COUNT(*) as count FROM form_submissions WHERE form_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$form_id]);
        $submission_count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
} catch (Exception $e) {
    echo '錯誤：' . escape($e->getMessage());
    exit();
}

render_template('forms/view.php', [
    'form_id' => $form_id,
    'form' => $form,
    'questions' => $questions,
    'question_options' => $question_options,
    'submission_count' => $submission_count,
    'access_error' => $access_error
]);

include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/footer.php';

