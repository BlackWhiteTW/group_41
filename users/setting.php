<?php
require_once __DIR__ . '/../includes/cookies.php';

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/csrf.php';

$current_user = null;
$errors = [];
$success_message = null;
$form = [
	'username' => '',
	'email' => '',
	'current_password' => '',
	'new_password' => '',
	'confirm_password' => ''
];

if (!$user_raw) {
	header('Location: ../login.php');
	exit();
}
try {
	$pdo = get_db();
	$user_stmt = $pdo->prepare('SELECT id, username, email, role, created_at FROM users WHERE username = :u LIMIT 1');
	$user_stmt->execute([':u' => $user_raw]);
	$current_user = $user_stmt->fetch();
	if (!$current_user) {
		$_SESSION['flash_error'] = '找不到目前登入的帳號資料，請重新登入。';
		header('Location: ../login.php');
		exit();
	}
	$form['username'] = $current_user['username'];
	$form['email'] = $current_user['email'];

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$form['username'] = isset($_POST['username']) ? trim($_POST['username']) : '';
		$form['email'] = isset($_POST['email']) ? trim($_POST['email']) : '';
		$form['current_password'] = isset($_POST['current_password']) ? $_POST['current_password'] : '';
		$form['new_password'] = isset($_POST['new_password']) ? $_POST['new_password'] : '';
		$form['confirm_password'] = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

		if (!csrf_verify($_POST['csrf_token'] ?? '')) {
			$errors[] = '表單驗證失敗，請重新整理後再試。';
		}

		if ($form['username'] === '' || mb_strlen($form['username']) < 3) {
			$errors[] = '帳號名稱至少需要 3 個字元。';
		}
		if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
			$errors[] = '請輸入有效的電子郵件。';
		}
		if ($form['current_password'] === '') {
			$errors[] = '請輸入目前密碼以確認修改。';
		}
		if ($form['new_password'] !== '' || $form['confirm_password'] !== '') {
			if (strlen($form['new_password']) < 6) {
				$errors[] = '新密碼至少需要 6 個字元。';
			}
			if ($form['new_password'] !== $form['confirm_password']) {
				$errors[] = '新密碼與確認密碼不相符。';
			}
		}

		$auth_stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
		$auth_stmt->execute([':id' => $current_user['id']]);
		$stored_password = $auth_stmt->fetchColumn();
		if (!$stored_password || !password_verify($form['current_password'], $stored_password)) {
			$errors[] = '目前密碼錯誤。';
		}

		if ($form['username'] !== $current_user['username']) {
			$check_username = $pdo->prepare('SELECT id FROM users WHERE username = :u AND id <> :id LIMIT 1');
			$check_username->execute([':u' => $form['username'], ':id' => $current_user['id']]);
			if ($check_username->fetch()) {
				$errors[] = '此帳號名稱已被使用。';
			}
		}

		if ($form['email'] !== $current_user['email']) {
			$check_email = $pdo->prepare('SELECT id FROM users WHERE email = :e AND id <> :id LIMIT 1');
			$check_email->execute([':e' => $form['email'], ':id' => $current_user['id']]);
			if ($check_email->fetch()) {
				$errors[] = '此電子郵件已被使用。';
			}
		}

		if (empty($errors)) {
			try {
				$pdo->beginTransaction();
				$updates = [
					'username' => $form['username'],
					'email' => $form['email']
				];
				$sql = 'UPDATE users SET username = :username, email = :email';
				if ($form['new_password'] !== '') {
					$sql .= ', password = :password';
					$updates['password'] = password_hash($form['new_password'], PASSWORD_BCRYPT);
				}
				$sql .= ' WHERE id = :id';
				$updates['id'] = $current_user['id'];
				$update_stmt = $pdo->prepare($sql);
				$update_stmt->execute($updates);
				$pdo->commit();

				update_user_session($form['username']);
				$current_user['username'] = $form['username'];
				$current_user['email'] = $form['email'];
				$user = htmlspecialchars($form['username']);
				$success_message = '個人資料已更新。';
			} catch (Throwable $e) {
				if ($pdo->inTransaction()) {
					$pdo->rollBack();
				}
				$errors[] = '更新失敗，請稍後再試。';
			}
		}
	}
} catch (Throwable $e) {
	$errors[] = '設定頁載入失敗，請稍後再試。';
}
?>
<!doctype html>
<html lang="zh-Hant">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>個人設定 | 社團表單系統</title>
		<link rel="preconnect" href="https://fonts.googleapis.com" />
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
		<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;600;700&display=swap" rel="stylesheet" />
		<link rel="stylesheet" href="../css/app.css" />
	</head>
	<body>
		<?php $base_url = '../'; require __DIR__ . '/../includes/header.php'; ?>

		<?php require __DIR__ . '/../includes/right.php'; ?>

		<main class="section">
			<div class="container">
				<h1>個人設定</h1>
				<p class="muted">修改你的帳號名稱、綁定信箱與登入密碼。</p>

				<?php if (!empty($errors)) : ?>
					<div class="error">
						<ul>
							<?php foreach ($errors as $error) : ?>
								<li><?php echo htmlspecialchars($error); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ($success_message) : ?>
					<div class="panel" style="padding: 16px; margin-bottom: 16px; background: #eef7f3">
						<strong><?php echo htmlspecialchars($success_message); ?></strong>
					</div>
				<?php endif; ?>

				<div class="panel" style="padding: 20px; max-width: 760px">
					<div style="display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; align-items: flex-start">
						<div>
							<h2 style="margin-top: 0">帳號資料</h2>
							<p class="muted">目前登入：<?php echo htmlspecialchars($current_user['username']); ?></p>
						</div>
						<p class="meta">角色：<?php echo htmlspecialchars($current_user['role']); ?> ・ 註冊日：<?php echo !empty($current_user['created_at']) ? htmlspecialchars(date('Y-m-d', strtotime($current_user['created_at']))) : ''; ?></p>
					</div>

					<form method="post" action="./setting.php" style="margin-top: 16px">
						<?php echo csrf_field(); ?>

						<div class="field">
							<label for="username">帳號名稱</label>
							<input id="username" name="username" required minlength="3" value="<?php echo htmlspecialchars($form['username']); ?>" />
						</div>

						<div class="field">
							<label for="email">綁定信箱</label>
							<input id="email" name="email" type="email" required value="<?php echo htmlspecialchars($form['email']); ?>" />
						</div>

						<div class="field">
							<label for="current_password">目前密碼</label>
							<input id="current_password" name="current_password" type="password" required placeholder="請輸入目前密碼以確認修改" />
						</div>

						<div class="field">
							<label for="new_password">新密碼</label>
							<input id="new_password" name="new_password" type="password" minlength="6" placeholder="若不修改密碼可留空" />
						</div>

						<div class="field">
							<label for="confirm_password">確認新密碼</label>
							<input id="confirm_password" name="confirm_password" type="password" minlength="6" placeholder="再次輸入新密碼" />
						</div>

						<div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 8px">
							<button class="btn btn-primary" type="submit">儲存修改</button>
							<a class="btn btn-ghost" href="./user_index.php">取消</a>
						</div>
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