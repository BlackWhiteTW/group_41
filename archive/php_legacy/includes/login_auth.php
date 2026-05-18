<?php
// Login database helper using the legacy PDO connection.
function login_authenticate($username, $password)
{
    global $pdo;

    if (!isset($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return null;
    }

    $stored = $user['password'];
    $ok = false;

    $info = password_get_info($stored);
    if ($info['algo'] !== 0) {
        if (password_verify($password, $stored)) {
            $ok = true;
            if (password_needs_rehash($stored, PASSWORD_BCRYPT)) {
                $newhash = password_hash($password, PASSWORD_BCRYPT);
                $u = $pdo->prepare('UPDATE users SET password = :p WHERE id = :id');
                $u->execute([':p' => $newhash, ':id' => $user['id']]);
            }
        }
    }

    if (!$ok && strlen($stored) === 64 && ctype_xdigit($stored)) {
        if (hash('sha256', $password) === $stored) {
            $newhash = password_hash($password, PASSWORD_BCRYPT);
            $u = $pdo->prepare('UPDATE users SET password = :p WHERE id = :id');
            $u->execute([':p' => $newhash, ':id' => $user['id']]);
            $ok = true;
        }
    }

    return $ok ? $user : null;
}
