<?php
require 'includes/db.php';

try {
    $pdo = get_db();

    $pdo->exec("ALTER TABLE forms ADD COLUMN allow_resubmit TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否允許重複填答：1=可重複, 0=一人一次' AFTER close_at");

    echo '<p style="color:green">allow_resubmit 欄位已成功新增至 forms 表。</p>';
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo '<p style="color:orange">欄位已存在，無需重複新增。</p>';
    } else {
        echo '<p style="color:red">錯誤：' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}

echo '<p>請刪除此檔案：migrate_allow_resubmit.php</p>';
