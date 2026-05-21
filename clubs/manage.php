<?php
session_start();
require __DIR__ . '/../includes/db.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$user = !empty($user_raw) ? htmlspecialchars($user_raw) : null;
$errors = [];
$current_user = null;
$is_admin = false;

$clubs = [];
$club = null;
$members = [];
$officers = [];
$public_forms = [];
$private_forms = [];

$selected_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$user_raw) {
	header('Location: /group_41/login.php');
	exit;
}

try {
	$pdo = get_db();
	$user_stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
	$user_stmt->execute([':u' => $user_raw]);
	$current_user = $user_stmt->fetch();
	if (!$current_user) {
		$errors[] = '找不到登入帳號資料。';
	} else {
		$is_admin = ($current_user['role'] === 'admin');
	}

	if (empty($errors)) {
		if ($is_admin) {
			$clubs = $pdo->query('SELECT c.id, c.name, u.username AS owner_name FROM clubs c JOIN users u ON u.id = c.owner_user_id ORDER BY c.name ASC')->fetchAll();
		} else {
			$mem_stmt = $pdo->prepare('SELECT club_id FROM club_memberships WHERE user_id = :id');
			$mem_stmt->execute([':id' => $current_user['id']]);
			$member_club_ids = array_map('intval', array_column($mem_stmt->fetchAll(), 'club_id'));

			if (!empty($member_club_ids)) {
				$placeholders = implode(',', array_fill(0, count($member_club_ids), '?'));
				$club_stmt = $pdo->prepare("SELECT c.id, c.name, u.username AS owner_name FROM clubs c JOIN users u ON u.id = c.owner_user_id WHERE c.id IN ($placeholders) ORDER BY c.name ASC");
				$club_stmt->execute($member_club_ids);
				$clubs = $club_stmt->fetchAll();
			}
		}
	}

	if (empty($clubs)) {
		$errors[] = $is_admin ? '尚未建立任何社團。' : '你尚未加入任何社團。';
	} else {
		if ($selected_id === 0) {
			$selected_id = (int) $clubs[0]['id'];
		} else {
			$allowed_ids = array_map('intval', array_column($clubs, 'id'));
			if (!in_array($selected_id, $allowed_ids, true)) {
				$selected_id = (int) $clubs[0]['id'];
			}
		}
		$stmt = $pdo->prepare('SELECT c.id, c.name, c.owner_user_id, u.username AS owner_name, u.email AS owner_email FROM clubs c JOIN users u ON u.id = c.owner_user_id WHERE c.id = :id LIMIT 1');
		$stmt->execute([':id' => $selected_id]);
		$club = $stmt->fetch();
		if (!$club) {
			$errors[] = '找不到指定的社團。';
		} else {
			$member_stmt = $pdo->prepare('SELECT u.username, u.email, m.role FROM club_memberships m JOIN users u ON u.id = m.user_id WHERE m.club_id = :club ORDER BY m.role DESC, u.username ASC');
			$member_stmt->execute([':club' => $club['id']]);
			$members_all = $member_stmt->fetchAll();
			foreach ($members_all as $member) {
				if (in_array($member['role'], ['owner', 'club_officer'], true)) {
					$officers[] = $member;
				} elseif ($member['role'] === 'member') {
					$members[] = $member;
				}
			}

			$form_stmt = $pdo->prepare('SELECT f.id, f.title, f.form_type, f.status, f.created_at, u.username FROM forms f JOIN users u ON u.id = f.creator_id WHERE f.club_id = :club ORDER BY f.created_at DESC');
			$form_stmt->execute([':club' => $club['id']]);
			$forms = $form_stmt->fetchAll();
			foreach ($forms as $form) {
				if ($form['form_type'] === 'public') {
					$public_forms[] = $form;
				} else {
					$private_forms[] = $form;
				}
			}
		}
	}
} catch (Throwable $e) {
	$errors[] = '資料庫讀取失敗，請稍後再試。';
}
?>
<!doctype html>
<html lang="zh-Hant">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>社團資訊 | 社團表單系統</title>
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
				<h1>社團資訊</h1>
				<p class="muted">查看社團成員、幹部與社團表單資訊。</p>
				<?php if (!empty($errors)) : ?>
					<div class="error">
						<ul>
							<?php foreach ($errors as $e) : ?>
								<li><?php echo htmlspecialchars($e); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
					<?php if (!$is_admin && in_array('你尚未加入任何社團。', $errors, true)) : ?>
						<div class="panel" style="padding: 20px">
							<p class="muted">你目前沒有綁定社團，可請管理員協助設定，或重新註冊並建立/加入社團。</p>
							<div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap">
								<a class="btn btn-primary" href="/group_41/register.php">建立/加入社團</a>
								<a class="btn btn-ghost" href="/group_41/index.php">回首頁</a>
							</div>
						</div>
					<?php endif; ?>
				<?php else : ?>
					<div style="display: grid; gap: 20px; grid-template-columns: minmax(220px, 1fr) minmax(0, 3fr)">
						<div class="panel" style="padding: 16px">
							<h3>社團清單</h3>
							<div style="display: grid; gap: 10px; margin-top: 12px">
								<?php foreach ($clubs as $item) : ?>
									<?php $active = ($club && $club['id'] == $item['id']); ?>
									<a
										href="/group_41/clubs/manage.php?id=<?php echo (int) $item['id']; ?>"
										class="panel"
										style="padding: 12px; border-color: <?php echo $active ? '#8bc9b4' : '#e0e9e3'; ?>; background: <?php echo $active ? '#eef7f3' : 'rgba(255,255,255,0.9)'; ?>"
									>
										<strong><?php echo htmlspecialchars($item['name']); ?></strong>
										<p class="muted" style="margin-top: 4px">擁有人：<?php echo htmlspecialchars($item['owner_name']); ?></p>
									</a>
								<?php endforeach; ?>
							</div>
						</div>

						<div style="display: grid; gap: 16px">
							<div class="panel" style="padding: 20px">
								<h2><?php echo htmlspecialchars($club['name']); ?></h2>
								<p class="meta">擁有人：<?php echo htmlspecialchars($club['owner_name']); ?><?php echo $club['owner_email'] ? ' ・ ' . htmlspecialchars($club['owner_email']) : ''; ?></p>
								<div style="display: flex; gap: 16px; flex-wrap: wrap; margin-top: 12px">
									<span class="pill">幹部：<?php echo number_format(count($officers)); ?></span>
									<span class="pill">成員：<?php echo number_format(count($members)); ?></span>
									<span class="pill">公開表單：<?php echo number_format(count($public_forms)); ?></span>
									<span class="pill">非公開表單：<?php echo number_format(count($private_forms)); ?></span>
								</div>
							</div>

							<div class="panel" style="padding: 20px">
								<h3>幹部人員</h3>
								<?php if (empty($officers)) : ?>
									<p class="muted">尚未設定幹部。</p>
								<?php else : ?>
									<ul>
										<?php foreach ($officers as $officer) : ?>
											<li><?php echo htmlspecialchars($officer['username']); ?><?php echo $officer['email'] ? ' ・ ' . htmlspecialchars($officer['email']) : ''; ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</div>

							<div class="panel" style="padding: 20px">
								<h3>成員</h3>
								<?php if (empty($members)) : ?>
									<p class="muted">尚未設定成員。</p>
								<?php else : ?>
									<ul>
										<?php foreach ($members as $member) : ?>
											<li><?php echo htmlspecialchars($member['username']); ?><?php echo $member['email'] ? ' ・ ' . htmlspecialchars($member['email']) : ''; ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</div>

							<div class="panel" style="padding: 20px">
								<h3>社團表單（公開）</h3>
								<?php if (empty($public_forms)) : ?>
									<p class="muted">尚無公開表單。</p>
								<?php else : ?>
									<?php foreach ($public_forms as $form) : ?>
										<div style="padding: 10px 0; border-bottom: 1px solid #e4efe8">
											<strong><?php echo htmlspecialchars($form['title']); ?></strong>
											<p class="muted">狀態：<?php echo htmlspecialchars($form['status']); ?> ・ 建立者：<?php echo htmlspecialchars($form['username']); ?></p>
											<a class="btn btn-ghost btn-small" href="/group_41/forms/view.php?id=<?php echo (int) $form['id']; ?>">查看表單</a>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>

							<div class="panel" style="padding: 20px">
								<h3>社團表單（非公開）</h3>
								<?php if (empty($private_forms)) : ?>
									<p class="muted">尚無非公開表單。</p>
								<?php else : ?>
									<?php foreach ($private_forms as $form) : ?>
										<div style="padding: 10px 0; border-bottom: 1px solid #e4efe8">
											<strong><?php echo htmlspecialchars($form['title']); ?></strong>
											<p class="muted">狀態：<?php echo htmlspecialchars($form['status']); ?> ・ 建立者：<?php echo htmlspecialchars($form['username']); ?></p>
											<a class="btn btn-ghost btn-small" href="/group_41/forms/view.php?id=<?php echo (int) $form['id']; ?>">查看表單</a>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
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
