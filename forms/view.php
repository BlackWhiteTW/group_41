<?php
session_start();

require __DIR__ . '/../includes/db.php';

$user = !empty($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : null;
$current_user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$current_user = null;
$form_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$form = null;
$questions = [];
$options_map = [];
$load_error = null;

if ($form_id > 0) {
	try {
		$pdo = get_db();
		if ($current_user_raw) {
			$u = $pdo->prepare('SELECT id, username, role, club_category FROM users WHERE username = :u LIMIT 1');
			$u->execute([':u' => $current_user_raw]);
			$current_user = $u->fetch();
		}
		$stmt = $pdo->prepare('SELECT f.*, u.username, u.club_category AS creator_club FROM forms f JOIN users u ON u.id = f.creator_id WHERE f.id = :id LIMIT 1');
		$stmt->execute([':id' => $form_id]);
		$form = $stmt->fetch();

		if ($form) {
			$q_stmt = $pdo->prepare('SELECT * FROM form_questions WHERE form_id = :id ORDER BY question_order ASC');
			$q_stmt->execute([':id' => $form_id]);
			$questions = $q_stmt->fetchAll();

			if (!empty($questions)) {
				$question_ids = array_column($questions, 'id');
				$placeholders = implode(',', array_fill(0, count($question_ids), '?'));
				$o_stmt = $pdo->prepare('SELECT * FROM question_options WHERE question_id IN (' . $placeholders . ') ORDER BY option_order ASC');
				$o_stmt->execute($question_ids);
				$options = $o_stmt->fetchAll();
				foreach ($options as $opt) {
					$options_map[$opt['question_id']][] = $opt;
				}
			}
		}
	} catch (Throwable $e) {
		$load_error = '表單資料載入失敗，請稍後再試。';
	}
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
		<title>表單檢視 | 社團表單系統</title>
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
				<h1>表單檢視</h1>
				<?php if ($load_error) : ?>
					<div class="error"><?php echo htmlspecialchars($load_error); ?></div>
				<?php elseif (!$form) : ?>
					<div class="panel" style="padding: 20px">
						<p class="muted">找不到指定的表單。</p>
						<a class="btn btn-ghost" href="/group_41/forms/list.php">返回列表</a>
					</div>
				<?php else : ?>
					<?php
						$type_label = isset($type_labels[$form['form_type']]) ? $type_labels[$form['form_type']] : $form['form_type'];
						$status_label = isset($status_labels[$form['status']]) ? $status_labels[$form['status']] : $form['status'];
						$created_at = !empty($form['created_at']) ? date('Y-m-d', strtotime($form['created_at'])) : '';
						$can_edit = false;
						if ($current_user) {
							if ($current_user['role'] === 'admin') {
								$can_edit = true;
							} elseif ($current_user['role'] === 'club_officer' && $form['creator_club'] === $current_user['club_category']) {
								$can_edit = true;
							}
						}
					?>
					<div class="panel" style="padding: 20px">
						<span class="pill"><?php echo htmlspecialchars($type_label); ?></span>
						<h2><?php echo htmlspecialchars($form['title']); ?></h2>
						<p class="muted"><?php echo htmlspecialchars($form['description'] ?: '尚未提供表單說明。'); ?></p>
						<p class="meta">
							出題者：<?php echo htmlspecialchars($form['username']); ?> ・ 狀態：<?php echo htmlspecialchars($status_label); ?> ・ 建立日：<?php echo htmlspecialchars($created_at); ?>
						</p>
						<div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap">
							<?php if ($form['status'] === 'published') : ?>
								<a class="btn btn-primary" href="/group_41/forms/submit.php?id=<?php echo $form_id; ?>">前往填寫</a>
							<?php else : ?>
								<span class="muted">此表單尚未發布</span>
							<?php endif; ?>
							<?php if ($can_edit) : ?>
								<a class="btn btn-ghost" href="/group_41/forms/edit.php?id=<?php echo $form_id; ?>">修改表單</a>
								<a class="btn btn-ghost" href="/group_41/forms/statistics.php?id=<?php echo $form_id; ?>">填寫紀錄</a>
							<?php endif; ?>
							<a class="btn btn-ghost" href="/group_41/forms/list.php">返回列表</a>
						</div>
					</div>

					<div class="panel" style="padding: 20px; margin-top: 20px">
						<h3>題目列表</h3>
						<?php if (empty($questions)) : ?>
							<p class="muted">尚未設定任何題目。</p>
						<?php else : ?>
							<?php foreach ($questions as $q) : ?>
								<div style="padding: 12px 0; border-bottom: 1px solid #e4efe8">
									<strong><?php echo htmlspecialchars($q['question_text']); ?></strong>
									<p class="muted">題型：<?php echo htmlspecialchars($q['question_type']); ?> ・ <?php echo $q['is_required'] ? '必填' : '選填'; ?></p>
									<?php if (!empty($options_map[$q['id']])) : ?>
										<ul>
											<?php foreach ($options_map[$q['id']] as $opt) : ?>
												<li><?php echo htmlspecialchars($opt['option_text']); ?></li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
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
