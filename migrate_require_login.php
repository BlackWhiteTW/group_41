<?php
require 'includes/db.php';

try {
    $pdo = get_db();
    $pdo->exec("ALTER TABLE forms ADD COLUMN require_login TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否需登入才能填寫：1=需登入, 0=免登入' AFTER allow_resubmit");
    echo '<p style="color:green">require_login 欄位已成功新增至 forms 表。</p>';
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo '<p style="color:orange">欄位已存在，無需重複新增。</p>';
    } else {
        echo '<p style="color:red">錯誤：' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}

echo '<p>請刪除此檔案：migrate_require_login.php</p>';
