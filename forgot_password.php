<?php
require_once './includes/cookies.php';
require_once './includes/csrf.php';
require './includes/db.php';

$user = !empty($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : null;
$errors = [];
$message = '';
$sent = false;
$reset_link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = '表單驗證失敗，請重新整理後再試。';
    } else {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '請輸入有效的電子郵件。';
        }

        if (empty($errors)) {
            $pdo = get_db();
            $stmt = $pdo->prepare('SELECT id, username FROM users WHERE email = :e LIMIT 1');
            $stmt->execute([':e' => $email]);
            $user_row = $stmt->fetch();

            if ($user_row) {
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $ins = $pdo->prepare('INSERT INTO password_resets (email, token, expires_at) VALUES (:e, :t, :x)');
                $ins->execute([
                    ':e' => $email,
                    ':t' => $token,
                    ':x' => $expires_at
                ]);

                $reset_link = './reset_password.php?token=' . $token;
                $sent = true;
                $message = '重設密碼連結已產生。';
            } else {
                $message = '若此電子郵件存在於系統中，重設連結已產生。';
                $sent = true;
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
    <title>忘記密碼 | 社團表單系統</title>
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
            <h2>忘記密碼</h2>
            <p class="muted">輸入註冊時使用的電子郵件，系統將產生重設密碼連結。</p>

            <?php if (!empty($errors)): ?>
                <div class="error">
                    <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($sent): ?>
                <div class="panel" style="padding:20px;margin-top:12px;border-color:#8bc9b4;background:#eef7f3">
                    <p><?php echo htmlspecialchars($message); ?></p>
                    <?php if ($reset_link): ?>
                        <p class="muted" style="word-break:break-all">
                            <a href="<?php echo htmlspecialchars($reset_link); ?>"><?php echo htmlspecialchars($reset_link); ?></a>
                        </p>
                        <p class="muted" style="font-size:0.85rem;margin-top:8px">連結有效期 1 小時。在實際環境中，此連結將透過電子郵件寄送。</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form method="post" action="./forgot_password.php">
                    <?php echo csrf_field(); ?>
                    <div class="field">
                        <label for="email">電子郵件</label>
                        <input id="email" name="email" type="email" required placeholder="註冊時使用的信箱" />
                    </div>
                    <button class="btn btn-primary" style="width:100%" type="submit">產生重設連結</button>
                </form>
            <?php endif; ?>
        </section>
    </main>

    <script src="./js/app.js"></script>
    <footer class="footer container">社團表單系統</footer>
</body>
</html>
<?php
exit();
