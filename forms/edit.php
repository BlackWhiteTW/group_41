<?php
session_start();

require '../includes/db.php';
require '../includes/csrf.php';
require '../includes/functions.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$user = !empty($user_raw) ? htmlspecialchars($user_raw) : null;
$current_user = null;
$is_admin = false;
$member_clubs = [];
$managed_clubs = [];
$club_map = [];
$errors = [];
$success = '';

$edit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$user_raw) {
	header('Location: ../login.php');
	exit;
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

		$club_rows = $pdo->query('SELECT id, name FROM clubs ORDER BY name ASC')->fetchAll();
		foreach ($club_rows as $club_row) {
			$club_map[(int) $club_row['id']] = $club_row['name'];
		}

		if (!$is_admin) {
			$mem_stmt = $pdo->prepare('SELECT club_id, role FROM club_memberships WHERE user_id = :id');
			$mem_stmt->execute([':id' => $current_user['id']]);
			foreach ($mem_stmt->fetchAll() as $row) {
				$cid = (int) $row['club_id'];
				$member_clubs[] = $cid;
				if (in_array($row['role'], ['owner', 'club_officer'], true)) {
					$managed_clubs[] = $cid;
				}
			}
			$member_clubs = array_values(array_unique($member_clubs));
			$managed_clubs = array_values(array_unique($managed_clubs));
		}
	}
} catch (Throwable $e) {
	$errors[] = '系統錯誤。';
}

// ─── 編輯特定表單 ───
$editing_form = null;
$editing_questions = [];
$editing_options = [];

if ($edit_id > 0 && empty($errors)) {
	try {
		$pdo = get_db();
		$stmt = $pdo->prepare('SELECT f.*, u.username FROM forms f JOIN users u ON u.id = f.creator_id WHERE f.id = :id LIMIT 1');
		$stmt->execute([':id' => $edit_id]);
		$editing_form = $stmt->fetch();

		if (!$editing_form) {
			$errors[] = '找不到指定的表單。';
		} else {
			$can_edit = $is_admin || (int) $editing_form['creator_id'] === (int) $current_user['id'] || ($editing_form['club_id'] && in_array((int) $editing_form['club_id'], $managed_clubs, true));
			if (!$can_edit) {
				$errors[] = '你沒有權限修改此表單。';
			} else {
				$q_stmt = $pdo->prepare('SELECT * FROM form_questions WHERE form_id = :id ORDER BY question_order ASC');
				$q_stmt->execute([':id' => $edit_id]);
				$editing_questions = $q_stmt->fetchAll();

				if (!empty($editing_questions)) {
					$qids = array_column($editing_questions, 'id');
					$ph = implode(',', array_fill(0, count($qids), '?'));
					$o_stmt = $pdo->prepare('SELECT * FROM question_options WHERE question_id IN (' . $ph . ') ORDER BY option_order ASC');
					$o_stmt->execute($qids);
					foreach ($o_stmt->fetchAll() as $opt) {
						$editing_options[$opt['question_id']][] = $opt;
					}
				}
			}
		}
	} catch (Throwable $e) {
		$errors[] = '無法載入表單資料。';
	}
}

