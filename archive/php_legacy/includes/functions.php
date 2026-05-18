<?php
if (!defined('FUNCTIONS_LOADED')) {
    define('FUNCTIONS_LOADED', true);
    
    if (!session_id()) {
        session_start();
    }

    // 檢查用戶是否已登入
    function is_logged_in() {
        return isset($_SESSION['user_id']);
    }

    // 獲取當前用戶信息
    function get_current_user_info() {
        global $pdo;
        if (!is_logged_in()) {
            return null;
        }
        
        $sql = "SELECT id, username, email, club_category, role FROM users WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 檢查用戶權限
    function check_permission($required_role) {
        $user = get_current_user_info();
        if (!$user) {
            return false;
        }
        
        $role_hierarchy = ['guest' => 0, 'member' => 1, 'club_officer' => 2, 'admin' => 3];
        return $role_hierarchy[$user['role']] >= $role_hierarchy[$required_role];
    }

    // 重定向到登入頁
    function redirect_to_login() {
        header('Location: /group_41/login.php');
        exit();
    }

    // HTML安全輸出
    function escape($data) {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }

    // 驗證表單令牌（CSRF防護）
    function generate_csrf_token() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    function verify_csrf_token($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    // 獲取用戶IP
    function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    // 密碼驗證
    function verify_password($password, $hash) {
        return password_verify($password, $hash);
    }

    // 密碼加密
    function hash_password($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    // 模板渲染：把畫面檔案移到 templates，控制器只保留邏輯。
    function render_template($template_path, array $data = []) {
        $base_path = $_SERVER['DOCUMENT_ROOT'] . '/group_41/templates/';
        $full_path = $base_path . ltrim($template_path, '/');

        if (!file_exists($full_path)) {
            throw new RuntimeException('Template not found: ' . $template_path);
        }

        extract($data, EXTR_SKIP);
        include $full_path;
    }
}
?>

