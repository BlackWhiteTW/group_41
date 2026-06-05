<?php
session_start();

require __DIR__ . '/../includes/db.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$user = !empty($user_raw) ? htmlspecialchars($user_raw) : null;
$current_user = null;
$errors = [];
$stats = [
	'created_forms' => 0,
	'submissions' => 0,
	'clubs' => 0
];

try {
	$pdo = get_db();
	if ($user_raw) {
		$user_stmt = $pdo->prepare('SELECT id, username, email, role, created_at FROM users WHERE username = :u LIMIT 1');
		$user_stmt->execute([':u' => $user_raw]);
		$current_user = $user_stmt->fetch();
		if ($current_user) {
			$form_stmt = $pdo->prepare('SELECT COUNT(*) FROM forms WHERE creator_id = :id');
			$form_stmt->execute([':id' => $current_user['id']]);
			$stats['created_forms'] = (int) $form_stmt->fetchColumn();

			$sub_stmt = $pdo->prepare('SELECT COUNT(*) FROM form_submissions WHERE user_id = :id');
			$sub_stmt->execute([':id' => $current_user['id']]);
			$stats['submissions'] = (int) $sub_stmt->fetchColumn();

			$club_stmt = $pdo->prepare('SELECT COUNT(*) FROM club_memberships WHERE user_id = :id');
			$club_stmt->execute([':id' => $current_user['id']]);
			$stats['clubs'] = (int) $club_stmt->fetchColumn();
		}
	}
} catch (Throwable $e) {
	$errors[] = '使用者中心載入失敗，請稍後再試。';
}
?>
<!doctype html>
<html lang="zh-Hant">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>個人中心 | 社團表單系統</title>
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
				<h1>個人中心</h1>
				<p class="muted">快速前往你的表單、登入狀態與常用功能。</p>

				<?php if (!empty($errors)) : ?>
					<div class="error">
						<ul>
							<?php foreach ($errors as $error) : ?>
								<li><?php echo htmlspecialchars($error); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if (!$current_user) : ?>
					<div class="panel" style="padding: 20px">
						<h2 style="margin-top: 0">尚未登入</h2>
						<p class="muted">登入後可以建立表單、查看自己的填寫紀錄與管理社團相關功能。</p>
						<div style="display: flex; gap: 10px; flex-wrap: wrap">
							<a class="btn btn-primary" href="../login.php">登入</a>
							<a class="btn btn-ghost" href="../register.php">註冊</a>
							<a class="btn btn-ghost" href="../index.php">回首頁</a>
						</div>
					</div>
				<?php else : ?>
					<div class="card-grid">
						<article class="panel" style="padding: 20px">
							<h3 style="margin-top: 0"><?php echo htmlspecialchars($current_user['username']); ?></h3>
							<p class="muted">信箱：<?php echo htmlspecialchars($current_user['email']); ?></p>
							<p class="meta">角色：<?php echo htmlspecialchars($current_user['role']); ?> ・ 加入日期：<?php echo !empty($current_user['created_at']) ? htmlspecialchars(date('Y-m-d', strtotime($current_user['created_at']))) : ''; ?></p>
							<div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap">
								<a class="btn btn-primary" href="./setting.php">修改個人資料</a>
							</div>
						</article>
						<article class="panel" style="padding: 20px">
							<h3 style="margin-top: 0">我的表單</h3>
							<p class="muted">查看自己建立與管理中的表單。</p>
							<a class="btn btn-primary" href="../forms/my_forms.php">前往我的表單</a>
						</article>
					</div>

					<div class="panel" style="padding: 20px; margin-top: 20px">
						<h2 style="margin-top: 0">個人統計</h2>
						<div style="display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr))">
							<div class="stat"><div class="muted">建立表單</div><strong><?php echo number_format($stats['created_forms']); ?></strong></div>
							<div class="stat"><div class="muted">填寫次數</div><strong><?php echo number_format($stats['submissions']); ?></strong></div>
							<div class="stat"><div class="muted">社團數</div><strong><?php echo number_format($stats['clubs']); ?></strong></div>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</main>

		<footer class="footer container">社團表單系統</footer>
		<script src="../js/app.js"></script>
	</body>
</html>

<?php
exit();