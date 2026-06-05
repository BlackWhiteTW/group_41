<?php
require_once __DIR__ . '/../includes/cookies.php';

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/csrf.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$user = !empty($user_raw) ? htmlspecialchars($user_raw) : null;
$current_user = null;
$errors = [];
$success = '';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$forms_list = [];

$type_labels = ['public' => '公開表單', 'club_only' => '限定社團'];
$status_labels = ['draft' => '草稿', 'published' => '已發布', 'closed' => '已關閉'];

if (empty($user_raw)) {
	header('Location: ../login.php');
	exit();
}

try {
	$pdo = get_db();
	$current_stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
	$current_stmt->execute([':u' => $user_raw]);
	$current_user = $current_stmt->fetch();
	if (!$current_user || $current_user['role'] !== 'admin') {
		$_SESSION['flash_error'] = '需要管理員權限才能進入表單管理。';
		header('Location: ../index.php');
		exit();
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$posted_action = isset($_POST['action']) ? $_POST['action'] : '';

		if (!csrf_verify($_POST['csrf_token'] ?? '')) {
			$errors[] = '表單驗證失敗，請重新整理後再試。';
		}

		if (empty($errors) && $posted_action === 'delete_form') {
			$delete_id = isset($_POST['form_id']) ? (int) $_POST['form_id'] : 0;
			if ($delete_id <= 0) {
				$errors[] = '請選擇有效的表單。';
			} else {
				$check_stmt = $pdo->prepare('SELECT id, title FROM forms WHERE id = :id LIMIT 1');
				$check_stmt->execute([':id' => $delete_id]);
				$delete_form = $check_stmt->fetch();
				if (!$delete_form) {
					$errors[] = '找不到指定的表單。';
				} else {
					try {
						$files = $pdo->prepare("SELECT a.file_path FROM answers a JOIN form_questions q ON q.id = a.question_id WHERE q.form_id = :fid AND a.file_path IS NOT NULL AND a.file_path <> ''");
						$files->execute([':fid' => $delete_id]);
						foreach ($files->fetchAll() as $f) {
							$path = __DIR__ . '/../uploads/' . $f['file_path'];
							if (file_exists($path)) {
								unlink($path);
							}
						}

						$pdo->prepare('DELETE FROM forms WHERE id = :id')->execute([':id' => $delete_id]);
						$success = '表單「' . $delete_form['title'] . '」已刪除。';
					} catch (Throwable $e) {
						$errors[] = '刪除表單失敗，請稍後再試。';
					}
				}
			}
		} elseif (empty($errors) && $posted_action === 'update_status') {
			$update_id = isset($_POST['form_id']) ? (int) $_POST['form_id'] : 0;
			$new_status = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';
			if ($update_id <= 0) {
				$errors[] = '請選擇有效的表單。';
			} elseif (!in_array($new_status, ['draft', 'published', 'closed'], true)) {
				$errors[] = '請選擇有效的狀態。';
			} else {
				try {
					$upd = $pdo->prepare('UPDATE forms SET status = :s WHERE id = :id');
					$upd->execute([':s' => $new_status, ':id' => $update_id]);
					$success = '表單狀態已更新。';
				} catch (Throwable $e) {
					$errors[] = '更新表單狀態失敗。';
				}
			}
		}
	}

	$query = 'SELECT f.id, f.title, f.form_type, f.status, f.created_at, f.club_id, f.creator_id, u.username, c.name AS club_name, (SELECT COUNT(*) FROM form_submissions WHERE form_id = f.id) AS submission_count FROM forms f JOIN users u ON u.id = f.creator_id LEFT JOIN clubs c ON c.id = f.club_id';
	$params = [];
	$conditions = [];

	if ($search !== '') {
		$conditions[] = 'f.title LIKE :search';
		$params[':search'] = '%' . $search . '%';
	}
	if ($status_filter !== '' && in_array($status_filter, ['draft', 'published', 'closed'], true)) {
		$conditions[] = 'f.status = :status';
		$params[':status'] = $status_filter;
	}

	if (!empty($conditions)) {
		$query .= ' WHERE ' . implode(' AND ', $conditions);
	}
	$query .= ' ORDER BY f.created_at DESC, f.title ASC';

	$form_stmt = $pdo->prepare($query);
	foreach ($params as $key => $value) {
		$form_stmt->bindValue($key, $value, PDO::PARAM_STR);
	}
	$form_stmt->execute();
	$forms_list = $form_stmt->fetchAll();
} catch (Throwable $e) {
	$errors[] = '表單管理頁載入失敗，請稍後再試。';
}
?>
<!doctype html>
<html lang="zh-Hant">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>表單管理 | 社團表單系統</title>
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
				<h1>表單管理</h1>
				<p class="muted">管理所有系統表單，支援刪除、變更狀態與快速跳轉編輯。</p>

				<div style="display: flex; gap: 10px; flex-wrap: wrap; margin: 16px 0 20px">
					<a class="btn btn-ghost" href="./admin_index.php">回管理控制台</a>
					<a class="btn btn-ghost" href="./user_CRUD.php">使用者管理</a>
				</div>

				<?php if (!empty($errors)) : ?>
					<div class="error">
						<ul>
							<?php foreach ($errors as $e) : ?>
								<li><?php echo htmlspecialchars($e); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ($success !== '') : ?>
					<div class="panel" style="padding: 12px; margin-bottom: 16px; background: #e4f4eb; border-color: #8bc9b4; color: #085944;">
						<?php echo htmlspecialchars($success); ?>
					</div>
				<?php endif; ?>

				<div class="panel" style="padding: 16px; margin-bottom: 20px">
					<form method="get" action="./forms_CRUD.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: end">
						<div class="field" style="min-width: 220px">
							<label for="q">搜尋標題</label>
							<input id="q" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="表單標題關鍵字" />
						</div>
						<div class="field" style="min-width: 160px">
							<label for="status">狀態篩選</label>
							<select id="status" name="status">
								<option value="">全部狀態</option>
								<option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>草稿</option>
								<option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>已發布</option>
								<option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>已關閉</option>
							</select>
						</div>
						<div>
							<button class="btn btn-primary" type="submit">篩選</button>
							<a class="btn btn-ghost" href="./forms_CRUD.php">清除</a>
						</div>
					</form>
				</div>

				<?php if (empty($forms_list)) : ?>
					<div class="panel" style="padding: 20px">
						<p class="muted">沒有符合條件的表單。</p>
					</div>
				<?php else : ?>
					<div style="display: grid; gap: 16px">
						<?php foreach ($forms_list as $form) : ?>
							<?php
								$type_label = $type_labels[$form['form_type']] ?? $form['form_type'];
								$status_label = $status_labels[$form['status']] ?? $form['status'];
								$created_at = !empty($form['created_at']) ? date('Y-m-d', strtotime($form['created_at'])) : '';
								$club_name = $form['club_name'] ?? '系統全域';
							?>
							<article class="panel" style="padding: 16px; display: flex; gap: 16px; flex-wrap: wrap; align-items: center; justify-content: space-between">
								<div style="min-width: 240px; flex: 1">
									<strong><?php echo htmlspecialchars($form['title']); ?></strong>
									<p class="muted" style="margin-top: 4px">
										ID：<?php echo (int) $form['id']; ?> ・
										建立者：<?php echo htmlspecialchars($form['username']); ?> ・
										社團：<?php echo htmlspecialchars($club_name); ?> ・
										<span class="pill"><?php echo htmlspecialchars($type_label); ?></span>
										<span class="pill"><?php echo htmlspecialchars($status_label); ?></span>
										・ <?php echo htmlspecialchars($created_at); ?> ・
										填答數：<?php echo number_format((int) $form['submission_count']); ?>
									</p>
								</div>
								<div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center">
									<a class="btn btn-primary" href="../forms/edit.php?id=<?php echo (int) $form['id']; ?>">編輯</a>
									<a class="btn btn-ghost" href="../forms/view.php?id=<?php echo (int) $form['id']; ?>">查看</a>
									<a class="btn btn-ghost" href="../forms/statistics.php?id=<?php echo (int) $form['id']; ?>">統計</a>
									<form method="post" action="./forms_CRUD.php<?php echo ($search !== '' ? '?q=' . urlencode($search) : '') . ($status_filter !== '' ? ($search !== '' ? '&' : '?') . 'status=' . urlencode($status_filter) : ''); ?>" style="display: inline">
										<?php echo csrf_field(); ?>
										<input type="hidden" name="form_id" value="<?php echo (int) $form['id']; ?>" />
										<input type="hidden" name="action" value="delete_form" />
										<button class="btn btn-ghost" type="submit" onclick="return confirm('確定要刪除表單「<?php echo htmlspecialchars($form['title'], ENT_QUOTES); ?>」嗎？此動作無法復原。');" style="color: #b33">刪除</button>
									</form>
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
