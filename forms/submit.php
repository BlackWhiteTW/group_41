<?php
session_start();

require '../includes/db.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$user = !empty($user_raw) ? htmlspecialchars($user_raw) : null;
$current_user = null;
$is_admin = false;
$member_clubs = [];
$club_map = [];
$form_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$form = null;
$questions = [];
$options_map = [];
$errors = [];
$target_clubs = [];

function parse_target_clubs($value)
{
	if (!is_string($value) || trim($value) === '') {
		return [];
	}
	$items = array_map('trim', explode(',', $value));
	$items = array_values(array_filter($items, 'strlen'));
	return array_values(array_unique(array_map('intval', $items)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$form_id = isset($_POST['form_id']) ? (int) $_POST['form_id'] : 0;
}

if ($form_id > 0) {
	try {
		$pdo = get_db();
		$club_rows = $pdo->query('SELECT id, name FROM clubs')->fetchAll();
		foreach ($club_rows as $club_row) {
			$club_map[(int) $club_row['id']] = $club_row['name'];
		}
		if ($user_raw) {
			$u = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
			$u->execute([':u' => $user_raw]);
			$current_user = $u->fetch();
			if ($current_user && $current_user['role'] === 'admin') {
				$is_admin = true;
			} elseif ($current_user) {
				$mem_stmt = $pdo->prepare('SELECT club_id FROM club_memberships WHERE user_id = :id');
				$mem_stmt->execute([':id' => $current_user['id']]);
				$member_clubs = array_map('intval', array_column($mem_stmt->fetchAll(), 'club_id'));
			}
		}
		$stmt = $pdo->prepare('SELECT f.*, u.username FROM forms f JOIN users u ON u.id = f.creator_id WHERE f.id = :id LIMIT 1');
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
		$errors[] = '表單資料載入失敗，請稍後再試。';
	}
}

if ($form && empty($errors) && $form['form_type'] === 'club_only') {
	$target_clubs = parse_target_clubs($form['target_club_ids']);
	if (!$current_user) {
		$errors[] = '此表單僅限社團成員填寫，請先登入。';
	} elseif (!$is_admin && empty(array_intersect($target_clubs, $member_clubs))) {
		$target_names = [];
		foreach ($target_clubs as $cid) {
			if (isset($club_map[$cid])) {
				$target_names[] = $club_map[$cid];
			}
		}
		$errors[] = '此表單僅限 ' . ($target_names ? implode('、', $target_names) : '指定社團') . ' 成員填寫。';
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $form && empty($errors)) {
	if ($form['status'] !== 'published') {
		$errors[] = '此表單尚未發布，無法填寫。';
	} else {
		$answers = (isset($_POST['answers']) && is_array($_POST['answers'])) ? $_POST['answers'] : [];
		$valid_option_ids = [];
		foreach ($options_map as $qid => $opts) {
			$valid_option_ids[$qid] = array_column($opts, 'id');
		}

		foreach ($questions as $q) {
			$qid = $q['id'];
			$required = (bool) $q['is_required'];
			$type = $q['question_type'];
			$value = isset($answers[$qid]) ? $answers[$qid] : null;

			if (in_array($type, ['short_answer', 'long_answer'], true)) {
				$text = is_string($value) ? trim($value) : '';
				if ($required && $text === '') {
					$errors[] = '題目「' . $q['question_text'] . '」為必填。';
				}
			} elseif ($type === 'multiple_choice') {
				$option_id = (int) $value;
				if ($required && $option_id === 0) {
					$errors[] = '題目「' . $q['question_text'] . '」為必填。';
				} elseif ($option_id !== 0 && (!isset($valid_option_ids[$qid]) || !in_array($option_id, $valid_option_ids[$qid], true))) {
					$errors[] = '題目「' . $q['question_text'] . '」的選項無效。';
				}
			} elseif ($type === 'multi_choice') {
				$option_ids = is_array($value) ? $value : [];
				$option_ids = array_map('intval', $option_ids);
				if ($required && empty($option_ids)) {
					$errors[] = '題目「' . $q['question_text'] . '」為必填。';
				} else {
					foreach ($option_ids as $oid) {
						if (!isset($valid_option_ids[$qid]) || !in_array($oid, $valid_option_ids[$qid], true)) {
							$errors[] = '題目「' . $q['question_text'] . '」的選項無效。';
							break;
						}
					}
				}
			}
		}
	}

	if (empty($errors)) {
		try {
			$pdo = get_db();
			$pdo->beginTransaction();
			$user_id = null;
			if ($user_raw) {
				$u = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
				$u->execute([':u' => $user_raw]);
				$urow = $u->fetch();
				$user_id = $urow ? (int) $urow['id'] : null;
			}
			$s = $pdo->prepare('INSERT INTO form_submissions (form_id, user_id, ip_address) VALUES (:f, :u, :ip)');
			$s->execute([
				':f' => $form_id,
				':u' => $user_id,
				':ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null
			]);
			$submission_id = (int) $pdo->lastInsertId();

			$a = $pdo->prepare('INSERT INTO answers (submission_id, question_id, answer_text, option_id) VALUES (:s, :q, :t, :o)');
			foreach ($questions as $q) {
				$qid = $q['id'];
				$type = $q['question_type'];
				$value = isset($answers[$qid]) ? $answers[$qid] : null;

				if (in_array($type, ['short_answer', 'long_answer'], true)) {
					$text = is_string($value) ? trim($value) : '';
					if ($text !== '') {
						$a->execute([
							':s' => $submission_id,
							':q' => $qid,
							':t' => $text,
							':o' => null
						]);
					}
				} elseif ($type === 'multiple_choice') {
					$option_id = (int) $value;
					if ($option_id) {
						$a->execute([
							':s' => $submission_id,
							':q' => $qid,
							':t' => null,
							':o' => $option_id
						]);
					}
				} elseif ($type === 'multi_choice') {
					$option_ids = is_array($value) ? $value : [];
					$option_ids = array_map('intval', $option_ids);
					foreach ($option_ids as $oid) {
						if ($oid) {
							$a->execute([
								':s' => $submission_id,
								':q' => $qid,
								':t' => null,
								':o' => $oid
							]);
						}
					}
				}
			}

			$pdo->commit();
			header('Location: ./success.php?id=' . $submission_id);
			exit();
		} catch (Throwable $e) {
			if (!empty($pdo) && $pdo->inTransaction()) {
				$pdo->rollBack();
			}
			$errors[] = '送出失敗，請稍後再試。';
		}
	}
}
?>
<!doctype html>
<html lang="zh-Hant">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>填寫表單 | 社團表單系統</title>
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
				<h1>填寫表單</h1>
				<?php if (!empty($errors)) : ?>
					<div class="error">
						<ul>
							<?php foreach ($errors as $e) : ?>
								<li><?php echo htmlspecialchars($e); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<?php if (!empty($errors)) : ?>
					<div class="panel" style="padding: 20px">
						<a class="btn btn-ghost" href="./list.php">返回列表</a>
					</div>
				<?php elseif (!$form) : ?>
					<div class="panel" style="padding: 20px">
						<p class="muted">找不到指定的表單。</p>
						<a class="btn btn-ghost" href="./list.php">返回列表</a>
					</div>
				<?php else : ?>
					<div class="panel" style="padding: 20px">
						<h2><?php echo htmlspecialchars($form['title']); ?></h2>
						<p class="muted"><?php echo htmlspecialchars($form['description'] ?: '尚未提供表單說明。'); ?></p>
						<?php if ($form['status'] !== 'published') : ?>
							<p class="muted">此表單尚未發布，暫時無法填寫。</p>
						<?php else : ?>
							<form method="post" action="./submit.php">
								<input type="hidden" name="form_id" value="<?php echo $form_id; ?>" />
								<?php foreach ($questions as $q) : ?>
									<div class="field" style="margin-top: 16px">
										<label>
											<?php echo htmlspecialchars($q['question_text']); ?>
											<?php if ($q['is_required']) : ?>
												<span class="muted">(必填)</span>
											<?php endif; ?>
										</label>
										<?php if (in_array($q['question_type'], ['short_answer', 'long_answer'], true)) : ?>
											<?php if ($q['question_type'] === 'short_answer') : ?>
												<input name="answers[<?php echo $q['id']; ?>]" />
											<?php else : ?>
												<textarea name="answers[<?php echo $q['id']; ?>]" rows="3"></textarea>
											<?php endif; ?>
										<?php elseif ($q['question_type'] === 'multiple_choice') : ?>
											<?php foreach ($options_map[$q['id']] ?? [] as $opt) : ?>
												<label style="display: block; margin-top: 6px">
													<input type="radio" name="answers[<?php echo $q['id']; ?>]" value="<?php echo $opt['id']; ?>" />
													<?php echo htmlspecialchars($opt['option_text']); ?>
												</label>
											<?php endforeach; ?>
										<?php elseif ($q['question_type'] === 'multi_choice') : ?>
											<?php foreach ($options_map[$q['id']] ?? [] as $opt) : ?>
												<label style="display: block; margin-top: 6px">
													<input type="checkbox" name="answers[<?php echo $q['id']; ?>][]" value="<?php echo $opt['id']; ?>" />
													<?php echo htmlspecialchars($opt['option_text']); ?>
												</label>
											<?php endforeach; ?>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
								<button class="btn btn-primary" type="submit" style="margin-top: 16px">送出</button>
							</form>
						<?php endif; ?>
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
