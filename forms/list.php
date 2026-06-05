<?php
session_start();

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/csrf.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$user = !empty($user_raw) ? htmlspecialchars($user_raw) : null;
$current_user = null;
$is_admin = false;
$managed_clubs = [];
$errors = [];
$forms = [];

$type_labels = [
	'public' => '公開表單',
	'club_only' => '限定社團'
];
$status_labels = [
	'draft' => '草稿',
	'published' => '已發布',
	'closed' => '已關閉'
];

try {
	$pdo = get_db();
	if ($user_raw) {
		$user_stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
		$user_stmt->execute([':u' => $user_raw]);
		$current_user = $user_stmt->fetch();
		if ($current_user && $current_user['role'] === 'admin') {
			$is_admin = true;
		} elseif ($current_user) {
			$mem_stmt = $pdo->prepare('SELECT club_id, role FROM club_memberships WHERE user_id = :id');
			$mem_stmt->execute([':id' => $current_user['id']]);
			foreach ($mem_stmt->fetchAll() as $row) {
				$club_id = (int) $row['club_id'];
				if (in_array($row['role'], ['owner', 'club_officer'], true)) {
					$managed_clubs[] = $club_id;
				}
			}
			$managed_clubs = array_values(array_unique($managed_clubs));
		}
	}

	if ($is_admin) {
		$stmt = $pdo->query('SELECT f.id, f.title, f.description, f.form_type, f.status, f.created_at, u.username, c.name AS club_name, f.creator_id, f.club_id FROM forms f JOIN users u ON u.id = f.creator_id LEFT JOIN clubs c ON c.id = f.club_id ORDER BY f.created_at DESC');
		$forms = $stmt->fetchAll();
	} else {
		$conditions = ["(f.form_type = 'public' AND f.status = 'published')"];
		$params = [];
		if ($current_user) {
			$conditions[] = 'f.creator_id = ?';
			$params[] = (int) $current_user['id'];
			if (!empty($managed_clubs)) {
				$placeholders = implode(',', array_fill(0, count($managed_clubs), '?'));
				$conditions[] = 'f.club_id IN (' . $placeholders . ')';
				foreach ($managed_clubs as $club_id) {
					$params[] = (int) $club_id;
				}
			}
		}

		$sql = 'SELECT f.id, f.title, f.description, f.form_type, f.status, f.created_at, u.username, c.name AS club_name, f.creator_id, f.club_id FROM forms f JOIN users u ON u.id = f.creator_id LEFT JOIN clubs c ON c.id = f.club_id WHERE ' . implode(' OR ', $conditions) . ' ORDER BY f.created_at DESC';
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		$forms = $stmt->fetchAll();
	}
} catch (Throwable $e) {
	$errors[] = '表單列表載入失敗，請稍後再試。';
}
?>
<!doctype html>
<html lang="zh-Hant">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>表單列表 | 社團表單系統</title>
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
				<h1>表單列表</h1>
				<p class="muted">瀏覽可查看、可填寫與可管理的表單。</p>

				<?php if (!empty($errors)) : ?>
					<div class="error">
						<ul>
							<?php foreach ($errors as $error) : ?>
								<li><?php echo htmlspecialchars($error); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php elseif (empty($forms)) : ?>
					<div class="panel" style="padding: 20px">
						<p class="muted">目前沒有可顯示的表單。</p>
						<a class="btn btn-primary" href="./create.php">建立第一份表單</a>
					</div>
				<?php else : ?>
					<div class="card-grid">
						<?php foreach ($forms as $form) : ?>
							<?php
								$type_label = isset($type_labels[$form['form_type']]) ? $type_labels[$form['form_type']] : $form['form_type'];
								$status_label = isset($status_labels[$form['status']]) ? $status_labels[$form['status']] : $form['status'];
								$created_at = !empty($form['created_at']) ? date('Y-m-d', strtotime($form['created_at'])) : '';
								$can_manage = false;
								if ($current_user) {
									if ($is_admin || (int) $form['creator_id'] === (int) $current_user['id'] || ($form['club_id'] && in_array((int) $form['club_id'], $managed_clubs, true))) {
										$can_manage = true;
									}
								}
							?>
							<article class="panel form-preview" style="padding: 20px">
								<span class="pill"><?php echo htmlspecialchars($type_label); ?></span>
								<h3><?php echo htmlspecialchars($form['title']); ?></h3>
								<p class="muted"><?php echo htmlspecialchars($form['description'] ?: '尚未提供表單說明。'); ?></p>
								<p class="meta">社團：<?php echo htmlspecialchars($form['club_name'] ?? '系統全域'); ?> ・ 建立者：<?php echo htmlspecialchars($form['username']); ?> ・ 狀態：<?php echo htmlspecialchars($status_label); ?> ・ 建立日：<?php echo htmlspecialchars($created_at); ?></p>
								<div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px">
									<a class="btn btn-primary" href="./view.php?id=<?php echo (int) $form['id']; ?>">查看表單</a>
									<?php if ($form['status'] === 'published') : ?>
										<a class="btn btn-ghost" href="./submit.php?id=<?php echo (int) $form['id']; ?>">前往填寫</a>
									<?php endif; ?>
									<?php if ($can_manage) : ?>
										<a class="btn btn-ghost" href="./edit.php?id=<?php echo (int) $form['id']; ?>">修改表單</a>
										<a class="btn btn-ghost" href="./statistics.php?id=<?php echo (int) $form['id']; ?>">填寫紀錄</a>
										<form method="post" action="./delete.php" style="display:inline">
											<?php echo csrf_field(); ?>
											<input type="hidden" name="id" value="<?php echo (int) $form['id']; ?>" />
											<input type="hidden" name="redirect" value="list.php" />
											<button type="submit" class="btn btn-ghost" data-confirm="確定要刪除此表單嗎？此操作無法復原。">刪除</button>
										</form>
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