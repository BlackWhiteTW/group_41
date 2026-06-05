<?php
session_start();
require_once '../includes/db.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$answer_id = isset($_GET['aid']) ? (int) $_GET['aid'] : 0;
$preview = isset($_GET['preview']);

if (!$user_raw) {
    http_response_code(403);
    exit('請先登入');
}

if ($answer_id <= 0) {
    http_response_code(400);
    exit('缺少檔案編號');
}

try {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT a.id, a.file_path, a.answer_text, a.submission_id, f.id AS form_id, f.creator_id, f.club_id, s.user_id AS submitter_user_id FROM answers a JOIN form_questions q ON q.id = a.question_id JOIN forms f ON f.id = q.form_id JOIN form_submissions s ON s.id = a.submission_id WHERE a.id = :aid LIMIT 1');
    $stmt->execute([':aid' => $answer_id]);
    $row = $stmt->fetch();

    if (!$row || !$row['file_path']) {
        http_response_code(404);
        exit('找不到檔案');
    }

    // Authorization check
    $u = $pdo->prepare('SELECT id, role FROM users WHERE username = :u LIMIT 1');
    $u->execute([':u' => $user_raw]);
    $user_row = $u->fetch();
    if (!$user_row) {
        http_response_code(403);
        exit('權限不足');
    }

    $authorized = false;
    if ($user_row['role'] === 'admin') {
        $authorized = true;
    } elseif ((int) $row['creator_id'] === (int) $user_row['id']) {
        $authorized = true;
    } elseif ((int) $row['submitter_user_id'] === (int) $user_row['id']) {
        $authorized = true;
    } elseif ($row['club_id']) {
        $cm = $pdo->prepare("SELECT 1 FROM club_memberships WHERE user_id = ? AND club_id = ? AND role IN ('owner', 'club_officer') LIMIT 1");
        $cm->execute([(int) $user_row['id'], (int) $row['club_id']]);
        if ($cm->fetch()) {
            $authorized = true;
        }
    }

    if (!$authorized) {
        http_response_code(403);
        exit('權限不足');
    }

    $file = __DIR__ . '/../uploads/' . $row['file_path'];
    if (!file_exists($file)) {
        http_response_code(404);
        exit('檔案不存在');
    }

    $original_name = $row['answer_text'] ?: 'download';
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $mime_types = [
        'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif'  => 'image/gif',  'webp' => 'image/webp', 'bmp' => 'image/bmp',
        'svg'  => 'image/svg+xml', 'ico' => 'image/x-icon', 'tiff' => 'image/tiff',
        'pdf'  => 'application/pdf',
        'txt'  => 'text/plain; charset=utf-8', 'csv' => 'text/csv; charset=utf-8',
        'log'  => 'text/plain; charset=utf-8', 'md' => 'text/plain; charset=utf-8',
        'json' => 'application/json; charset=utf-8', 'xml' => 'application/xml; charset=utf-8',
        'yaml' => 'text/plain; charset=utf-8', 'yml' => 'text/plain; charset=utf-8',
        'html' => 'text/html; charset=utf-8', 'htm' => 'text/html; charset=utf-8',
        'css'  => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt'  => 'application/vnd.oasis.opendocument.text',
        'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
        'odp'  => 'application/vnd.oasis.opendocument.presentation',
        'zip'  => 'application/zip', 'rar' => 'application/vnd.rar',
        'mp4'  => 'video/mp4', 'webm' => 'video/webm',
        'mp3'  => 'audio/mpeg', 'wav' => 'audio/wav',
    ];
    $mime = isset($mime_types[$ext]) ? $mime_types[$ext] : 'application/octet-stream';

    if (ob_get_level()) { ob_clean(); }
    header('Content-Type: ' . $mime);
    if ($preview) {
        header('Content-Disposition: inline');
    } else {
        $safe_name = str_replace('"', '', $original_name);
        header('Content-Disposition: attachment; filename="' . $safe_name . '"');
    }
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit();

} catch (Throwable $e) {
    http_response_code(500);
    exit('下載失敗');
}
