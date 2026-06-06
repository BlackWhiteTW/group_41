<?php
/**
 * 郵件發送工具
 *
 * 優先使用 PHP mail()，可選設定 SMTP 覆蓋。
 * XAMPP 環境需設定 php.ini 或 sendmail.ini 中的 SMTP/port。
 *
 * SMTP 設定範例（Gmail 應用程式密碼）：
 *   編輯 php.ini:
 *     SMTP=smtp.gmail.com
 *     smtp_port=587
 *     sendmail_from=your@gmail.com
 *   編輯 sendmail.ini:
 *     smtp_server=smtp.gmail.com
 *     smtp_port=587
 *     auth_username=your@gmail.com
 *     auth_password=your-app-password
 */

/**
 * 發送郵件
 *
 * @param string $to      收件人 email
 * @param string $subject 主旨
 * @param string $body    HTML 內容
 * @return bool
 */
function send_email($to, $subject, $body)
{
    $from = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@clubform.local';
    $from_name = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : '社團表單系統';

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=utf-8',
        'From: ' . _mail_encode_header($from_name) . ' <' . $from . '>',
        'Reply-To: ' . $from,
        'X-Mailer: PHP/' . phpversion(),
    ];

    $subject_encoded = _mail_encode_header($subject);

    $wrapped = '<!DOCTYPE html><html><head><meta charset="utf-8"></head>'
        . '<body style="font-family:Arial,sans-serif;color:#132017;max-width:520px;margin:0 auto;padding:20px">'
        . $body
        . '</body></html>';

    return mail($to, $subject_encoded, $wrapped, implode("\r\n", $headers));
}

/**
 * 編碼郵件標頭中的中文
 */
function _mail_encode_header($text)
{
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

/**
 * 產生密碼重設郵件內容
 */
function build_reset_email_body($username, $reset_url)
{
    $site_name = defined('SITE_NAME') ? SITE_NAME : '社團表單系統';
    $site_url = defined('SITE_URL') ? SITE_URL : 'http://localhost/group_41';

    return '
<div style="background:#f5faf7;border-radius:12px;padding:24px;margin:16px 0">
    <h2 style="color:#0b7a5a;margin:0 0 16px">密碼重設請求</h2>
    <p>' . htmlspecialchars($username) . ' 您好，</p>
    <p>我們收到您的密碼重設請求。請點擊下方按鈕重設密碼（連結有效期 1 小時）：</p>
    <div style="text-align:center;margin:24px 0">
        <a href="' . htmlspecialchars($reset_url) . '" style="display:inline-block;background:#0b7a5a;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:600;font-size:16px">重設密碼</a>
    </div>
    <p style="font-size:13px;color:#6b7a72">若無法點擊按鈕，請複製以下網址到瀏覽器：<br>
    ' . htmlspecialchars($reset_url) . '</p>
    <p style="font-size:13px;color:#6b7a72">若您未請求重設密碼，請忽略此郵件。</p>
</div>
<p style="font-size:12px;color:#aaa">此郵件由 ' . htmlspecialchars($site_name) . ' 系統自動發送，請勿回覆。</p>';
}
