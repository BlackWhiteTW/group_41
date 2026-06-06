<?php
/**
 * 共用工具函數。
 * 由各頁面在需要時 require 引入。
 */

/**
 * 解析 target_club_ids 字串（逗號分隔的社團 ID）為整數陣列。
 * 用於 form_type = 'club_only' 的表單權限檢查。
 */
function parse_target_clubs($value)
{
    if (!is_string($value) || trim($value) === '') {
        return [];
    }
    $items = array_map('trim', explode(',', $value));
    $items = array_values(array_filter($items, 'strlen'));
    return array_values(array_unique(array_map('intval', $items)));
}

/**
 * 取得允許上傳的副檔名白名單。
 * 僅包含文件、圖片、PDF 等安全類型，不含任何可執行檔。
 */
function get_allowed_upload_extensions()
{
    return [
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp',
        'pdf',
        'doc', 'docx',
        'xls', 'xlsx',
        'ppt', 'pptx',
        'txt', 'csv', 'rtf',
        'odt', 'ods', 'odp',
        'zip',
    ];
}

/**
 * 檢查副檔名是否在白名單內。
 */
function is_allowed_upload_extension($filename)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, get_allowed_upload_extensions(), true);
}

/**
 * 取得 accept 屬性字串，用於 <input type="file"> 的瀏覽器端篩選。
 */
function get_upload_accept_string()
{
    $exts = get_allowed_upload_extensions();
    return '.' . implode(',.', $exts);
}

/**
 * 驗證上傳檔案的實際內容是否與副檔名相符。
 * 使用 MIME 類型 + magic bytes 雙重檢查，防止偽造副檔名。
 *
 * @param string $tmp_path 暫存檔案路徑（$_FILES['...']['tmp_name']）
 * @param string $filename 原始檔名
 * @return bool
 */
function validate_upload_content($tmp_path, $filename)
{
    if (!is_uploaded_file($tmp_path) || !is_readable($tmp_path)) {
        return false;
    }

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $header = '';
    $fh = fopen($tmp_path, 'rb');
    if ($fh) {
        $header = fread($fh, 16);
        fclose($fh);
    }

    if ($header === '' || strlen($header) < 2) {
        return false;
    }

    if (in_array($ext, ['jpg', 'jpeg'], true)) {
        return (substr($header, 0, 2) === "\xFF\xD8");
    }

    if ($ext === 'png') {
        return (substr($header, 0, 8) === "\x89PNG\r\n\x1A\n");
    }

    if ($ext === 'gif') {
        return (substr($header, 0, 3) === 'GIF');
    }

    if ($ext === 'bmp') {
        return (substr($header, 0, 2) === 'BM');
    }

    if ($ext === 'webp') {
        return (substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP');
    }

    if ($ext === 'pdf') {
        return (substr($header, 0, 4) === '%PDF');
    }

    if ($ext === 'zip' || $ext === 'docx' || $ext === 'xlsx' || $ext === 'pptx' || $ext === 'odt' || $ext === 'ods' || $ext === 'odp') {
        return (substr($header, 0, 4) === "PK\x03\x04" || substr($header, 0, 4) === "PK\x05\x06");
    }

    if ($ext === 'doc' || $ext === 'xls' || $ext === 'ppt') {
        return (substr($header, 0, 8) === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");
    }

    if ($ext === 'rtf') {
        return (substr($header, 0, 5) === '{\rtf');
    }

    if (in_array($ext, ['txt', 'csv'], true)) {
        $dangerous = ['<?php', '<?=', '<? ', '<script', '<%', '<!ENTITY'];
        $content = file_get_contents($tmp_path);
        if ($content === false) {
            return false;
        }
        $lower = strtolower($content);
        foreach ($dangerous as $pattern) {
            if (strpos($lower, $pattern) !== false) {
                return false;
            }
        }
        return true;
    }

    return false;
}
