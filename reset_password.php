<?php
require_once './includes/cookies.php';
require_once './includes/csrf.php';
require './includes/db.php';

$user = !empty($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : null;
$errors = [];
$success = false;
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$valid_token = false;
$token_email = '';

if ($token === '') {
    $errors[] = '缺少重設令牌，請從忘記密碼頁面重新操作。';
} else {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, email, expires_at, used FROM password_resets WHERE token = :t ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([':t' => $token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        $errors[] = '無效的重設令牌。';
    } elseif ((int)$reset['used'] === 1) {
        $errors[] = '此重設連結已被使用過。';
    } elseif (strtotime($reset['expires_at']) < time()) {
        $errors[] = '此重設連結已過期（有效期 1 小時），請重新申請。';
    } else {
        $valid_token = true;
        $token_email = $reset['email'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = '表單驗證失敗，請重新整理後再試。';
    } else {
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        if (strlen($new_password) < 6) {
            $errors[] = '新密碼至少需要 6 個字元。';
        }
        if ($new_password !== $confirm_password) {
            $errors[] = '密碼與確認密碼不相符。';
        }

        if (empty($errors)) {
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            $pdo->beginTransaction();
            try {
                $upd = $pdo->prepare('UPDATE users SET password = :p WHERE email = :e LIMIT 1');
                $upd->execute([':p' => $hash, ':e' => $token_email]);

                $mark = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = :t LIMIT 1');
                $mark->execute([':t' => $token]);

                $pdo->commit();
                $success = true;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = '密碼更新失敗，請稍後再試。';
            }
        }
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>重設密碼 | 社團表單系統</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="./css/app.css" />
</head>
<body>
    <?php $base_url = './'; require './includes/header.php'; ?>

    <main class="form-page">
        <section class="form-card">
            <a href="./login.php" class="muted">← 回登入頁</a>
            <h2>重設密碼</h2>

            <?php if (!empty($errors)): ?>
                <div class="error">
                    <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="panel" style="padding:20px;margin-top:12px;border-color:#8bc9b4;background:#eef7f3">
                    <p>密碼已成功更新。</p>
                    <a href="./login.php" class="btn btn-primary" style="margin-top:12px">前往登入</a>
                </div>
            <?php elseif ($valid_token): ?>
                <p class="muted">為 <?php echo htmlspecialchars($token_email); ?> 設定新密碼。</p>
                <form method="post" action="./reset_password.php?token=<?php echo htmlspecialchars($token); ?>">
                    <?php echo csrf_field(); ?>
                    <div class="field">
                        <label for="new_password">新密碼</label>
                        <input id="new_password" name="new_password" type="password" required minlength="6" placeholder="至少 6 個字元" />
                    </div>
                    <div class="field">
                        <label for="confirm_password">確認密碼</label>
                        <input id="confirm_password" name="confirm_password" type="password" required minlength="6" placeholder="請再次輸入密碼" />
                    </div>
                    <button class="btn btn-primary" style="width:100%" type="submit">更新密碼</button>
                </form>
            <?php else: ?>
                <p class="muted">請從忘記密碼頁面重新申請重設連結。</p>
                <a href="./forgot_password.php" class="btn btn-ghost" style="margin-top:12px">忘記密碼</a>
            <?php endif; ?>
        </section>
    </main>

    <script src="./js/app.js"></script>
</body>
</html>
<?php
exit();
