<?php
session_start();
require __DIR__ . '/../includes/db.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$user = !empty($user_raw) ? htmlspecialchars($user_raw) : null;

$errors = [];
$current_user = null;
$club_options = [];
$allowed_clubs = [];
$all_clubs = [];
$can_select_any = false;
$form_id = null;
$defaults = [
	'title' => '',
	'description' => '',
	'form_type' => 'public',
	'target_club_category' => [],
	'status' => 'draft'
];
$question_defaults = [];

$allowed_types = ['short_answer', 'long_answer', 'multiple_choice', 'multi_choice'];
$allowed_status = ['draft', 'published', 'closed'];

if (!empty($user_raw)) {
	try {
		$pdo = get_db();
		$u = $pdo->prepare('SELECT id, username, role, club_category FROM users WHERE username = :u LIMIT 1');
		$u->execute([':u' => $user_raw]);
		$current_user = $u->fetch();

		$club_stmt = $pdo->query('SELECT name FROM clubs ORDER BY name ASC');
		$all_clubs = array_column($club_stmt->fetchAll(), 'name');

		if ($current_user) {
			if ($current_user['role'] === 'admin') {
				$can_select_any = true;
				$club_options = $all_clubs;
			} else {
				if (!empty($current_user['club_category'])) {
					$allowed_clubs[] = $current_user['club_category'];
				}
				$owned_stmt = $pdo->prepare('SELECT name FROM clubs WHERE owner_user_id = :id');
				$owned_stmt->execute([':id' => $current_user['id']]);
				$owned_clubs = array_column($owned_stmt->fetchAll(), 'name');
				$allowed_clubs = array_values(array_unique(array_merge($allowed_clubs, $owned_clubs)));
				$club_options = $allowed_clubs;
			}
		}
	} catch (Throwable $e) {
		$errors[] = '無法載入社團清單。';
	}
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$can_select_any && empty($defaults['target_club_category']) && !empty($club_options)) {
	$defaults['target_club_category'] = [$club_options[0]];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$defaults['title'] = trim(isset($_POST['title']) ? $_POST['title'] : '');
	$defaults['description'] = trim(isset($_POST['description']) ? $_POST['description'] : '');
	$defaults['form_type'] = isset($_POST['form_type']) ? $_POST['form_type'] : 'public';
	$target_input = isset($_POST['target_club_category']) ? $_POST['target_club_category'] : [];
	if (!is_array($target_input)) {
		$target_input = [$target_input];
	}
	$target_input = array_values(array_filter(array_map('trim', $target_input), 'strlen'));
	$defaults['target_club_category'] = $target_input;
	$defaults['status'] = isset($_POST['status']) ? $_POST['status'] : 'draft';

	$questions_input = (isset($_POST['questions']) && is_array($_POST['questions'])) ? $_POST['questions'] : [];
	foreach ($questions_input as $q) {
		$text = trim(isset($q['text']) ? $q['text'] : '');
		$type = isset($q['type']) ? $q['type'] : 'short_answer';
		$required = !empty($q['required']);
		$options_raw = (isset($q['options']) && is_array($q['options'])) ? $q['options'] : [];
		$options = [];
		foreach ($options_raw as $opt) {
			$options[] = trim($opt);
		}
		if (count($options) < 2) {
			$options = array_pad($options, 2, '');
		}
		$question_defaults[] = [
			'text' => $text,
			'type' => $type,
			'required' => $required,
			'options' => $options
		];
	}

	if (empty($user_raw)) {
		$errors[] = '請先登入才能建立表單。';
	}
	if ($defaults['title'] === '') {
		$errors[] = '請輸入表單標題。';
	}

	if (!in_array($defaults['form_type'], ['public', 'club_only'], true)) {
		$defaults['form_type'] = 'public';
	}
	if (!in_array($defaults['status'], $allowed_status, true)) {
		$defaults['status'] = 'draft';
	}
	if ($defaults['form_type'] === 'club_only' && empty($defaults['target_club_category'])) {
		$errors[] = '限定社團表單請選擇社團。';
	}
	if ($defaults['form_type'] === 'club_only') {
		if ($can_select_any) {
			foreach ($defaults['target_club_category'] as $club_name) {
				if (!in_array($club_name, $club_options, true)) {
					$errors[] = '請選擇有效的社團。';
					break;
				}
			}
		} else {
			if (empty($club_options)) {
				$errors[] = '你尚未綁定任何社團，無法建立限定社團表單。';
			} else {
				$allowed = array_values(array_intersect($defaults['target_club_category'], $club_options));
				if (empty($allowed)) {
					$defaults['target_club_category'] = [$club_options[0]];
					$errors[] = '社團管理員只能選擇自己的社團。';
				} else {
					if (count($allowed) > 1) {
						$errors[] = '社團管理員一次只能選擇一個社團。';
					}
					$defaults['target_club_category'] = [$allowed[0]];
				}
			}
		}
	}

	$valid_questions = [];
	foreach ($question_defaults as $idx => $q) {
		if ($q['text'] === '') {
			continue;
		}
		if (!in_array($q['type'], $allowed_types, true)) {
			$errors[] = '第 ' . ($idx + 1) . ' 題的題型不正確。';
			continue;
		}
		if (in_array($q['type'], ['multiple_choice', 'multi_choice'], true)) {
			$options_filtered = array_values(array_filter($q['options'], 'strlen'));
			if (count($options_filtered) < 2) {
				$errors[] = '第 ' . ($idx + 1) . ' 題至少需要 2 個選項。';
				continue;
			}
			$q['options'] = $options_filtered;
		}
		$valid_questions[] = $q;
	}

	if (empty($valid_questions)) {
		$errors[] = '請至少新增一題表單題目。';
	}

	if (empty($errors)) {
		try {
			$pdo = get_db();
			$pdo->beginTransaction();
			$u = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
			$u->execute([':u' => $user_raw]);
			$user_row = $u->fetch();
			if (!$user_row) {
				throw new RuntimeException('找不到登入者資訊。');
			}

			$target_club = ($defaults['form_type'] === 'club_only') ? implode(',', $defaults['target_club_category']) : null;
			$f = $pdo->prepare('INSERT INTO forms (creator_id, title, description, form_type, target_club_category, status) VALUES (:c, :t, :d, :ft, :tc, :s)');
			$f->execute([
				':c' => $user_row['id'],
				':t' => $defaults['title'],
				':d' => $defaults['description'] ?: null,
				':ft' => $defaults['form_type'],
				':tc' => $target_club,
				':s' => $defaults['status']
			]);
			$form_id = (int) $pdo->lastInsertId();

			$q_stmt = $pdo->prepare('INSERT INTO form_questions (form_id, question_order, question_text, question_type, is_required) VALUES (:f, :o, :t, :qt, :r)');
			$o_stmt = $pdo->prepare('INSERT INTO question_options (question_id, option_text, option_order) VALUES (:q, :t, :o)');
			foreach ($valid_questions as $order => $q) {
				$q_stmt->execute([
					':f' => $form_id,
					':o' => $order + 1,
					':t' => $q['text'],
					':qt' => $q['type'],
					':r' => $q['required'] ? 1 : 0
				]);
				$question_id = (int) $pdo->lastInsertId();
				if (in_array($q['type'], ['multiple_choice', 'multi_choice'], true)) {
					foreach ($q['options'] as $opt_index => $opt_text) {
						$o_stmt->execute([
							':q' => $question_id,
							':t' => $opt_text,
							':o' => $opt_index + 1
						]);
					}
				}
			}
			$pdo->commit();
			header('Location: /group_41/forms/view.php?id=' . $form_id);
			exit();
		} catch (Throwable $e) {
			if (!empty($pdo) && $pdo->inTransaction()) {
				$pdo->rollBack();
			}
			$errors[] = '建立表單失敗，請稍後再試。';
		}
	}
}