// ─── 處理更新提交 ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $edit_id > 0 && empty($errors) && $editing_form) {
	if (!csrf_verify($_POST['csrf_token'] ?? '')) {
		$errors[] = '表單驗證失敗，請重新整理後再試。';
	}
	$title = trim($_POST['title'] ?? '');
	$description = trim($_POST['description'] ?? '');
	$form_type = $_POST['form_type'] ?? 'public';
	$status = $_POST['status'] ?? 'draft';
	$open_at_date = trim($_POST['open_at_date'] ?? '');
	$open_at_hour = $_POST['open_at_hour'] ?? '';
	$open_at_minute = $_POST['open_at_minute'] ?? '';
	$open_at = ($open_at_date !== '' && $open_at_hour !== '' && $open_at_minute !== '') ? $open_at_date . 'T' . $open_at_hour . ':' . $open_at_minute : '';

	$close_at_date = trim($_POST['close_at_date'] ?? '');
	$close_at_hour = $_POST['close_at_hour'] ?? '';
	$close_at_minute = $_POST['close_at_minute'] ?? '';
	$close_at = ($close_at_date !== '' && $close_at_hour !== '' && $close_at_minute !== '') ? $close_at_date . 'T' . $close_at_hour . ':' . $close_at_minute : '';
	$allow_resubmit = empty($_POST['allow_resubmit']) ? 0 : 1;
	$require_login = empty($_POST['require_login']) ? 0 : 1;
	$target_club_ids_raw = isset($_POST['target_club_ids']) ? $_POST['target_club_ids'] : [];
	if (!is_array($target_club_ids_raw)) {
		$target_club_ids_raw = [$target_club_ids_raw];
	}
	$trimmed = array_map('trim', $target_club_ids_raw);
	$ints = array_map('intval', $trimmed);
	$filtered = array_filter($ints, function ($v) { return $v > 0; });
	$unique = array_unique($filtered);
	$target_club_ids = implode(',', array_values($unique));
	if ($form_type !== 'club_only') {
		$target_club_ids = null;
	}

	if ($title === '') {
		$errors[] = '請輸入表單標題。';
	}

	if (empty($errors)) {
		try {
			$pdo = get_db();
			$pdo->beginTransaction();

			$db_open_at = $open_at ? str_replace('T', ' ', $open_at) . ':00' : null;
			$db_close_at = $close_at ? str_replace('T', ' ', $close_at) . ':00' : null;
			$upd = $pdo->prepare('UPDATE forms SET title = :t, description = :d, form_type = :ft, status = :s, open_at = :oa, close_at = :ca, allow_resubmit = :ar, require_login = :rl, target_club_ids = :tc WHERE id = :id');
			$upd->execute([
				':t' => $title,
				':d' => $description ?: null,
				':ft' => $form_type,
				':s' => $status,
				':oa' => $db_open_at,
				':ca' => $db_close_at,
				':ar' => $allow_resubmit,
				':rl' => $require_login,
				':tc' => $target_club_ids ?: null,
				':id' => $edit_id
			]);

			// Update or insert questions (preserve IDs to keep answers linked)
			$existing_qids = array_column($editing_questions, 'id');
			$questions_input = (isset($_POST['questions']) && is_array($_POST['questions'])) ? $_POST['questions'] : [];
			$kept_qids = [];

			$q_update = $pdo->prepare('UPDATE form_questions SET question_order = :o, question_text = :t, question_type = :qt, is_required = :r WHERE id = :id AND form_id = :f');
			$q_insert = $pdo->prepare('INSERT INTO form_questions (form_id, question_order, question_text, question_type, is_required) VALUES (:f, :o, :t, :qt, :r)');
			$o_update = $pdo->prepare('UPDATE question_options SET option_text = :t, option_order = :o WHERE id = :id');
			$o_insert = $pdo->prepare('INSERT INTO question_options (question_id, option_text, option_order) VALUES (:q, :t, :o)');

			$order = 0;
			foreach ($questions_input as $q) {
				$text = trim($q['text'] ?? '');
				if ($text === '') continue;
				$type = $q['type'] ?? 'short_answer';
				$required = !empty($q['required']);
				$order++;

				$qid = isset($q['id']) ? (int) $q['id'] : 0;

				if ($qid > 0 && in_array($qid, $existing_qids, true)) {
					$q_update->execute([':o' => $order, ':t' => $text, ':qt' => $type, ':r' => $required ? 1 : 0, ':id' => $qid, ':f' => $edit_id]);
					$question_id = $qid;
					$kept_qids[] = $qid;
				} else {
					$q_insert->execute([':f' => $edit_id, ':o' => $order, ':t' => $text, ':qt' => $type, ':r' => $required ? 1 : 0]);
					$question_id = (int) $pdo->lastInsertId();
				}

				if ($qid > 0 && !in_array($type, ['multiple_choice', 'multi_choice'], true)) {
					$pdo->prepare('DELETE FROM question_options WHERE question_id = ?')->execute([$qid]);
				}

				if (in_array($type, ['multiple_choice', 'multi_choice'], true)) {
					$options = isset($q['options']) && is_array($q['options']) ? $q['options'] : [];
					$opt_ids = isset($q['opt_ids']) && is_array($q['opt_ids']) ? $q['opt_ids'] : [];
					$kept_opt_ids = [];
					$opt_order = 0;
					foreach ($options as $idx => $opt_text) {
						$opt_text = trim($opt_text);
						if ($opt_text === '') continue;
						$opt_order++;
						$opt_id = isset($opt_ids[$idx]) ? (int) $opt_ids[$idx] : 0;

						if ($opt_id > 0) {
							$o_update->execute([':t' => $opt_text, ':o' => $opt_order, ':id' => $opt_id]);
							$kept_opt_ids[] = $opt_id;
						} else {
							$o_insert->execute([':q' => $question_id, ':t' => $opt_text, ':o' => $opt_order]);
						}
					}
					if ($qid > 0) {
						if (!empty($kept_opt_ids)) {
							$ph = implode(',', array_fill(0, count($kept_opt_ids), '?'));
							$pdo->prepare("DELETE FROM question_options WHERE question_id = ? AND id NOT IN ($ph)")->execute(array_merge([$question_id], $kept_opt_ids));
						} else {
							$pdo->prepare('DELETE FROM question_options WHERE question_id = ?')->execute([$question_id]);
						}
					}
				}
			}

			if (!empty($kept_qids)) {
				$ph = implode(',', array_fill(0, count($kept_qids), '?'));
				$pdo->prepare("DELETE FROM form_questions WHERE form_id = ? AND id NOT IN ($ph)")->execute(array_merge([$edit_id], $kept_qids));
			} else {
				$pdo->prepare('DELETE FROM form_questions WHERE form_id = ?')->execute([$edit_id]);
			}

			$pdo->commit();
			$success = '表單已更新成功。';

			// Reload
			$stmt = $pdo->prepare('SELECT f.*, u.username FROM forms f JOIN users u ON u.id = f.creator_id WHERE f.id = :id LIMIT 1');
			$stmt->execute([':id' => $edit_id]);
			$editing_form = $stmt->fetch();

			$q_stmt = $pdo->prepare('SELECT * FROM form_questions WHERE form_id = :id ORDER BY question_order ASC');
			$q_stmt->execute([':id' => $edit_id]);
			$editing_questions = $q_stmt->fetchAll();
			$editing_options = [];
			if (!empty($editing_questions)) {
				$qids = array_column($editing_questions, 'id');
				$ph = implode(',', array_fill(0, count($qids), '?'));
				$o_stmt = $pdo->prepare('SELECT * FROM question_options WHERE question_id IN (' . $ph . ') ORDER BY option_order ASC');
				$o_stmt->execute($qids);
				foreach ($o_stmt->fetchAll() as $opt) {
					$editing_options[$opt['question_id']][] = $opt;
				}
			}
		} catch (Throwable $e) {
			if (!empty($pdo) && $pdo->inTransaction()) $pdo->rollBack();
			$errors[] = '更新表單失敗，請稍後再試。';
		}
	}
}

