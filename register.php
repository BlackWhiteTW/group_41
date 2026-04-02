<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/db.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/functions.php';

if (is_logged_in()) {
    header('Location: /group_41/index.php');
    exit();
}

$success = '';
$error = '';

try {
    $stmtClubs = $pdo->query("SELECT name FROM clubs ORDER BY name ASC");
    $existing_clubs = $stmtClubs->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $existing_clubs = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $club_mode = $_POST['club_mode'] ?? 'existing';
    $existing_club = trim($_POST['existing_club'] ?? '');
    $new_club_name = trim($_POST['new_club_name'] ?? '');
    $club_category = '';
    
    // 驗證
    if (!$username || !$email || !$password) {
        $error = '所有欄位都是必填的';
    } elseif (strlen($username) < 3) {
        $error = '帳號至少需要3個字符';
    } elseif ($password !== $password_confirm) {
        $error = '兩次輸入的密碼不一致';
    } elseif (strlen($password) < 6) {
        $error = '密碼至少需要6個字符';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '請輸入有效的電子郵件地址';
    } elseif (!in_array($club_mode, ['existing', 'new'])) {
        $error = '社團選擇模式無效';
    } else {
        try {
            if ($club_mode === 'existing') {
                if (!$existing_club) {
                    throw new Exception('請選擇要加入的社團');
                }

                $sql = "SELECT id, name FROM clubs WHERE name = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$existing_club]);
                $club = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$club) {
                    throw new Exception('找不到指定社團，請重新選擇');
                }

                $club_category = $club['name'];
            } else {
                if (!$new_club_name) {
                    throw new Exception('請輸入新社團名稱');
                }

                if (mb_strlen($new_club_name, 'UTF-8') < 2) {
                    throw new Exception('新社團名稱至少 2 個字');
                }

                if (mb_strlen($new_club_name, 'UTF-8') > 100) {
                    throw new Exception('新社團名稱不能超過 100 個字');
                }

                $club_category = $new_club_name;
            }

            // 檢查帳號是否已存在
            $sql = "SELECT id FROM users WHERE username = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = '該帳號已被註冊';
            } else {
                // 檢查郵箱是否已存在
                $sql = "SELECT id FROM users WHERE email = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = '該電子郵件已被使用';
                } else {
                    $pdo->beginTransaction();

                    // 插入新用戶（建立新社團者自動為社團幹部）
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $role = ($club_mode === 'new') ? 'club_officer' : 'member';
                    $sql = "INSERT INTO users (username, password, email, club_category, role) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$username, $hashed_password, $email, $club_category, $role]);

                    $new_user_id = (int)$pdo->lastInsertId();

                    if ($club_mode === 'new') {
                        $sql = "SELECT id FROM clubs WHERE name = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$club_category]);
                        if ($stmt->fetch()) {
                            throw new Exception('社團名稱已存在，請改用其他名稱');
                        }

                        $sql = "INSERT INTO clubs (name, owner_user_id) VALUES (?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$club_category, $new_user_id]);
                    }

                    $pdo->commit();
                    $success = '註冊成功！請 <a href="login.php" class="alert-link">登入</a> 您的帳號';
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage() ?: '註冊失敗，請稍後重試';
        }
    }
}
render_template('auth/register.php', [
    'success' => $success,
    'error' => $error,
    'existing_clubs' => $existing_clubs
]);

