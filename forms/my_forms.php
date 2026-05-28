<?php
session_start();

require '../includes/db.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$user = !empty($user_raw) ? htmlspecialchars($user_raw) : null;
$current_user = null;
$is_admin = false;
$managed_clubs = [];
$forms = [];
$errors = [];

$type_labels = [
	'public' => '公開表單',
	'club_only' => '限定社團'
];
$status_labels = [
	'draft' => '草稿',
	'published' => '已發布',
	'closed' => '已關閉'
];

if (empty($user_raw)) {
	header('Location: ../login.php');
	exit();
}

try {
	$pdo = get_db();
	$u = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
	$u->execute([':u' => $user_raw]);
	$current_user = $u->fetch();
	if (!$current_user) {
		$errors[] = '找不到登入帳號資料。';
	} else {
		$is_admin = ($current_user['role'] === 'admin');
		if ($is_admin) {
			$stmt = $pdo->query('SELECT f.id, f.title, f.description, f.form_type, f.status, f.created_at, u.username, f.club_id FROM forms f JOIN users u ON u.id = f.creator_id ORDER BY f.created_at DESC');
			$forms = $stmt->fetchAll();
		} else {
			$mem_stmt = $pdo->prepare('SELECT club_id, role FROM club_memberships WHERE user_id = :id');
			$mem_stmt->execute([':id' => $current_user['id']]);
			foreach ($mem_stmt->fetchAll() as $row) {
				if (in_array($row['role'], ['owner', 'club_officer'], true)) {
					$managed_clubs[] = (int) $row['club_id'];
				}
			}
			$managed_clubs = array_values(array_unique($managed_clubs));
			if (!empty($managed_clubs)) {
				$placeholders = implode(',', array_fill(0, count($managed_clubs), '?'));
				$stmt = $pdo->prepare('SELECT f.id, f.title, f.description, f.form_type, f.status, f.created_at, u.username, f.club_id FROM forms f JOIN users u ON u.id = f.creator_id WHERE f.club_id IN (' . $placeholders . ') ORDER BY f.created_at DESC');
				$stmt->execute($managed_clubs);
				$forms = $stmt->fetchAll();
			}
		}
	}
} catch (Throwable $e) {
	$errors[] = '表單資料載入失敗，請稍後再試。';
}
?>
<!doctype html>
<html lang="zh-Hant">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>我的表單 | 社團表單系統</title>
		<link rel="preconnect" href="https://fonts.googleapis.com" />
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
		<link
			href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;600;700&display=swap"
			rel="stylesheet"
		/>
		<link rel="stylesheet" href="../css/app.css" />
	</head>
	<body>
		<?php $base_url = '../'; require '../includes/header.php'; ?>

		<main class="section">
			<div class="container">
				<h1>我的表單</h1>
				<p class="muted">查看你建立的表單與目前狀態。</p>
				<?php if (!empty($errors)) : ?>
					<div class="error">
						<ul>
							<?php foreach ($errors as $e) : ?>
								<li><?php echo htmlspecialchars($e); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php elseif (empty($forms)) : ?>
					<div class="panel" style="padding: 20px">
						<p class="muted">目前尚無表單。</p>
						<a class="btn btn-primary" href="./create.php">建立新表單</a>
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
									if ($is_admin) {
										$can_edit = true;
									} elseif (in_array((int) $form['club_id'], $managed_clubs, true)) {
										$can_edit = true;
									}
								}
							?>
							<article class="panel form-preview fade-up" style="animation-delay: <?php echo $index * 80; ?>ms">
								<span class="pill"><?php echo htmlspecialchars($type_label); ?></span>
								<h3><?php echo htmlspecialchars($form['title']); ?></h3>
								<p class="muted"><?php echo htmlspecialchars($form['description'] ?: '尚未提供表單說明。'); ?></p>
								<p class="meta">
									狀態：<?php echo htmlspecialchars($status_label); ?> ・ 建立日：<?php echo htmlspecialchars($created_at); ?>
								</p>
								<div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap">
									<a class="btn btn-primary" href="./view.php?id=<?php echo (int) $form['id']; ?>">查看表單</a>
									<?php if ($can_edit) : ?>
										<a class="btn btn-ghost" href="./edit.php?id=<?php echo (int) $form['id']; ?>">修改表單</a>
										<a class="btn btn-ghost" href="./statistics.php?id=<?php echo (int) $form['id']; ?>">填寫紀錄</a>
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
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
