<?php
/**
 * Centralized session & cookie management.
 * Include this at the top of any page that needs user state.
 * Sets $user_raw, $user, $is_logged_in in global scope.
 *
 * Remember-me uses token-based cookies (userId:randomHex) with SHA256
 * hash stored in users.remember_token_hash. Each user keeps only the
 * latest token (logging in on a new device replaces the old one).
 */

// ─── Boot: start session & check timeout ───
function start_user_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $idle_timeout = 3600;
    $session_expired = false;

    if (isset($_SESSION['last_activity'])) {
        $inactive = time() - $_SESSION['last_activity'];
        if ($inactive > $idle_timeout) {
            $session_expired = _try_remember_token();
            if (!$session_expired) {
                session_regenerate_id(true);
                $_SESSION['last_activity'] = time();
                $ttl = 3600;
                // Extend the cookie TTL
                if (!empty($_COOKIE['remember_active'])) {
                    setcookie('remember_active', $_COOKIE['remember_active'], time() + $ttl, '/');
                }
                setcookie(session_name(), session_id(), time() + $ttl, '/', '', false, true);
            } else {
                session_unset();
                session_destroy();
                _clear_session_cookie();
                setcookie('remember_active', '', time() - 3600, '/');
            }
        }
    }

    if (!$session_expired) {
        $_SESSION['last_activity'] = time();
    }

    $raw = (!$session_expired && isset($_SESSION['user'])) ? $_SESSION['user'] : null;
    $GLOBALS['user_raw'] = $raw;
    $GLOBALS['user'] = $raw ? htmlspecialchars($raw) : null;
    $GLOBALS['is_logged_in'] = !empty($raw);
}

// ─── Login / register ───
function set_user_session($username) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_regenerate_id(true);
    $_SESSION['user'] = $username;
    $_SESSION['last_activity'] = time();
    $ttl = 3600;

    // Generate remember token
    _create_remember_token($username, $ttl);

    setcookie(session_name(), session_id(), time() + $ttl, '/', '', false, true);
}

// ─── Logout ───
function clear_user_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Delete remember token from DB
    _delete_remember_token();
    $_SESSION = [];
    session_unset();
    session_destroy();
    _clear_session_cookie();
    setcookie('remember_active', '', time() - 3600, '/');
}

// ─── Update stored username (after rename) ───
function update_user_session($new_username) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user'] = $new_username;
}

// ─── Helpers ───

function get_current_user_id($pdo) {
    $raw = $GLOBALS['user_raw'] ?? null;
    if (!$raw) return null;
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $raw]);
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : null;
}

function is_current_user_admin($pdo) {
    $raw = $GLOBALS['user_raw'] ?? null;
    if (!$raw) return false;
    try {
        $stmt = $pdo->prepare('SELECT role FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $raw]);
        $row = $stmt->fetch();
        return $row && $row['role'] === 'admin';
    } catch (Throwable $e) {
        return false;
    }
}

function is_user_club_officer($pdo) {
    $raw = $GLOBALS['user_raw'] ?? null;
    if (!$raw) return false;
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM club_memberships m JOIN users u ON u.id = m.user_id WHERE u.username = :u AND m.role IN ('owner', 'club_officer') LIMIT 1");
        $stmt->execute([':u' => $raw]);
        return (bool) $stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

// ─── Remember-token internals ───

function _try_remember_token() {
    $cookieVal = $_COOKIE['remember_active'] ?? '';
    if ($cookieVal === '') return true;

    $parts = explode(':', $cookieVal);
    $userId = (int)($parts[0] ?? 0);
    $rawToken = $parts[1] ?? '';
    if ($userId <= 0 || $rawToken === '') return true;

    try {
        global $pdo;
        if (!isset($pdo)) return true;

        $tokenHash = hash('sha256', $rawToken);
        $stmt = $pdo->prepare('SELECT username FROM users WHERE id = :uid AND remember_token_hash = :hash LIMIT 1');
        $stmt->execute([':uid' => $userId, ':hash' => $tokenHash]);
        $row = $stmt->fetch();

        if ($row) {
            $_SESSION['user'] = $row['username'];
            return false; // not expired
        }
    } catch (Throwable $e) {
        // DB unavailable → expire
    }
    return true; // expired
}

function _create_remember_token($username, $ttl) {
    try {
        global $pdo;
        if (!isset($pdo)) return;

        $uStmt = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
        $uStmt->execute([':u' => $username]);
        $userRow = $uStmt->fetch();
        if (!$userRow) return;

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $stmt = $pdo->prepare('UPDATE users SET remember_token_hash = :hash WHERE id = :uid');
        $stmt->execute([':hash' => $tokenHash, ':uid' => $userRow['id']]);

        setcookie('remember_active', $userRow['id'] . ':' . $rawToken, time() + $ttl, '/');
    } catch (Throwable $e) {
        // Silently fail — session-only auth still works
    }
}

function _delete_remember_token() {
    $cookieVal = $_COOKIE['remember_active'] ?? '';
    if ($cookieVal === '') return;

    $parts = explode(':', $cookieVal);
    $userId = (int)($parts[0] ?? 0);
    if ($userId <= 0) return;

    try {
        global $pdo;
        if (!isset($pdo)) return;
        $stmt = $pdo->prepare('UPDATE users SET remember_token_hash = NULL WHERE id = :uid');
        $stmt->execute([':uid' => $userId]);
    } catch (Throwable $e) {
        // ignore
    }
}

function _clear_session_cookie() {
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    } else {
        setcookie(session_name(), '', time() - 3600, '/');
    }
}

// ─── Auto-boot ───
start_user_session();
