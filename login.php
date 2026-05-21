<?php
session_start();

$user = !empty($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	require __DIR__ . '/archive/php_legacy/includes/db.php';
	require __DIR__ . '/archive/php_legacy/includes/login_auth.php';
	$username = isset($_POST['username']) ? trim($_POST['username']) : '';
	$password = isset($_POST['password']) ? $_POST['password'] : '';

	if ($username === '' || $password === '') {
		$error = '請輸入帳號與密碼';
	} else {
		$auth_user = login_authenticate($username, $password);
		if ($auth_user) {
				// 安全：重新產生 session id，並設定最後活動時間以支援閒置自動登出
				session_regenerate_id(true);
				$_SESSION['user'] = $auth_user['username'];
				$_SESSION['last_activity'] = time();
				// 固定啟用「記住功能」：設定記住 cookie 並延長 session cookie 的到期時間（1 小時）
				$ttl = 3600; // 1 小時
				setcookie('remember_active', '1', time() + $ttl, '/');
				setcookie(session_name(), session_id(), time() + $ttl, '/', '', false, true);
			header('Location: /group_41/index.php');
			exit();
		}
		$error = '帳號或密碼錯誤';
	}
}

// 輸出整合後的登入頁面（不再依賴已刪除的靜態檔案）
?>
<!doctype html>
<html lang="zh-Hant">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width,initial-scale=1" />
		<title>登入 | 社團表單系統</title>
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
				<h2>登入帳號</h2>
				<p class="muted">使用帳號登入系統。</p>

				<?php if (!empty($error)): ?>
					<div class="error"><?php echo htmlspecialchars($error); ?></div>
				<?php endif; ?>

				<form id="loginForm" method="post" action="/group_41/login.php">
					<div class="field">
						<label for="username">帳號</label>
						<input id="username" name="username" required placeholder="請輸入帳號" value="<?php echo isset($_POST['username'])?htmlspecialchars($_POST['username']):''; ?>" />
					</div>
					<div class="field">
						<label for="password">密碼</label>
						<input id="password" name="password" type="password" required placeholder="請輸入密碼" />
					</div>
					<button class="btn btn-primary" style="width:100%" type="submit">登入</button>
				</form>

				<p class="muted" style="margin-top:12px">沒有帳號？<a href="/group_41/register.php">前往註冊</a></p>
			</section>
		</main>

		<script src="/group_41/js/app.js"></script>
	</body>
</html>

<?php
exit();

