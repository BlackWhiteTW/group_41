<?php
session_start();

require __DIR__ . '/../includes/db.php';

$user = !empty($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : null;
$current_user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$current_user = null;
$is_admin = false;
$member_clubs = [];
$managed_clubs = [];
$club_map = [];
$forms = [];
$load_error = null;

try {
	$pdo = get_db();
	$club_rows = $pdo->query('SELECT id, name FROM clubs ORDER BY name ASC')->fetchAll();
	foreach ($club_rows as $club_row) {
		$club_map[(int) $club_row['id']] = $club_row['name'];
	}
	if ($current_user_raw) {
		$u = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
		$u->execute([':u' => $current_user_raw]);
		$current_user = $u->fetch();
		if ($current_user && $current_user['role'] === 'admin') {
			$is_admin = true;
		} elseif ($current_user) {
			$mem_stmt = $pdo->prepare('SELECT club_id, role FROM club_memberships WHERE user_id = :id');
			$mem_stmt->execute([':id' => $current_user['id']]);
			foreach ($mem_stmt->fetchAll() as $row) {
				$club_id = (int) $row['club_id'];
				$member_clubs[] = $club_id;
				if (in_array($row['role'], ['owner', 'club_officer'], true)) {
					$managed_clubs[] = $club_id;
				}
			}
			$member_clubs = array_values(array_unique($member_clubs));
			$managed_clubs = array_values(array_unique($managed_clubs));
		}
	}
	$stmt = $pdo->query('SELECT f.id, f.title, f.description, f.form_type, f.status, f.created_at, f.club_id, f.target_club_ids, u.username, c.name AS club_name FROM forms f JOIN users u ON u.id = f.creator_id JOIN clubs c ON c.id = f.club_id ORDER BY f.created_at DESC');
	$forms = $stmt->fetchAll();
} catch (Throwable $e) {
	$load_error = '表單資料載入失敗，請稍後再試。';
}

if (empty($load_error) && !$is_admin) {
	$forms = array_values(array_filter($forms, function ($form) use ($current_user, $member_clubs) {
		if ($form['form_type'] === 'public') {
			return true;
		}
		if ($form['form_type'] === 'club_only') {
			if (!$current_user) {
				return false;
			}
			$target_ids = parse_target_clubs($form['target_club_ids']);
			return !empty(array_intersect($target_ids, $member_clubs));
		}
		return false;
	}));
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

function parse_target_clubs($value)
{
	if (!is_string($value) || trim($value) === '') {
		return [];
	}
	$items = array_map('trim', explode(',', $value));
	$items = array_values(array_filter($items, 'strlen'));
	return array_values(array_unique(array_map('intval', $items)));
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
								$can_submit = ($form['status'] === 'published');
								$club_notice = '';
								if ($is_admin) {
									$can_edit = true;
								} elseif ($current_user && in_array((int) $form['club_id'], $managed_clubs, true)) {
									$can_edit = true;
								}
								if ($form['form_type'] === 'club_only') {
									$target_ids = parse_target_clubs($form['target_club_ids']);
									if (!$current_user) {
										$can_submit = false;
										$club_notice = '限定社團，請先登入';
									} elseif (!$is_admin && empty(array_intersect($target_ids, $member_clubs))) {
										$can_submit = false;
										$target_names = [];
										foreach ($target_ids as $cid) {
											if (isset($club_map[$cid])) {
												$target_names[] = $club_map[$cid];
											}
										}
										$club_notice = '限定社團：' . ($target_names ? implode('、', $target_names) : '指定社團');
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
										<?php if ($can_submit) : ?>
											<a class="btn btn-ghost" href="/group_41/forms/submit.php?id=<?php echo (int) $form['id']; ?>">前往填寫</a>
										<?php else : ?>
											<span class="muted"><?php echo htmlspecialchars($club_notice ?: '此表單目前無法填寫'); ?></span>
										<?php endif; ?>
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
