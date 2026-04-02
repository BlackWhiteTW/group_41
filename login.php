<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/db.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/functions.php';

if (is_logged_in()) {
    header('Location: /group_41/index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!$username || !$password) {
        $error = '帳號和密碼不能為空';
    } else {
        try {
            $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $is_valid_password = false;
            if ($user) {
                // Preferred: modern password_hash()/password_verify()
                if (password_verify($password, $user['password'])) {
                    $is_valid_password = true;
                } else {
                    // Backward compatibility for old SQL seed data using SHA2(..., 256)
                    $legacy_sha256 = hash('sha256', $password);
                    if (hash_equals($user['password'], $legacy_sha256)) {
                        $is_valid_password = true;

                        // Auto-migrate legacy hash to bcrypt after successful login
                        $new_hash = password_hash($password, PASSWORD_BCRYPT);
                        $sql_update = "UPDATE users SET password = ? WHERE id = ?";
                        $stmt_update = $pdo->prepare($sql_update);
                        $stmt_update->execute([$new_hash, $user['id']]);
                    }
                }
            }

            if ($user && $is_valid_password) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header('Location: /group_41/index.php');
                exit();
            } else {
                $error = '帳號或密碼錯誤';
            }
        } catch (Exception $e) {
            $error = '登入失敗，請稍後重試';
        }
    }
}
render_template('auth/login.php', [
    'error' => $error
]);

