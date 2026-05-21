<?php
session_start();
require __DIR__ . '/includes/db.php';

$user = !empty($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : null;

$pdo = get_db();
$club_options = array_column($pdo->query('SELECT name FROM clubs ORDER BY name ASC')->fetchAll(), 'name');

$errors = [];
$success = false;
$club_mode = 'existing';
$existing_club = '';
$new_club_name = '';

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

    if (!in_array($club_mode, ['existing', 'new'], true)) {
        $club_mode = 'existing';
    }
    if ($club_mode === 'existing') {
        if (empty($club_options)) {
            $errors[] = '目前尚無社團，請選擇建立新社團。';
        } elseif ($existing_club === '' || !in_array($existing_club, $club_options, true)) {
            $errors[] = '請選擇有效的社團。';
        }
    } else {
        if ($new_club_name === '') {
            $errors[] = '請輸入新社團名稱。';
        }
    }

    // 檢查 username/email 是否存在
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
    $stmt->execute([':u' => $username, ':e' => $email]);
    $exists = $stmt->fetch();
    if ($exists) $errors[] = '帳號或電子郵件已被使用';

    if ($club_mode === 'new' && $new_club_name !== '') {
        $c_exist = $pdo->prepare('SELECT id FROM clubs WHERE name = :n LIMIT 1');
        $c_exist->execute([':n' => $new_club_name]);
        if ($c_exist->fetch()) {
            $errors[] = '社團名稱已存在，請改用加入既有社團。';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare('INSERT INTO users (username, password, email, role) VALUES (:u, :p, :e, :r)');
            $ins->execute([
                ':u' => $username,
                ':p' => $hash,
                ':e' => $email,
                ':r' => 'member'
            ]);
            $user_id = $pdo->lastInsertId();

            if ($club_mode === 'new' && $new_club_name) {
                $c = $pdo->prepare('INSERT INTO clubs (name, owner_user_id) VALUES (:n, :o)');
                $c->execute([':n' => $new_club_name, ':o' => $user_id]);
                $club_id = (int) $pdo->lastInsertId();

                $m = $pdo->prepare('INSERT INTO club_memberships (user_id, club_id, role) VALUES (:u, :c, :r)');
                $m->execute([
                    ':u' => $user_id,
                    ':c' => $club_id,
                    ':r' => 'club_officer'
                ]);
            } else {
                $club_stmt = $pdo->prepare('SELECT id FROM clubs WHERE name = :n LIMIT 1');
                $club_stmt->execute([':n' => $existing_club]);
                $club_row = $club_stmt->fetch();
                if (!$club_row) {
                    throw new RuntimeException('找不到選擇的社團。');
                }

                $m = $pdo->prepare('INSERT INTO club_memberships (user_id, club_id, role) VALUES (:u, :c, :r)');
                $m->execute([
                    ':u' => $user_id,
                    ':c' => (int) $club_row['id'],
                    ':r' => 'member'
                ]);
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
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;600;700&display=swap"
            rel="stylesheet"
        />
        <link rel="stylesheet" href="/group_41/css/app.css" />
    </head>
    <body>
        <?php require __DIR__ . '/includes/header.php'; ?>

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
                        <label><input type="radio" id="club_mode_existing" name="club_mode" value="existing" <?php echo $club_mode === 'existing' ? 'checked' : ''; ?> /> 加入既有社團</label>
                        <label><input type="radio" id="club_mode_new" name="club_mode" value="new" <?php echo $club_mode === 'new' ? 'checked' : ''; ?> /> 建立新社團</label>
                    </div>

                    <div class="field" id="existingClubWrap" style="<?php echo $club_mode === 'new' ? 'display:none' : ''; ?>">
                        <label for="existing_club">選擇社團</label>
                        <select id="existing_club" name="existing_club" <?php echo empty($club_options) ? 'disabled' : 'required'; ?>>
                            <?php if (empty($club_options)) : ?>
                                <option value="">尚無社團，請建立新社團</option>
                            <?php else : ?>
                                <option value="">請選擇</option>
                                <?php foreach ($club_options as $club_name) : ?>
                                    <option value="<?php echo htmlspecialchars($club_name); ?>" <?php echo $existing_club === $club_name ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($club_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="field" id="newClubWrap" style="<?php echo $club_mode === 'new' ? '' : 'display:none'; ?>">
                        <label for="new_club_name">新社團名稱</label>
                        <input id="new_club_name" name="new_club_name" placeholder="例如：資工系學會" value="<?php echo htmlspecialchars($new_club_name); ?>" />
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