if (empty($question_defaults)) {
	$question_defaults[] = [
		'text' => '',
		'type' => 'short_answer',
		'required' => true,
		'options' => ['', '']
	];
}
?>
<!doctype html>
<html lang="zh-Hant">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>建立表單 | 社團表單系統</title>
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
				<h1>建立表單</h1>
				<p class="muted">建立新的社團表單，支援不同題型與選項。</p>
				<?php if ($user) : ?>
					<p class="muted">目前已登入：<?php echo $user; ?></p>
				<?php endif; ?>
				<?php if (!empty($errors)) : ?>
					<div class="error">
						<ul>
							<?php foreach ($errors as $e) : ?>
								<li><?php echo htmlspecialchars($e); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<form class="panel" style="padding: 20px" method="post" action="/group_41/forms/create.php">
					<div class="field">
						<label for="title">表單標題</label>
						<input id="title" name="title" required value="<?php echo htmlspecialchars($defaults['title']); ?>" />
					</div>
					<div class="field">
						<label for="description">表單說明</label>
						<textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($defaults['description']); ?></textarea>
					</div>
					<div class="field">
						<label for="form_type">表單類型</label>
						<select id="form_type" name="form_type">
							<option value="public" <?php echo $defaults['form_type'] === 'public' ? 'selected' : ''; ?>>公開表單</option>
							<option value="club_only" <?php echo $defaults['form_type'] === 'club_only' ? 'selected' : ''; ?>>限定社團</option>
						</select>
					</div>
					<div class="field" id="targetClubWrap">
						<label for="target_club_category">限定社團名稱</label>
						<?php if ($can_select_any && !empty($all_clubs)) : ?>
							<select id="target_club_category" name="target_club_category[]" multiple size="6">
								<option value="">請選擇</option>
								<?php foreach ($club_options as $club_name) : ?>
									<option value="<?php echo htmlspecialchars($club_name); ?>" <?php echo in_array($club_name, $defaults['target_club_category'], true) ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars($club_name); ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php elseif (!empty($club_options)) : ?>
							<select id="target_club_category" name="target_club_category[]">
								<?php foreach ($club_options as $club_name) : ?>
									<option value="<?php echo htmlspecialchars($club_name); ?>" <?php echo in_array($club_name, $defaults['target_club_category'], true) ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars($club_name); ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php else : ?>
							<input id="target_club_category" name="target_club_category" placeholder="尚無可選社團" disabled />
						<?php endif; ?>
						<p class="muted" style="margin-top: 6px">幹部/社團持有人只能選擇自己的社團，管理員可多選（Ctrl/Command）。</p>
					</div>
					<div class="field">
						<label for="status">狀態</label>
						<select id="status" name="status">
							<option value="draft" <?php echo $defaults['status'] === 'draft' ? 'selected' : ''; ?>>草稿</option>
							<option value="published" <?php echo $defaults['status'] === 'published' ? 'selected' : ''; ?>>發布</option>
							<option value="closed" <?php echo $defaults['status'] === 'closed' ? 'selected' : ''; ?>>關閉</option>
						</select>
					</div>

					<h2 style="margin-top: 24px">題目設定</h2>
					<div id="questionList" data-next-index="<?php echo count($question_defaults); ?>">
						<?php foreach ($question_defaults as $index => $q) : ?>
							<?php $show_options = in_array($q['type'], ['multiple_choice', 'multi_choice'], true); ?>
							<div class="panel" style="padding: 16px; margin-bottom: 16px" data-question-block="<?php echo $index; ?>">
								<div class="field">
									<label>題目說明</label>
									<input name="questions[<?php echo $index; ?>][text]" value="<?php echo htmlspecialchars($q['text']); ?>" />
								</div>
								<div class="field">
									<label>題型</label>
									<select name="questions[<?php echo $index; ?>][type]" data-role="question-type">
										<option value="short_answer" <?php echo $q['type'] === 'short_answer' ? 'selected' : ''; ?>>簡答</option>
										<option value="long_answer" <?php echo $q['type'] === 'long_answer' ? 'selected' : ''; ?>>長答</option>
										<option value="multiple_choice" <?php echo $q['type'] === 'multiple_choice' ? 'selected' : ''; ?>>單選</option>
										<option value="multi_choice" <?php echo $q['type'] === 'multi_choice' ? 'selected' : ''; ?>>多選</option>
									</select>
								</div>
								<div class="field">
									<label class="check-row">
										<input type="checkbox" name="questions[<?php echo $index; ?>][required]" value="1" <?php echo $q['required'] ? 'checked' : ''; ?> />
										必填
									</label>
								</div>
								<div class="field option-group" data-role="option-group" style="<?php echo $show_options ? '' : 'display: none'; ?>">
									<label>選項（單選/多選用）</label>
									<div class="options">
										<?php foreach ($q['options'] as $opt) : ?>
											<div class="option-row">
												<input name="questions[<?php echo $index; ?>][options][]" value="<?php echo htmlspecialchars($opt); ?>" placeholder="選項內容" />
												<button class="btn btn-ghost btn-small" type="button" data-action="remove-option">刪除</button>
											</div>
										<?php endforeach; ?>
									</div>
									<button class="btn btn-ghost btn-small" type="button" data-action="add-option" data-question="<?php echo $index; ?>">新增選項</button>
								</div>
								<div class="question-actions">
									<button class="btn btn-ghost btn-small" type="button" data-action="remove-question">刪除題目</button>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<button class="btn btn-ghost" type="button" id="addQuestionBtn">新增題目</button>
					<template id="questionTemplate">
						<div class="panel" style="padding: 16px; margin-bottom: 16px" data-question-block="__INDEX__">
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
								</select>
							</div>
							<div class="field">
								<label class="check-row">
									<input type="checkbox" name="questions[__INDEX__][required]" value="1" checked />
									必填
								</label>
							</div>
							<div class="field option-group" data-role="option-group" style="display: none">
								<label>選項（單選/多選用）</label>
								<div class="options">
									<div class="option-row">
										<input name="questions[__INDEX__][options][]" placeholder="選項內容" />
										<button class="btn btn-ghost btn-small" type="button" data-action="remove-option">刪除</button>
									</div>
									<div class="option-row">
										<input name="questions[__INDEX__][options][]" placeholder="選項內容" />
										<button class="btn btn-ghost btn-small" type="button" data-action="remove-option">刪除</button>
									</div>
								</div>
								<button class="btn btn-ghost btn-small" type="button" data-action="add-option" data-question="__INDEX__">新增選項</button>
							</div>
							<div class="question-actions">
								<button class="btn btn-ghost btn-small" type="button" data-action="remove-question">刪除題目</button>
							</div>
						</div>
					</template>

					<div style="margin-top: 20px">
						<button class="btn btn-primary" type="submit" <?php echo empty($user_raw) ? 'disabled' : ''; ?>>建立表單</button>
						<a class="btn btn-ghost" href="/group_41/forms/list.php">回表單列表</a>
					</div>
				</form>
			</div>
		</main>

		<footer class="footer container">社團表單系統</footer>
		<script src="/group_41/js/app.js"></script>
	</body>
</html>

<?php
exit();
