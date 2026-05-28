<?php
session_start();
require '../includes/db.php';

$current_user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$current_user = null;
$is_admin = false;
$managed_clubs = [];
$errors = [];
$form_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$form = null;
$questions = [];
$options_map = [];
$submissions = [];
$answers_map = [];
$summary = [];
$chart_data = [];
$pdo = null;
$view = isset($_GET['view']) ? $_GET['view'] : 'detail';
if (!in_array($view, ['detail', 'summary'], true)) {
	$view = 'detail';
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

if (!$current_user_raw) {
	$errors[] = '請先登入才能查看填寫紀錄。';
}

if ($form_id <= 0) {
	$errors[] = '找不到指定的表單。';
}

try {
	$pdo = get_db();
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
				if (in_array($row['role'], ['owner', 'club_officer'], true)) {
					$managed_clubs[] = (int) $row['club_id'];
				}
			}
			$managed_clubs = array_values(array_unique($managed_clubs));
		}
	}
} catch (Throwable $e) {
	$errors[] = '資料庫連線失敗，請稍後再試。';
}

if (empty($errors)) {
	$stmt = $pdo->prepare('SELECT f.*, u.username, c.name AS club_name FROM forms f JOIN users u ON u.id = f.creator_id JOIN clubs c ON c.id = f.club_id WHERE f.id = :id LIMIT 1');
	$stmt->execute([':id' => $form_id]);
	$form = $stmt->fetch();
	if (!$form) {
		$errors[] = '找不到指定的表單。';
	}
}

$can_view = false;
if ($current_user && $form) {
	if ($is_admin) {
		$can_view = true;
	} elseif (in_array((int) $form['club_id'], $managed_clubs, true)) {
		$can_view = true;
	}
}

if ($form && !$can_view) {
	$errors[] = '你沒有權限查看此表單的填寫紀錄。';
}

