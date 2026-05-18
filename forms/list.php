<?php
session_start();

require __DIR__ . '/../includes/db.php';

$user = !empty($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : null;
$current_user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$current_user = null;
$forms = [];
$load_error = null;

try {
	$pdo = get_db();
	if ($current_user_raw) {
		$u = $pdo->prepare('SELECT id, username, role, club_category FROM users WHERE username = :u LIMIT 1');
		$u->execute([':u' => $current_user_raw]);
		$current_user = $u->fetch();
	}
	$stmt = $pdo->query('SELECT f.id, f.title, f.description, f.form_type, f.status, f.created_at, u.username, u.club_category AS creator_club FROM forms f JOIN users u ON u.id = f.creator_id ORDER BY f.created_at DESC');
	$forms = $stmt->fetchAll();
} catch (Throwable $e) {
	$load_error = '表單資料載入失敗，請稍後再試。';
}

$type_labels = [
	'public' => '公開表單',
	'club_only' => '限定社團'
];
$status_labels = [
	'draft' => '草稿',
	'published' => '已發布',
	'closed' => '已關閉'
];
?>
<!doctype html>
<html lang="zh-Hant">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>表單列表 | 社團表單系統</title>
		<link rel="preconnect" href="https://fonts.googleapis.com" />
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
		<link
			href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;600;700&display=swap"
			rel="stylesheet"
		/>
		<link rel="stylesheet" href="/group_41/css/app.css" />
	</head>
	<body>
		<header class="topbar">
			<div class="container nav">
				<a href="/group_41/index.php" class="brand">Club Form Studio</a>
				<nav class="menu">
					<a class="link-btn" href="/group_41/forms/list.php">表單列表</a>
					<a class="link-btn" href="/group_41/forms/create.php">新增表單</a>
					<?php if ($user) : ?>
						<a class="btn btn-primary" href="/group_41/logout.php">登出</a>
					<?php else : ?>
						<a class="link-btn" href="/group_41/login.php">登入</a>
						<a class="btn btn-primary" href="/group_41/register.php">註冊</a>
					<?php endif; ?>
				</nav>
			</div>
		</header>

		<main class="section">
			<div class="container">
				<h1>表單列表</h1>
				<p class="muted">瀏覽目前公開的表單。</p>
				<?php if ($user) : ?>
					<p class="muted">目前已登入：<?php echo $user; ?></p>
				<?php endif; ?>
				<?php if ($load_error) : ?>
					<div class="error"><?php echo htmlspecialchars($load_error); ?></div>
				<?php elseif (empty($forms)) : ?>
					<div class="panel" style="padding: 20px">
						<p class="muted">目前尚無表單，請先建立表單。</p>
						<a class="btn btn-primary" href="/group_41/forms/create.php">建立新表單</a>
					</div>
				<?php else : ?>
					<div class="card-grid">
						<?php foreach ($forms as $index => $form) : ?>
							<?php
								$type_label = isset($type_labels[$form['form_type']]) ? $type_labels[$form['form_type']] : '表單';
								$status_label = isset($status_labels[$form['status']]) ? $status_labels[$form['status']] : $form['status'];
								$created_at = !empty($form['created_at']) ? date('Y-m-d', strtotime($form['created_at'])) : '';
								$can_edit = false;
								if ($current_user) {
									if ($current_user['role'] === 'admin') {
										$can_edit = true;
									} elseif ($current_user['role'] === 'club_officer' && $current_user['club_category'] === $form['creator_club']) {
										$can_edit = true;
									}
								}
							?>
							<article class="panel form-preview fade-up" style="animation-delay: <?php echo $index * 80; ?>ms">
								<span class="pill"><?php echo htmlspecialchars($type_label); ?></span>
								<h3><?php echo htmlspecialchars($form['title']); ?></h3>
								<p class="muted"><?php echo htmlspecialchars($form['description'] ?: '尚未提供表單說明。'); ?></p>
								<p class="meta">
									出題者：<?php echo htmlspecialchars($form['username']); ?> ・ 狀態：<?php echo htmlspecialchars($status_label); ?> ・ 建立日：<?php echo htmlspecialchars($created_at); ?>
								</p>
								<div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap">
									<a class="btn btn-primary" href="/group_41/forms/view.php?id=<?php echo (int) $form['id']; ?>">查看表單</a>
									<?php if ($form['status'] === 'published') : ?>
										<a class="btn btn-ghost" href="/group_41/forms/submit.php?id=<?php echo (int) $form['id']; ?>">前往填寫</a>
									<?php endif; ?>
									<?php if ($can_edit) : ?>
										<a class="btn btn-ghost" href="/group_41/forms/edit.php?id=<?php echo (int) $form['id']; ?>">修改表單</a>
									<?php else : ?>
										<?php if ($form['status'] !== 'published') : ?>
											<span class="muted">尚未發布</span>
										<?php endif; ?>
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</main>

		<footer class="footer container">社團表單系統</footer>
		<script src="/group_41/js/app.js"></script>
	</body>
</html>

<?php
exit();