// ─── 列表模式（無 edit_id） ───
$forms_list = [];
$list_error = null;
if ($edit_id === 0 && empty($errors)) {
	try {
		$pdo = get_db();
		if ($is_admin) {
			$stmt = $pdo->query('SELECT f.id, f.title, f.form_type, f.status, f.created_at, f.club_id, f.creator_id, u.username, c.name AS club_name FROM forms f JOIN users u ON u.id = f.creator_id LEFT JOIN clubs c ON c.id = f.club_id ORDER BY f.created_at DESC');
			$forms_list = $stmt->fetchAll();
		} else {
			$conditions = ["(f.creator_id = ?)"];
			$params = [(int) $current_user['id']];
			if (!empty($managed_clubs)) {
				$placeholders = implode(',', array_fill(0, count($managed_clubs), '?'));
				$conditions[] = 'f.club_id IN (' . $placeholders . ')';
				foreach ($managed_clubs as $cid) {
					$params[] = (int) $cid;
				}
			}
			$sql = 'SELECT f.id, f.title, f.form_type, f.status, f.created_at, f.club_id, f.creator_id, u.username, c.name AS club_name FROM forms f JOIN users u ON u.id = f.creator_id LEFT JOIN clubs c ON c.id = f.club_id WHERE ' . implode(' OR ', $conditions) . ' ORDER BY f.created_at DESC';
			$stmt = $pdo->prepare($sql);
			$stmt->execute($params);
			$forms_list = $stmt->fetchAll();
		}
	} catch (Throwable $e) {
		$list_error = '表單資料載入失敗。';
	}
}

