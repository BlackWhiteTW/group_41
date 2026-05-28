<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

// 閒置超時（秒）: 3600 = 1 小時
$idle_timeout = 3600;
$session_expired = false;
if (isset($_SESSION['last_activity'])) {
	$inactive = time() - $_SESSION['last_activity'];
	if ($inactive > $idle_timeout) {
		// 若使用者有 remember cookie，延長 session 與 cookie 的存活期
		if (isset($_COOKIE['remember_active']) && $_COOKIE['remember_active'] === '1') {
			if (session_status() !== PHP_SESSION_ACTIVE) {
				session_start();
			}
			session_regenerate_id(true);
			$_SESSION['last_activity'] = time();
			// 延長 remember cookie 和 session cookie
			setcookie('remember_active', '1', time() + $idle_timeout, '/');
			setcookie(session_name(), session_id(), time() + $idle_timeout, '/', '', false, true);
		} else {
			// 沒有 remember cookie，清除 session
			session_unset();
			session_destroy();
			if (ini_get("session.use_cookies")) {
				$params = session_get_cookie_params();
				setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
			} else {
				setcookie(session_name(), '', time() - 3600, '/');
			}
			// 也清除 remember cookie（以防）
			setcookie('remember_active', '', time() - 3600, '/');
			$session_expired = true;
			$user_raw = null;
			$user = null;
		}
	}
}

if (!isset($session_expired) || $session_expired === false) {
	// 更新最後活動時間
	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}
	$_SESSION['last_activity'] = time();
}

if (!isset($user_raw)) {
	$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
}
if (!isset($user)) {
	$user = !empty($user_raw) ? htmlspecialchars($user_raw) : null;
}

$show_status = isset($show_status) ? (bool) $show_status : false;
$base_url = isset($base_url) ? $base_url : './';
if ($base_url !== '' && substr($base_url, -1) !== '/') {
	$base_url .= '/';
}
?>
<header class="topbar">
	<div class="container nav">
		<a href="<?php echo $base_url; ?>index.php" class="brand">Club Form Studio</a>
		<nav class="menu">
			<a class="link-btn" href="<?php echo $base_url; ?>index.php">首頁</a>
			<a class="link-btn" href="<?php echo $base_url; ?>forms/list.php">表單列表</a>
			<a class="link-btn" href="<?php echo $base_url; ?>forms/create.php">新增表單</a>
			<a class="link-btn" href="<?php echo $base_url; ?>clubs/manage.php">社團資訊</a>
			<a class="link-btn" href="<?php echo $base_url; ?>forms/my_forms.php">我的表單</a>
			<?php if ($user) : ?>
				<a class="btn btn-primary" href="<?php echo $base_url; ?>logout.php">登出</a>
			<?php else : ?>
				<a class="link-btn" href="<?php echo $base_url; ?>login.php">登入</a>
				<a class="btn btn-primary" href="<?php echo $base_url; ?>register.php">註冊</a>
			<?php endif; ?>
		</nav>
		<?php if ($show_status) : ?>
			<span class="muted">登入狀態：<?php echo $user ? '已登入：' . $user : '未登入'; ?></span>
		<?php endif; ?>
	</div>
</header>
