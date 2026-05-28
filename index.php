<?php
// 社團系統首頁：展示公開表單、統計資訊和導航連結
session_start();

require './includes/db.php';

$user = !empty($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : null;
$stats = [
	'public_forms' => 0,
	'submissions' => 0,
	'clubs' => 0
];
$public_forms = [];
$public_error = null;
$flash_error = null;

if (!empty($_SESSION['flash_error'])) {
	$flash_error = $_SESSION['flash_error'];
	unset($_SESSION['flash_error']);
}

try {
	$pdo = get_db();
	$stats['public_forms'] = (int) $pdo->query("SELECT COUNT(*) FROM forms WHERE form_type = 'public'")->fetchColumn();
	$stats['submissions'] = (int) $pdo->query('SELECT COUNT(*) FROM form_submissions')->fetchColumn();
	$stats['clubs'] = (int) $pdo->query('SELECT COUNT(*) FROM clubs')->fetchColumn();
	$stmt = $pdo->query("SELECT f.id, f.title, f.description, f.status, f.created_at, u.username, COUNT(s.id) AS submissions FROM forms f JOIN users u ON u.id = f.creator_id LEFT JOIN form_submissions s ON s.form_id = f.id WHERE f.form_type = 'public' GROUP BY f.id ORDER BY f.created_at DESC");
	$public_forms = $stmt->fetchAll();
} catch (Throwable $e) {
	$stats = [
		'public_forms' => 0,
		'submissions' => 0,
		'clubs' => 0
	];
	$public_error = '公開表單載入失敗，請稍後再試。';
}
?>
<!doctype html>
<html lang="zh-Hant">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>社團表單系統</title>
		<link rel="preconnect" href="https://fonts.googleapis.com" />
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
		<link
			href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;600;700&display=swap"
			rel="stylesheet"
		/>
		<link rel="stylesheet" href="./css/app.css" />
	</head>
	<body>
		<?php $show_status = true; ?>
		<?php $base_url = './'; require './includes/header.php'; ?>

		<main>
			<?php if ($flash_error) : ?>
				<section class="section">
					<div class="container">
						<div class="error"><?php echo htmlspecialchars($flash_error); ?></div>
					</div>
				</section>
			<?php endif; ?>
			<section class="hero">
				<div class="container hero-grid">
					<article class="hero-card fade-up">
						<span class="badge">歡迎使用</span>
						<h1>
							校園活動問卷平台
						</h1>
						<p class="muted">
							<span>登入狀態：<?php echo $user ? '已登入：' . $user : '未登入'; ?></span>
						</p>
						<div
							style="
								display: flex;
								gap: 10px;
								flex-wrap: wrap;
								margin-top: 14px;
							"
						>
							<a class="btn btn-primary" href="./forms/list.php">查看表單列表</a>
							<a class="btn btn-ghost" href="./forms/create.php">建立新表單</a>
							<a class="btn btn-ghost" href="./clubs/manage.php">查看社團資訊</a>
						</div>
					</article>

					<aside class="panel stats fade-up" style="animation-delay: 120ms">
						<div class="stat">
							<div class="muted">公開表單</div>
							<strong><?php echo number_format($stats['public_forms']); ?></strong>
						</div>
						<div class="stat">
							<div class="muted">總填答次數</div>
							<strong><?php echo number_format($stats['submissions']); ?></strong>
						</div>
						<div class="stat">
							<div class="muted">活躍社團</div>
							<strong><?php echo number_format($stats['clubs']); ?></strong>
						</div>
					</aside>
				</div>
			</section>

			<section class="section">
				<div class="container">
					<h2>公開表單清單</h2>
					<p class="muted">以下為目前公開的表單。</p>
					<?php if ($public_error) : ?>
						<div class="error"><?php echo htmlspecialchars($public_error); ?></div>
					<?php elseif (empty($public_forms)) : ?>
						<div class="panel" style="padding: 20px">
							<p class="muted">目前尚無公開表單。</p>
							<a class="btn btn-primary" href="./forms/create.php">建立新表單</a>
						</div>
					<?php else : ?>
						<div id="public-form-list" class="card-grid">
							<?php foreach ($public_forms as $index => $form) : ?>
								<?php
									$created_at = !empty($form['created_at']) ? date('Y-m-d', strtotime($form['created_at'])) : '';
									$submissions = isset($form['submissions']) ? (int) $form['submissions'] : 0;
								?>
								<article class="panel form-preview fade-up" style="animation-delay: <?php echo $index * 80; ?>ms">
									<span class="pill">公開表單</span>
									<h3><?php echo htmlspecialchars($form['title']); ?></h3>
									<p class="muted"><?php echo htmlspecialchars($form['description'] ?: '尚未提供表單說明。'); ?></p>
									<p class="meta">
										出題者：<?php echo htmlspecialchars($form['username']); ?> ・ 填寫數：<?php echo number_format($submissions); ?> ・ 建立日：<?php echo htmlspecialchars($created_at); ?>
									</p>
									<div
										style="
											margin-top: 12px;
											display: flex;
											gap: 8px;
											flex-wrap: wrap;
										"
									>
										<a class="btn btn-primary" href="./forms/view.php?id=<?php echo (int) $form['id']; ?>">查看表單</a>
										<?php if ($form['status'] === 'published') : ?>
											<a class="btn btn-ghost" href="./forms/submit.php?id=<?php echo (int) $form['id']; ?>">前往填寫</a>
										<?php else : ?>
											<span class="muted">尚未發布</span>
										<?php endif; ?>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</section>
		</main>

		<footer class="footer container">社團表單系統</footer>
		<script src="./js/app.js"></script>
	</body>
</html>

<?php
exit();