$type_labels = ['public' => '公開表單', 'club_only' => '限定社團'];
$status_labels = ['draft' => '草稿', 'published' => '已發布', 'closed' => '已關閉'];
$allowed_types = ['short_answer', 'long_answer', 'multiple_choice', 'multi_choice', 'file_upload'];
?>
<!doctype html>
<html lang="zh-Hant">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo $edit_id ? '修改表單' : '表單列表'; ?> | 社團表單系統</title>
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
	<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;600;700&display=swap" rel="stylesheet" />
	<link rel="stylesheet" href="../css/app.css" />
	<style>
		.option-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
		.option-row input { flex: 1; }
		.question-block { padding: 16px; margin-bottom: 16px; }
		.question-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 8px; }
		.success { background: #e4f4eb; border: 1px solid #8bc9b4; color: #085944; padding: 10px 12px; border-radius: 10px; margin-bottom: 12px; }
	</style>
</head>
<body>
	<?php $base_url = '../'; require '../includes/header.php'; ?>

	<?php require __DIR__ . '/../includes/right.php'; ?>

	<main class="section">
		<div class="container">
			<?php if (!empty($errors)) : ?>
				<div class="error"><ul><?php foreach ($errors as $e) : ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div>
			<?php endif; ?>

			<?php if ($success) : ?>
				<div class="success"><?php echo htmlspecialchars($success); ?></div>
			<?php endif; ?>

			<?php if ($edit_id && $editing_form) : ?>
				<!-- ═══ 編輯模式 ═══ -->
				<h1>修改表單</h1>
				<a href="edit.php" class="muted" style="display:inline-block;margin-bottom:16px;">← 回表單列表</a>

				<form method="post" action="edit.php?id=<?php echo $edit_id; ?>" class="panel" style="padding: 20px">
					<?php echo csrf_field(); ?>
					<div class="field">
						<label for="title">表單標題</label>
						<input id="title" name="title" required value="<?php echo htmlspecialchars($editing_form['title']); ?>" />
					</div>
					<div class="field">
						<label for="description">表單說明</label>
						<textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($editing_form['description'] ?? ''); ?></textarea>
					</div>
					<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
						<div class="field">
							<label for="form_type">表單類型</label>
							<select id="form_type" name="form_type">
								<option value="public" <?php echo $editing_form['form_type'] === 'public' ? 'selected' : ''; ?>>公開表單</option>
								<option value="club_only" <?php echo $editing_form['form_type'] === 'club_only' ? 'selected' : ''; ?>>限定社團</option>
							</select>
						</div>
						<div class="field">
							<label for="status">狀態</label>
							<select id="status" name="status">
								<option value="draft" <?php echo $editing_form['status'] === 'draft' ? 'selected' : ''; ?>>草稿</option>
								<option value="published" <?php echo $editing_form['status'] === 'published' ? 'selected' : ''; ?>>發布</option>
								<option value="closed" <?php echo $editing_form['status'] === 'closed' ? 'selected' : ''; ?>>關閉</option>
							</select>
						</div>
					</div>
				<div class="field" id="targetClubWrap">
					<label for="target_club_ids">限定社團名稱</label>
					<?php if ($is_admin && !empty($club_map)) : ?>
						<select id="target_club_ids" name="target_club_ids[]" multiple size="6">
							<?php foreach ($club_map as $cid => $cname) : ?>
								<option value="<?php echo $cid; ?>" <?php echo in_array($cid, parse_target_clubs($editing_form['target_club_ids'] ?? ''), true) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cname); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="muted" style="margin-top: 6px">管理員可多選（Ctrl/Command）。</p>
					<?php else : ?>
						<p class="muted">限定社團將使用表單所屬社團。</p>
					<?php endif; ?>
				</div>
					<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px">
						<div class="field">
							<label>預定開放時間 <span class="muted">（可選）</span></label>
							<div style="display:grid;grid-template-columns:1fr auto auto;gap:8px;align-items:center">
								<?php
									$oa_date = $editing_form['open_at'] ? substr($editing_form['open_at'], 0, 10) : '';
									$oa_hour = $editing_form['open_at'] ? substr($editing_form['open_at'], 11, 2) : '';
									$oa_min  = $editing_form['open_at'] ? substr($editing_form['open_at'], 14, 2) : '';
								?>
								<input type="date" name="open_at_date" value="<?php echo htmlspecialchars($oa_date); ?>" />
								<select name="open_at_hour">
									<option value="">時</option>
									<?php for ($h = 0; $h < 24; $h++) : ?>
										<option value="<?php echo sprintf('%02d', $h); ?>" <?php echo $oa_hour === sprintf('%02d', $h) ? 'selected' : ''; ?>><?php echo sprintf('%02d', $h); ?></option>
									<?php endfor; ?>
								</select>
								<select name="open_at_minute">
									<option value="">分</option>
									<?php for ($m = 0; $m < 60; $m++) : ?>
										<option value="<?php echo sprintf('%02d', $m); ?>" <?php echo $oa_min === sprintf('%02d', $m) ? 'selected' : ''; ?>><?php echo sprintf('%02d', $m); ?></option>
									<?php endfor; ?>
								</select>
							</div>
						</div>
						<div class="field">
							<label>預定關閉時間 <span class="muted">（可選）</span></label>
							<div style="display:grid;grid-template-columns:1fr auto auto;gap:8px;align-items:center">
								<?php
									$ca_date = $editing_form['close_at'] ? substr($editing_form['close_at'], 0, 10) : '';
									$ca_hour = $editing_form['close_at'] ? substr($editing_form['close_at'], 11, 2) : '';
									$ca_min  = $editing_form['close_at'] ? substr($editing_form['close_at'], 14, 2) : '';
								?>
								<input type="date" name="close_at_date" value="<?php echo htmlspecialchars($ca_date); ?>" />
								<select name="close_at_hour">
									<option value="">時</option>
									<?php for ($h = 0; $h < 24; $h++) : ?>
										<option value="<?php echo sprintf('%02d', $h); ?>" <?php echo $ca_hour === sprintf('%02d', $h) ? 'selected' : ''; ?>><?php echo sprintf('%02d', $h); ?></option>
									<?php endfor; ?>
								</select>
								<select name="close_at_minute">
									<option value="">分</option>
									<?php for ($m = 0; $m < 60; $m++) : ?>
										<option value="<?php echo sprintf('%02d', $m); ?>" <?php echo $ca_min === sprintf('%02d', $m) ? 'selected' : ''; ?>><?php echo sprintf('%02d', $m); ?></option>
									<?php endfor; ?>
								</select>
							</div>
						</div>
					</div>
				<div class="field">
					<label class="check-row">
						<input type="checkbox" name="allow_resubmit" value="1" <?php echo ((int) ($editing_form['allow_resubmit'] ?? 1)) ? 'checked' : ''; ?> />
						允許重複填答
					</label>
					<p class="muted">取消勾選則每人僅能填寫一次（需登入才能偵測）。</p>
				</div>
				<div class="field">
					<label class="check-row">
						<input type="checkbox" name="require_login" value="1" <?php echo empty($editing_form['require_login']) ? '' : 'checked'; ?> />
						需登入才能填寫
					</label>
					<p class="muted">勾選後未登入使用者無法填寫此表單。</p>
				</div>

					<h2 style="margin-top:24px">題目設定</h2>
					<div id="questionList" data-next-index="<?php echo count($editing_questions); ?>">
						<?php foreach ($editing_questions as $qi => $q) : ?>
							<?php $show_opts = in_array($q['question_type'], ['multiple_choice', 'multi_choice'], true); ?>
							<div class="panel question-block" data-question-block="<?php echo $qi; ?>">
								<input type="hidden" name="questions[<?php echo $qi; ?>][id]" value="<?php echo (int) $q['id']; ?>" />
								<div class="field">
									<label>題目說明</label>
									<input name="questions[<?php echo $qi; ?>][text]" value="<?php echo htmlspecialchars($q['question_text']); ?>" />
								</div>
								<div class="field">
									<label>題型</label>
									<select name="questions[<?php echo $qi; ?>][type]" data-role="question-type">
										<option value="short_answer" <?php echo $q['question_type'] === 'short_answer' ? 'selected' : ''; ?>>簡答</option>
										<option value="long_answer" <?php echo $q['question_type'] === 'long_answer' ? 'selected' : ''; ?>>長答</option>
										<option value="multiple_choice" <?php echo $q['question_type'] === 'multiple_choice' ? 'selected' : ''; ?>>單選</option>
										<option value="multi_choice" <?php echo $q['question_type'] === 'multi_choice' ? 'selected' : ''; ?>>多選</option>
										<option value="file_upload" <?php echo $q['question_type'] === 'file_upload' ? 'selected' : ''; ?>>檔案上傳</option>
									</select>
								</div>
								<div class="field">
									<label class="check-row">
										<input type="checkbox" name="questions[<?php echo $qi; ?>][required]" value="1" <?php echo $q['is_required'] ? 'checked' : ''; ?> />
										必填
									</label>
								</div>
								<div class="field option-group" data-role="option-group" style="<?php echo $show_opts ? '' : 'display:none'; ?>">
									<label>選項</label>
									<div class="options">
										<?php foreach ($editing_options[$q['id']] ?? [] as $opt) : ?>
											<div class="option-row">
												<input type="hidden" name="questions[<?php echo $qi; ?>][opt_ids][]" value="<?php echo (int) $opt['id']; ?>" />
												<input name="questions[<?php echo $qi; ?>][options][]" value="<?php echo htmlspecialchars($opt['option_text']); ?>" placeholder="選項內容" />
												<button class="btn btn-ghost btn-small" type="button" data-action="remove-option">刪除</button>
											</div>
										<?php endforeach; ?>
									</div>
									<button class="btn btn-ghost btn-small" type="button" data-action="add-option" data-question="<?php echo $qi; ?>">新增選項</button>
								</div>
								<div class="question-actions">
									<button class="btn btn-ghost btn-small" type="button" data-action="move-up">上移</button>
									<button class="btn btn-ghost btn-small" type="button" data-action="move-down">下移</button>
									<button class="btn btn-ghost btn-small" type="button" data-action="remove-question">刪除題目</button>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<button class="btn btn-ghost" type="button" id="addQuestionBtn">新增題目</button>

					<template id="questionTemplate">
						<div class="panel question-block" data-question-block="__INDEX__">
							<div class="field">
								<label>題目說明</label>
								<input name="questions[__INDEX__][text]" />
							</div>
							<div class="field">
								<label>題型</label>
								<select name="questions[__INDEX__][type]" data-role="question-type">
									<option value="short_answer">簡答</option>
									<option value="long_answer">長答</option>
									<option value="multiple_choice">單選</option>
									<option value="multi_choice">多選</option>
									<option value="file_upload">檔案上傳</option>
								</select>
							</div>
							<div class="field">
								<label class="check-row">
									<input type="checkbox" name="questions[__INDEX__][required]" value="1" checked />
									必填
								</label>
							</div>
							<div class="field option-group" data-role="option-group" style="display:none">
								<label>選項</label>
								<div class="options">
									<div class="option-row">
										<input type="hidden" name="questions[__INDEX__][opt_ids][]" value="0" />
										<input name="questions[__INDEX__][options][]" placeholder="選項內容" />
										<button class="btn btn-ghost btn-small" type="button" data-action="remove-option">刪除</button>
									</div>
									<div class="option-row">
										<input type="hidden" name="questions[__INDEX__][opt_ids][]" value="0" />
										<input name="questions[__INDEX__][options][]" placeholder="選項內容" />
										<button class="btn btn-ghost btn-small" type="button" data-action="remove-option">刪除</button>
									</div>
								</div>
								<button class="btn btn-ghost btn-small" type="button" data-action="add-option" data-question="__INDEX__">新增選項</button>
							</div>
							<div class="question-actions">
								<button class="btn btn-ghost btn-small" type="button" data-action="move-up">上移</button>
								<button class="btn btn-ghost btn-small" type="button" data-action="move-down">下移</button>
								<button class="btn btn-ghost btn-small" type="button" data-action="remove-question">刪除題目</button>
							</div>
						</div>
					</template>

					<div style="margin-top:20px">
						<button class="btn btn-primary" type="submit">儲存變更</button>
						<a class="btn btn-ghost" target="_blank" href="submit.php?id=<?php echo $edit_id; ?>&preview=1">預覽</a>
						<a class="btn btn-ghost" href="view.php?id=<?php echo $edit_id; ?>">取消</a>
					</div>
				</form>

			<?php else : ?>
				<!-- ═══ 列表模式 ═══ -->
				<h1>修改表單</h1>
				<p class="muted">點選表單進行修改。</p>

				<?php if ($list_error) : ?>
					<div class="error"><?php echo htmlspecialchars($list_error); ?></div>
				<?php elseif (empty($forms_list)) : ?>
					<div class="panel" style="padding:20px">
						<p class="muted">目前沒有可編輯的表單。</p>
						<a class="btn btn-primary" href="create.php">建立新表單</a>
					</div>
				<?php else : ?>
					<div class="card-grid">
						<?php foreach ($forms_list as $form) : ?>
							<?php
								$type_label = $type_labels[$form['form_type']] ?? $form['form_type'];
								$status_label = $status_labels[$form['status']] ?? $form['status'];
								$created_at = !empty($form['created_at']) ? date('Y-m-d', strtotime($form['created_at'])) : '';
								$club_name = $form['club_name'] ?? '系統全域';
							?>
							<article class="panel form-preview fade-up">
								<span class="pill"><?php echo htmlspecialchars($type_label); ?></span>
								<h3><?php echo htmlspecialchars($form['title']); ?></h3>
								<p class="meta">社團：<?php echo htmlspecialchars($club_name); ?> ・ 建立者：<?php echo htmlspecialchars($form['username']); ?> ・ 狀態：<?php echo htmlspecialchars($status_label); ?> ・ <?php echo htmlspecialchars($created_at); ?></p>
								<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
									<a class="btn btn-primary" href="edit.php?id=<?php echo (int) $form['id']; ?>">修改此表單</a>
									<a class="btn btn-ghost" href="view.php?id=<?php echo (int) $form['id']; ?>">查看</a>
									<a class="btn btn-ghost" href="statistics.php?id=<?php echo (int) $form['id']; ?>">統計</a>
									<form method="post" action="delete.php" style="display:inline">
										<?php echo csrf_field(); ?>
										<input type="hidden" name="id" value="<?php echo (int) $form['id']; ?>" />
										<input type="hidden" name="redirect" value="edit.php" />
										<button type="submit" class="btn btn-ghost" data-confirm="確定要刪除此表單嗎？此操作無法復原。">刪除</button>
									</form>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</main>

	<footer class="footer container">社團表單系統</footer>
	<script src="../js/app.js"></script>
</body>
</html>
<?php exit();