if (empty($errors)) {
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

	$s_stmt = $pdo->prepare('SELECT s.id, s.submitted_at, s.user_id, s.ip_address, u.username AS submitter FROM form_submissions s LEFT JOIN users u ON u.id = s.user_id WHERE s.form_id = :id ORDER BY s.submitted_at DESC');
	$s_stmt->execute([':id' => $form_id]);
	$submissions = $s_stmt->fetchAll();

	if (!empty($submissions)) {
		$submission_ids = array_column($submissions, 'id');
		$placeholders = implode(',', array_fill(0, count($submission_ids), '?'));
		$a_stmt = $pdo->prepare('SELECT a.submission_id, a.question_id, a.answer_text, a.option_id, o.option_text FROM answers a LEFT JOIN question_options o ON o.id = a.option_id WHERE a.submission_id IN (' . $placeholders . ') ORDER BY a.submission_id ASC, a.question_id ASC, o.option_order ASC');
		$a_stmt->execute($submission_ids);
		$answers = $a_stmt->fetchAll();
		foreach ($answers as $row) {
			$value = '';
			if (!empty($row['option_id'])) {
				$value = $row['option_text'];
			} else {
				$value = $row['answer_text'];
			}
			if ($value === null || $value === '') {
				continue;
			}
			$answers_map[$row['submission_id']][$row['question_id']][] = $value;
		}
	}

	if (!empty($questions)) {
		$question_ids = array_column($questions, 'id');
		$placeholders = implode(',', array_fill(0, count($question_ids), '?'));
		$choice_counts = [];
		$text_counts = [];

		$choice_stmt = $pdo->prepare('SELECT question_id, option_id, COUNT(*) AS cnt FROM answers WHERE question_id IN (' . $placeholders . ') AND option_id IS NOT NULL GROUP BY question_id, option_id');
		$choice_stmt->execute($question_ids);
		foreach ($choice_stmt->fetchAll() as $row) {
			$choice_counts[$row['question_id']][$row['option_id']] = (int) $row['cnt'];
		}

		$text_stmt = $pdo->prepare('SELECT question_id, COUNT(*) AS cnt FROM answers WHERE question_id IN (' . $placeholders . ') AND answer_text IS NOT NULL AND answer_text <> "" GROUP BY question_id');
		$text_stmt->execute($question_ids);
		foreach ($text_stmt->fetchAll() as $row) {
			$text_counts[$row['question_id']] = (int) $row['cnt'];
		}

		foreach ($questions as $q) {
			$entry = [
				'id' => $q['id'],
				'text' => $q['question_text'],
				'type' => $q['question_type'],
				'required' => (bool) $q['is_required'],
				'answers' => []
			];
			if (in_array($q['question_type'], ['multiple_choice', 'multi_choice'], true)) {
				foreach ($options_map[$q['id']] ?? [] as $opt) {
					$entry['answers'][] = [
						'label' => $opt['option_text'],
						'count' => isset($choice_counts[$q['id']][$opt['id']]) ? $choice_counts[$q['id']][$opt['id']] : 0
					];
				}
				$chart_data[] = [
					'id' => $q['id'],
					'label' => $q['question_text'],
					'labels' => array_map(function ($row) { return $row['label']; }, $entry['answers']),
					'counts' => array_map(function ($row) { return $row['count']; }, $entry['answers'])
				];
			} else {
				$entry['answer_count'] = isset($text_counts[$q['id']]) ? $text_counts[$q['id']] : 0;
			}
			$summary[$q['id']] = $entry;
		}
	}
}
?>
<!doctype html>
<html lang="zh-Hant">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>填寫紀錄 | 社團表單系統</title>
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
				<h1>填寫紀錄</h1>
				<?php if (!empty($errors)) : ?>
					<div class="error">
						<ul>
							<?php foreach ($errors as $e) : ?>
								<li><?php echo htmlspecialchars($e); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
					<a class="btn btn-ghost" href="./list.php">返回列表</a>
				<?php else : ?>
					<?php
						$type_label = isset($type_labels[$form['form_type']]) ? $type_labels[$form['form_type']] : $form['form_type'];
						$status_label = isset($status_labels[$form['status']]) ? $status_labels[$form['status']] : $form['status'];
						$created_at = !empty($form['created_at']) ? date('Y-m-d', strtotime($form['created_at'])) : '';
					?>
					<div class="panel" style="padding: 20px">
						<span class="pill"><?php echo htmlspecialchars($type_label); ?></span>
						<h2><?php echo htmlspecialchars($form['title']); ?></h2>
						<p class="muted"><?php echo htmlspecialchars($form['description'] ?: '尚未提供表單說明。'); ?></p>
						<p class="meta">
							出題者：<?php echo htmlspecialchars($form['username']); ?> ・ 狀態：<?php echo htmlspecialchars($status_label); ?> ・ 建立日：<?php echo htmlspecialchars($created_at); ?> ・ 總填寫數：<?php echo number_format(count($submissions)); ?>
						</p>
						<div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap">
							<?php if ($view === 'detail') : ?>
								<a class="btn btn-primary" href="./statistics.php?id=<?php echo $form_id; ?>&view=detail">詳細紀錄</a>
								<a class="btn btn-ghost" href="./statistics.php?id=<?php echo $form_id; ?>&view=summary">統整圖表</a>
							<?php else : ?>
								<a class="btn btn-ghost" href="./statistics.php?id=<?php echo $form_id; ?>&view=detail">詳細紀錄</a>
								<a class="btn btn-primary" href="./statistics.php?id=<?php echo $form_id; ?>&view=summary">統整圖表</a>
							<?php endif; ?>
							<a class="btn btn-ghost" href="./view.php?id=<?php echo $form_id; ?>">返回表單</a>
							<a class="btn btn-ghost" href="./edit.php?id=<?php echo $form_id; ?>">修改表單</a>
						</div>
					</div>

					<?php if ($view === 'summary') : ?>
						<?php if (empty($summary)) : ?>
							<div class="panel" style="padding: 20px; margin-top: 16px">
								<p class="muted">尚無題目可供統計。</p>
							</div>
						<?php else : ?>
							<?php foreach ($questions as $q) : ?>
								<?php
									$summary_item = $summary[$q['id']] ?? null;
									if (!$summary_item) {
										continue;
									}
								?>
								<div class="panel" style="padding: 20px; margin-top: 16px">
									<h3>Q<?php echo htmlspecialchars($q['question_order']); ?>. <?php echo htmlspecialchars($q['question_text']); ?></h3>
									<p class="muted">題型：<?php echo htmlspecialchars($q['question_type']); ?> ・ <?php echo $q['is_required'] ? '必填' : '選填'; ?></p>
									<?php if (in_array($q['question_type'], ['multiple_choice', 'multi_choice'], true)) : ?>
										<canvas id="chart-q-<?php echo $q['id']; ?>" height="140"></canvas>
										<div style="margin-top: 8px">
											<?php foreach ($summary_item['answers'] as $row) : ?>
												<div class="meta"><?php echo htmlspecialchars($row['label']); ?>：<?php echo number_format($row['count']); ?></div>
											<?php endforeach; ?>
										</div>
									<?php else : ?>
										<p class="muted">已填：<?php echo number_format($summary_item['answer_count']); ?> / <?php echo number_format(count($submissions)); ?></p>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					<?php else : ?>
						<?php if (empty($submissions)) : ?>
							<div class="panel" style="padding: 20px; margin-top: 16px">
								<p class="muted">尚無填寫紀錄。</p>
							</div>
						<?php else : ?>
							<?php foreach ($submissions as $index => $submission) : ?>
								<?php
									$submitter = $submission['submitter'] ?: '訪客';
									$submitted_at = !empty($submission['submitted_at']) ? date('Y-m-d H:i', strtotime($submission['submitted_at'])) : '';
									$ip = $submission['ip_address'] ?: '-';
								?>
								<div class="panel" style="padding: 20px; margin-top: 16px">
									<h3>填寫紀錄 #<?php echo htmlspecialchars($submission['id']); ?></h3>
									<p class="meta">填寫者：<?php echo htmlspecialchars($submitter); ?> ・ IP：<?php echo htmlspecialchars($ip); ?> ・ 時間：<?php echo htmlspecialchars($submitted_at); ?></p>
									<?php if (empty($questions)) : ?>
										<p class="muted">此表單沒有題目。</p>
									<?php else : ?>
										<?php foreach ($questions as $q) : ?>
											<?php
												$answers = $answers_map[$submission['id']][$q['id']] ?? [];
												$answer_text = '未填';
												if (!empty($answers)) {
													if (count($answers) === 1) {
														$answer_text = nl2br(htmlspecialchars($answers[0]));
													} else {
														$clean_answers = array_map('htmlspecialchars', $answers);
														$answer_text = implode('、', $clean_answers);
													}
												}
											?>
											<div style="padding: 12px 0; border-bottom: 1px solid #e4efe8">
												<strong>Q<?php echo htmlspecialchars($q['question_order']); ?>. <?php echo htmlspecialchars($q['question_text']); ?></strong>
												<p class="muted">題型：<?php echo htmlspecialchars($q['question_type']); ?> ・ <?php echo $q['is_required'] ? '必填' : '選填'; ?></p>
												<p><?php echo $answer_text; ?></p>
											</div>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</main>

		<footer class="footer container">社團表單系統</footer>
		<script src="../js/app.js"></script>
		<?php if ($view === 'summary' && !empty($chart_data)) : ?>
			<script src="../js/chart.umd.js"></script>
			<script>
				(function(){
					var charts = <?php echo json_encode($chart_data, JSON_UNESCAPED_UNICODE); ?>;
					charts.forEach(function(item){
						var canvas = document.getElementById('chart-q-' + item.id);
						if (!canvas || !window.Chart) {
							return;
						}
						var ctx = canvas.getContext('2d');
						new Chart(ctx, {
							type: 'bar',
							data: {
								labels: item.labels,
								datasets: [{
									label: '選擇數',
									data: item.counts,
									backgroundColor: 'rgba(11, 122, 90, 0.5)',
									borderColor: 'rgba(11, 122, 90, 1)',
									borderWidth: 1
								}]
							},
							options: {
								responsive: true,
								plugins: {
									legend: {
										display: false
									}
								},
								scales: {
									y: {
										beginAtZero: true,
										precision: 0
									}
								}
							}
						});
					});
				})();
			</script>
		<?php endif; ?>
	</body>
</html>

<?php
exit();
