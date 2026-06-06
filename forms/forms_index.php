<?php
require __DIR__ . '/../includes/cookies.php';

require __DIR__ . '/../includes/db.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$user = !empty($user_raw) ? htmlspecialchars($user_raw) : null;
$current_user = null;
$errors = [];
$stats = [
	'total_forms' => 0,
	'published_forms' => 0,
	'my_forms' => 0,
	'submissions' => 0
];
$recent_forms = [];

try {
	$pdo = get_db();
	if ($user_raw) {
		$user_stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
		$user_stmt->execute([':u' => $user_raw]);
		$current_user = $user_stmt->fetch();
	}

	$stats['total_forms'] = (int) $pdo->query('SELECT COUNT(*) FROM forms')->fetchColumn();
	$stats['published_forms'] = (int) $pdo->query("SELECT COUNT(*) FROM forms WHERE status = 'published'")->fetchColumn();
	$stats['submissions'] = (int) $pdo->query('SELECT COUNT(*) FROM form_submissions')->fetchColumn();

	if ($current_user) {
		$mine = $pdo->prepare('SELECT COUNT(*) FROM forms WHERE creator_id = :id');
		$mine->execute([':id' => $current_user['id']]);
		$stats['my_forms'] = (int) $mine->fetchColumn();
	}

	$recent_stmt = $pdo->query('SELECT f.id, f.title, f.description, f.form_type, f.status, f.created_at, u.username, c.name AS club_name FROM forms f JOIN users u ON u.id = f.creator_id LEFT JOIN clubs c ON c.id = f.club_id ORDER BY f.created_at DESC LIMIT 6');
	$recent_forms = $recent_stmt->fetchAll();
} catch (Throwable $e) {
	$errors[] = '表單導覽頁載入失敗，請稍後再試。';
}
?>
<!doctype html>
<html lang="zh-Hant">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>表單中心 | 社團表單系統</title>
		<link rel="preconnect" href="https://fonts.googleapis.com" />
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
		<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;600;700&display=swap" rel="stylesheet" />
		<link rel="stylesheet" href="../css/app.css" />
	</head>
	<body>
		<?php $base_url = '../'; require __DIR__ . '/../includes/header.php'; ?>

		<?php require __DIR__ . '/../includes/right.php'; ?>

		<main>
			<section class="hero">
				<div class="container hero-grid">
					<article class="hero-card fade-up">
						<span class="badge">表單入口</span>
						<h1>表單中心</h1>
						<p class="muted">從這裡進入建立、查詢、修改、填寫與統計等主要功能。</p>
						<div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px">
							<a class="btn btn-primary" href="./list.php">前往表單列表</a>
							<a class="btn btn-ghost" href="./create.php">建立新表單</a>
							<a class="btn btn-ghost" href="./my_forms.php">我的表單</a>
						</div>
					</article>

					<aside class="panel stats fade-up" style="animation-delay: 120ms">
						<div class="stat">
							<div class="muted">表單總數</div>
							<strong><?php echo number_format($stats['total_forms']); ?></strong>
						</div>
						<div class="stat">
							<div class="muted">已發布</div>
							<strong><?php echo number_format($stats['published_forms']); ?></strong>
						</div>
						<div class="stat">
							<div class="muted">我的表單</div>
							<strong><?php echo number_format($stats['my_forms']); ?></strong>
						</div>
						<div class="stat">
							<div class="muted">填寫紀錄</div>
							<strong><?php echo number_format($stats['submissions']); ?></strong>
						</div>
					</aside>
				</div>
			</section>

			<section class="section">
				<div class="container">
					<?php if (!empty($errors)) : ?>
						<div class="error">
							<ul>
								<?php foreach ($errors as $error) : ?>
									<li><?php echo htmlspecialchars($error); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>

					<div class="card-grid">
						<article class="panel" style="padding: 20px">
							<h3 style="margin-top: 0">建立表單</h3>
							<p class="muted">新增題目、設定公開或限定社團、發布新問卷。</p>
							<a class="btn btn-primary" href="./create.php">開始建立</a>
						</article>
						<article class="panel" style="padding: 20px">
							<h3 style="margin-top: 0">查詢與管理</h3>
							<p class="muted">查看自己建立的表單，進入修改與統計頁面。</p>
							<a class="btn btn-ghost" href="./my_forms.php">查看我的表單</a>
						</article>
						<article class="panel" style="padding: 20px">
							<h3 style="margin-top: 0">填寫表單</h3>
							<p class="muted">瀏覽可填寫的表單，直接進入填答畫面。</p>
							<a class="btn btn-ghost" href="./list.php">前往填寫列表</a>
						</article>
						<article class="panel" style="padding: 20px">
							<h3 style="margin-top: 0">統計與回收</h3>
							<p class="muted">查看題目統計、明細回覆與圖表分析。</p>
							<a class="btn btn-ghost" href="./my_forms.php">進入統計頁</a>
						</article>
					</div>

					<div class="panel" style="padding: 20px; margin-top: 20px">
						<h2 style="margin-top: 0">最近表單</h2>
						<p class="muted">快速查看最近建立的表單，並直接前往檢視或填寫。</p>
						<?php if (empty($recent_forms)) : ?>
							<p class="muted">目前尚無表單資料。</p>
						<?php else : ?>
							<div class="card-grid">
								<?php foreach ($recent_forms as $form) : ?>
									<?php
										$type_label = $form['form_type'] === 'club_only' ? '限定社團' : '公開表單';
										$created_at = !empty($form['created_at']) ? date('Y-m-d', strtotime($form['created_at'])) : '';
									?>
									<article class="panel" style="padding: 16px">
										<span class="pill"><?php echo htmlspecialchars($type_label); ?></span>
										<h3><?php echo htmlspecialchars($form['title']); ?></h3>
										<p class="muted"><?php echo htmlspecialchars($form['description'] ?: '尚未提供表單說明。'); ?></p>
										<p class="meta">社團：<?php echo htmlspecialchars($form['club_name'] ?? '系統全域'); ?> ・ 建立者：<?php echo htmlspecialchars($form['username']); ?> ・ 建立日：<?php echo htmlspecialchars($created_at); ?></p>
										<div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px">
											<a class="btn btn-primary" href="./view.php?id=<?php echo (int) $form['id']; ?>">查看表單</a>
											<?php if ($form['status'] === 'published') : ?>
												<a class="btn btn-ghost" href="./submit.php?id=<?php echo (int) $form['id']; ?>">前往填寫</a>
											<?php endif; ?>
										</div>
									</article>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</section>
		</main>

		<footer class="footer container">社團表單系統</footer>
		<script src="../js/app.js"></script>
	</body>
</html>

<?php
exit();
