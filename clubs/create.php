<?php
require_once '../includes/cookies.php';
require_once '../includes/csrf.php';
require '../includes/db.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$user = !empty($user_raw) ? htmlspecialchars($user_raw) : null;
$current_user = null;
$errors = [];
$success = '';

if (!$user_raw) {
    header('Location: ../login.php');
    exit();
}

try {
    $pdo = get_db();
    $u = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
    $u->execute([':u' => $user_raw]);
    $current_user = $u->fetch();
    if (!$current_user) {
        header('Location: ../login.php');
        exit();
    }
} catch (Throwable $e) {
    $errors[] = '資料庫連線失敗。';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = '表單驗證失敗，請重新整理後再試。';
    } else {
        $club_name = isset($_POST['name']) ? trim($_POST['name']) : '';
        if ($club_name === '') {
            $errors[] = '請輸入社團名稱。';
        } elseif (mb_strlen($club_name) < 2) {
            $errors[] = '社團名稱至少需要 2 個字元。';
        }

        if (empty($errors)) {
            $exist = $pdo->prepare('SELECT id FROM clubs WHERE name = :n LIMIT 1');
            $exist->execute([':n' => $club_name]);
            if ($exist->fetch()) {
                $errors[] = '此社團名稱已存在。';
            }
        }

        if (empty($errors)) {
            $pdo->beginTransaction();
            try {
                $c = $pdo->prepare('INSERT INTO clubs (name, owner_user_id) VALUES (:n, :o)');
                $c->execute([':n' => $club_name, ':o' => $current_user['id']]);
                $club_id = (int) $pdo->lastInsertId();

                $m = $pdo->prepare('INSERT INTO club_memberships (user_id, club_id, role) VALUES (:u, :c, :r)');
                $m->execute([':u' => $current_user['id'], ':c' => $club_id, ':r' => 'club_officer']);

                $pdo->commit();
                $success = '社團「' . $club_name . '」已建立。';
                header('Location: setting.php?id=' . $club_id);
                exit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = '建立社團失敗。';
            }
        }
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>創建社團 | 社團表單系統</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../css/app.css" />
</head>
<body>
    <?php $base_url = '../'; require '../includes/header.php'; ?>

    <?php require __DIR__ . '/../includes/right.php'; ?>

    <main class="section">
        <div class="container">
            <h1>創建社團</h1>
            <p class="muted">建立一個新社團，你將自動成為社團持有人。</p>

            <?php if (!empty($errors)): ?>
                <div class="error">
                    <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="panel" style="padding:20px;max-width:500px">
                <form method="post" action="./create.php">
                    <?php echo csrf_field(); ?>
                    <div class="field">
                        <label for="club_name">社團名稱</label>
                        <input id="club_name" name="name" required minlength="2" placeholder="例如：資工系學會" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" />
                    </div>
                    <button class="btn btn-primary" type="submit">建立社團</button>
                    <a class="btn btn-ghost" href="./clubs_index.php">返回社團中心</a>
                </form>
            </div>
        </div>
    </main>

    <footer class="footer container">社團表單系統</footer>
    <script src="../js/app.js"></script>
</body>
</html>
<?php
exit();
