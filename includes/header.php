<?php
require_once __DIR__ . '/cookies.php';
require_once __DIR__ . '/csrf.php';
csrf_generate();

$show_status = isset($show_status) ? (bool) $show_status : false;
$base_url = isset($base_url) ? $base_url : './';
if ($base_url !== '' && substr($base_url, -1) !== '/') {
	$base_url .= '/';
}

// 檢查是否為管理員
$is_admin_user = false;
$is_club_officer = false;
if ($is_logged_in) {
	try {
		$pdo_check = get_db();
		$is_admin_user = is_current_user_admin($pdo_check);
		if (!$is_admin_user) {
			$is_club_officer = is_user_club_officer($pdo_check);
		}
	} catch (Throwable $e) {
		// ignore
	}
}

$flash_error = null;
$flash_success = null;
if (!empty($_SESSION['flash_error'])) {
	$flash_error = $_SESSION['flash_error'];
	unset($_SESSION['flash_error']);
}
if (!empty($_SESSION['flash_success'])) {
	$flash_success = $_SESSION['flash_success'];
	unset($_SESSION['flash_success']);
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

// 檢查是否為管理員
$is_admin_user = false;
if (!empty($user_raw)) {
	try {
		$pdo_check = get_db();
		$role_stmt = $pdo_check->prepare('SELECT role FROM users WHERE username = :u LIMIT 1');
		$role_stmt->execute([':u' => $user_raw]);
		$role_row = $role_stmt->fetch();
		$is_admin_user = ($role_row && $role_row['role'] === 'admin');
	} catch (Throwable $e) {
		// ignore
	}
}
?>
<header id="global-topbar" class="topbar" style="position: fixed; left: 0; right: 0; top: 0; z-index: 1000; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.06)">
	<div class="container nav">
		<a href="<?php echo $base_url; ?>index.php" class="brand">Club Form Studio</a>
		<button class="hamburger" id="hamburgerToggle" aria-label="選單" type="button">
			<span></span><span></span><span></span>
		</button>
		<nav class="menu" id="mainMenu">
			<a class="link-btn" href="<?php echo $base_url; ?>forms/forms_index.php">表單中心</a>
			<a class="link-btn" href="<?php echo $base_url; ?>users/user_index.php">個人中心</a>
			<a class="link-btn" href="<?php echo $base_url; ?>clubs/clubs_index.php">社團中心</a>
			<?php if ($is_admin_user) : ?>
				<a class="link-btn" href="<?php echo $base_url; ?>admin/admin_index.php" style="color:var(--accent);font-weight:700;">⚙ Admin</a>
			<?php endif; ?>
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

<?php if ($flash_error || $flash_success): ?>
<div class="container" style="padding-top:16px">
	<?php if ($flash_error): ?>
		<div class="error"><?php echo htmlspecialchars($flash_error); ?></div>
	<?php endif; ?>
	<?php if ($flash_success): ?>
		<div class="panel" style="padding:12px;border-color:#8bc9b4;background:#eef7f3"><?php echo htmlspecialchars($flash_success); ?></div>
	<?php endif; ?>
</div>
<?php endif; ?>

<script src="<?php echo $base_url; ?>js/sweetalert2@11.js"></script>
<script>
(function(){
	var topbar = document.getElementById('global-topbar');
	var hb = document.getElementById('hamburgerToggle');
	var mn = document.getElementById('mainMenu');

	function adjustPadding() {
		if (topbar) {
			document.documentElement.style.paddingTop = (topbar.offsetHeight || 64) + 'px';
		}
	}

	adjustPadding();

	var resizeTimer;
	window.addEventListener('resize', function(){
		clearTimeout(resizeTimer);
		resizeTimer = setTimeout(function(){
			adjustPadding();
			if (window.innerWidth > 900 && hb && mn) {
				hb.classList.remove('open');
				mn.classList.remove('open');
			}
		}, 120);
	});

	if (hb && mn) {
		hb.addEventListener('click', function(e){
			e.stopPropagation();
			hb.classList.toggle('open');
			mn.classList.toggle('open');
			adjustPadding();
		});

		mn.addEventListener('click', function(e){
			if (e.target.tagName === 'A') {
				hb.classList.remove('open');
				mn.classList.remove('open');
			}
		});

		document.addEventListener('click', function(e){
			if (!hb.contains(e.target) && !mn.contains(e.target)) {
				hb.classList.remove('open');
				mn.classList.remove('open');
			}
		});
	}
})();
</script>
