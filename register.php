<?php
session_start();
require __DIR__ . '/includes/db.php';

$user = !empty($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : null;

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
    $club_mode = isset($_POST['club_mode']) ? $_POST['club_mode'] : 'existing';
    $existing_club = isset($_POST['existing_club']) ? trim($_POST['existing_club']) : '';
    $new_club_name = isset($_POST['new_club_name']) ? trim($_POST['new_club_name']) : '';

    if (strlen($username) < 3) $errors[] = '帳號至少 3 個字元';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = '請輸入有效的電子郵件';
    if (strlen($password) < 6) $errors[] = '密碼至少 6 個字元';
    if ($password !== $password_confirm) $errors[] = '密碼與確認密碼不相符';

    // 檢查 username/email 是否存在
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
    $stmt->execute([':u' => $username, ':e' => $email]);
    $exists = $stmt->fetch();
    if ($exists) $errors[] = '帳號或電子郵件已被使用';

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $club_category = ($club_mode === 'new' && $new_club_name) ? $new_club_name : ($existing_club ?: '未分類');

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare('INSERT INTO users (username, password, email, club_category, role) VALUES (:u, :p, :e, :c, :r)');
            $ins->execute([
                ':u' => $username,
                ':p' => $hash,
                ':e' => $email,
                ':c' => $club_category,
                ':r' => 'member'
            ]);
            $user_id = $pdo->lastInsertId();

            if ($club_mode === 'new' && $new_club_name) {
                $c = $pdo->prepare('INSERT INTO clubs (name, owner_user_id) VALUES (:n, :o)');
                $c->execute([':n' => $new_club_name, ':o' => $user_id]);
            }

            $pdo->commit();
            $_SESSION['user'] = $username;
            $success = true;
            header('Location: /group_41/index.php');
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = '註冊失敗：' . $e->getMessage();
        }
    }
}

// 直接輸出註冊頁面（已整合資源）
?>
<!doctype html>
<html lang="zh-Hant">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title>註冊 | 社團表單系統</title>
        <link rel="stylesheet" href="/group_41/css/app.css" />
    </head>
    <body>
        <header class="topbar">
            <div class="container nav">
                <a class="brand" href="/group_41/index.php">Club Form Studio</a>
                <nav class="menu">
                    <a class="link-btn" href="/group_41/index.php">首頁</a>
                    <a class="link-btn" href="/group_41/forms/list.php">表單列表</a>
                    <a class="link-btn" href="/group_41/forms/create.php">新增表單</a>
                    <?php if ($user) : ?>
                        <a class="btn btn-primary" href="/group_41/logout.php">登出</a>
                    <?php else : ?>
                        <a class="link-btn" href="/group_41/login.php">登入</a>
                        <a class="btn btn-primary" href="/group_41/register.php">註冊</a>
                    <?php endif; ?>
                </nav>
            </div>
        </header>

        <main class="form-page">
            <section class="form-card">
                <a href="/group_41/index.php" class="muted">← 回首頁</a>
                <h2>建立帳號</h2>
                <p class="muted">可選擇加入既有社團或建立新社團。</p>

                <?php if (!empty($errors)): ?>
                    <div class="error">
                        <ul>
                        <?php foreach ($errors as $e): ?>
                            <li><?php echo htmlspecialchars($e); ?></li>
                        <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form id="registerForm" method="post" action="/group_41/register.php">
                    <div class="field">
                        <label for="reg_username">帳號</label>
                        <input id="reg_username" name="username" required minlength="3" placeholder="至少 3 個字" value="<?php echo isset($_POST['username'])?htmlspecialchars($_POST['username']):''; ?>" />
                    </div>

                    <div class="field">
                        <label for="reg_email">電子郵件</label>
                        <input id="reg_email" name="email" type="email" required placeholder="example@school.edu" value="<?php echo isset($_POST['email'])?htmlspecialchars($_POST['email']):''; ?>" />
                    </div>

                    <div class="field">
                        <label>社團設定</label>
                        <label><input type="radio" id="club_mode_existing" name="club_mode" value="existing" checked /> 加入既有社團</label>
                        <label><input type="radio" id="club_mode_new" name="club_mode" value="new" /> 建立新社團</label>
                    </div>

                    <div class="field" id="existingClubWrap">
                        <label for="existing_club">選擇社團</label>
                        <select id="existing_club" name="existing_club" required>
                            <option value="">請選擇</option>
                            <option value="學生會">學生會</option>
                            <option value="資訊社">資訊社</option>
                            <option value="其他">其他</option>
                        </select>
                    </div>

                    <div class="field" id="newClubWrap" style="display:none">
                        <label for="new_club_name">新社團名稱</label>
                        <input id="new_club_name" name="new_club_name" placeholder="例如：資工系學會" />
                    </div>

                    <div class="field">
                        <label for="reg_password">密碼</label>
                        <input id="reg_password" name="password" type="password" required minlength="6" placeholder="至少 6 個字" />
                    </div>

                    <div class="field">
                        <label for="reg_password_confirm">確認密碼</label>
                        <input id="reg_password_confirm" name="password_confirm" type="password" required minlength="6" placeholder="請再次輸入密碼" />
                    </div>

                    <button class="btn btn-primary" style="width:100%" type="submit">註冊</button>
                </form>
            </section>
        </main>

        <script src="/group_41/js/app.js"></script>
    </body>
</html>

<?php
exit();

