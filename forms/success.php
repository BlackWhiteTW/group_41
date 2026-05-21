<?php
session_start();

$user = !empty($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : null;
$submission_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
?>
<!doctype html>
<html lang="zh-Hant">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>送出成功 | 社團表單系統</title>
		<link rel="preconnect" href="https://fonts.googleapis.com" />
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
		<link
			href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;600;700&display=swap"
			rel="stylesheet"
		/>
		<link rel="stylesheet" href="/group_41/css/app.css" />
	</head>
	<body>
		<?php require __DIR__ . '/../includes/header.php'; ?>

		<main class="section">
			<div class="container">
				<h1>送出成功</h1>
				<div class="panel" style="padding: 20px">
					<p class="muted">你的表單已成功送出。</p>
					<?php if ($submission_id) : ?>
						<p class="muted">送出編號：<?php echo $submission_id; ?></p>
					<?php endif; ?>
					<div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap">
						<a class="btn btn-primary" href="/group_41/forms/list.php">返回表單列表</a>
						<a class="btn btn-ghost" href="/group_41/index.php">回首頁</a>
					</div>
				</div>
			</div>
		</main>

		<footer class="footer container">社團表單系統</footer>
		<script src="/group_41/js/app.js"></script>
	</body>
</html>

<?php
exit();
