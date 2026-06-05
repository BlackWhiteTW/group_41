<?php
require_once __DIR__ . '/../includes/cookies.php';

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/csrf.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$user = !empty($user_raw) ? htmlspecialchars($user_raw) : null;
$current_user = null;
$errors = [];
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$selected_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$users = [];
$selected_user = null;
$clubs = [];
$memberships = [];
$membership_map = [];
$form_count = 0;
$create_form = [
	'username' => '',
	'email' => '',
	'password' => '',
	'role' => 'member',
	'club_id' => 0,
	'membership_role' => 'member'
];

$role_labels = [
	'member' => '成員',
	'owner' => '持有人',
	'club_officer' => '幹部',
	'admin' => '管理員'
];

$membership_labels = [
	'member' => '成員',
	'owner' => '社團持有人',
	'club_officer' => '社團幹部'
];

$allowed_user_roles = ['member', 'owner', 'club_officer', 'admin'];
$allowed_membership_roles = ['member', 'owner', 'club_officer'];

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
		$_SESSION['flash_error'] = '需要管理員權限才能進入使用者管理。';
		header('Location: ../index.php');
		exit();
	}

	$clubs = $pdo->query('SELECT id, name FROM clubs ORDER BY name ASC')->fetchAll();

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$posted_action = isset($_POST['action']) ? $_POST['action'] : '';
		$posted_user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
		if ($posted_action === 'create_user') {
			$create_form['username'] = isset($_POST['username']) ? trim($_POST['username']) : '';
			$create_form['email'] = isset($_POST['email']) ? trim($_POST['email']) : '';
			$create_form['password'] = isset($_POST['password']) ? (string) $_POST['password'] : '';
			$create_form['role'] = isset($_POST['role']) ? trim($_POST['role']) : 'member';
			$create_form['club_id'] = isset($_POST['club_id']) ? (int) $_POST['club_id'] : 0;
			$create_form['membership_role'] = isset($_POST['membership_role']) ? trim($_POST['membership_role']) : 'member';
		}

		if (!csrf_verify($_POST['csrf_token'] ?? '')) {
			$errors[] = '表單驗證失敗，請重新整理後再試。';
		}

		if ($posted_action !== 'create_user' && $posted_user_id <= 0) {
			$errors[] = '請選擇有效的使用者。';
		}

		$user_lookup = null;
		if (empty($errors) && $posted_action !== 'create_user') {
			$lookup_stmt = $pdo->prepare('SELECT id, username, email, role FROM users WHERE id = :id LIMIT 1');
			$lookup_stmt->execute([':id' => $posted_user_id]);
			$user_lookup = $lookup_stmt->fetch();
			if (!$user_lookup) {
				$errors[] = '找不到指定的使用者。';
			}
		}

		if (empty($errors)) {
			if ($posted_action === 'create_user') {
				$new_username = $create_form['username'];
				$new_email = $create_form['email'];
				$new_password = $create_form['password'];
				$new_role = $create_form['role'];
				$new_club_id = (int) $create_form['club_id'];
				$new_membership_role = $create_form['membership_role'];

				if ($new_username === '' || strlen($new_username) < 3) {
					$errors[] = '使用者名稱至少需要 3 個字元。';
				}
				if ($new_email === '' || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
					$errors[] = '請輸入有效的電子郵件。';
				}
				if ($new_password === '' || strlen($new_password) < 6) {
					$errors[] = '密碼至少需要 6 個字元。';
				}
				if (!in_array($new_role, $allowed_user_roles, true)) {
					$errors[] = '請選擇有效的系統角色。';
				}
				if ($new_club_id > 0 && !in_array($new_membership_role, $allowed_membership_roles, true)) {
					$errors[] = '請選擇有效的社團角色。';
				}

				if (empty($errors)) {
					$username_stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
					$username_stmt->execute([':u' => $new_username]);
					if ($username_stmt->fetch()) {
						$errors[] = '此使用者名稱已被使用。';
					}

					$email_stmt = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
					$email_stmt->execute([':e' => $new_email]);
					if ($email_stmt->fetch()) {
						$errors[] = '此電子郵件已被使用。';
					}

					if ($new_club_id > 0) {
						$club_stmt = $pdo->prepare('SELECT id FROM clubs WHERE id = :id LIMIT 1');
						$club_stmt->execute([':id' => $new_club_id]);
						if (!$club_stmt->fetch()) {
							$errors[] = '找不到指定的社團。';
						}
					}
				}

				if (empty($errors)) {
					try {
						$pdo->beginTransaction();
						$insert_stmt = $pdo->prepare('INSERT INTO users (username, password, email, role) VALUES (:username, :password, :email, :role)');
						$insert_stmt->execute([
							':username' => $new_username,
							':password' => password_hash($new_password, PASSWORD_BCRYPT),
							':email' => $new_email,
							':role' => $new_role
						]);
						$new_user_id = (int) $pdo->lastInsertId();

						if ($new_club_id > 0) {
							$membership_stmt = $pdo->prepare('INSERT INTO club_memberships (user_id, club_id, role) VALUES (:uid, :cid, :role)');
							$membership_stmt->execute([
								':uid' => $new_user_id,
								':cid' => $new_club_id,
								':role' => $new_membership_role
							]);
						}

						$pdo->commit();
						$_SESSION['flash_success'] = '使用者已建立。';
						header('Location: ./user_CRUD.php?id=' . $new_user_id);
						exit();
					} catch (Throwable $e) {
						if ($pdo->inTransaction()) {
							$pdo->rollBack();
						}
						$errors[] = '建立使用者失敗，請稍後再試。';
					}
				}
			} elseif ($posted_action === 'update_user') {
				$new_username = isset($_POST['username']) ? trim($_POST['username']) : '';
				$new_email = isset($_POST['email']) ? trim($_POST['email']) : '';
				$new_role = isset($_POST['role']) ? trim($_POST['role']) : 'member';
				$new_password = isset($_POST['new_password']) ? (string) $_POST['new_password'] : '';

				if ($new_username === '' || strlen($new_username) < 3) {
					$errors[] = '使用者名稱至少需要 3 個字元。';
				}
				if ($new_email === '' || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
					$errors[] = '請輸入有效的電子郵件。';
				}
				if (!in_array($new_role, $allowed_user_roles, true)) {
					$errors[] = '請選擇有效的系統角色。';
				}
				if ($new_password !== '' && strlen($new_password) < 6) {
					$errors[] = '新密碼至少需要 6 個字元。';
				}

				if (empty($errors)) {
					$username_stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u AND id <> :id LIMIT 1');
					$username_stmt->execute([':u' => $new_username, ':id' => $posted_user_id]);
					if ($username_stmt->fetch()) {
						$errors[] = '此使用者名稱已被使用。';
					}

					$email_stmt = $pdo->prepare('SELECT id FROM users WHERE email = :e AND id <> :id LIMIT 1');
					$email_stmt->execute([':e' => $new_email, ':id' => $posted_user_id]);
					if ($email_stmt->fetch()) {
						$errors[] = '此電子郵件已被使用。';
					}
				}

				if (empty($errors)) {
					try {
						$pdo->beginTransaction();
						$sql = 'UPDATE users SET username = :username, email = :email, role = :role';
						$params = [
							':username' => $new_username,
							':email' => $new_email,
							':role' => $new_role,
							':id' => $posted_user_id
						];
						if ($new_password !== '') {
							$sql .= ', password = :password';
							$params[':password'] = password_hash($new_password, PASSWORD_BCRYPT);
						}
						$sql .= ' WHERE id = :id';
						$update_stmt = $pdo->prepare($sql);
						$update_stmt->execute($params);

						$pdo->commit();
						$_SESSION['flash_success'] = '使用者資料已更新。';
						if ($current_user && (int) $current_user['id'] === $posted_user_id) {
							update_user_session($new_username);
						}
						header('Location: ./user_CRUD.php?id=' . $posted_user_id);
						exit();
					} catch (Throwable $e) {
						if ($pdo->inTransaction()) {
							$pdo->rollBack();
						}
						$errors[] = '更新使用者資料失敗，請稍後再試。';
					}
				}
			} elseif ($posted_action === 'delete_user') {
				if ($posted_user_id === (int) $current_user['id']) {
					$errors[] = '不能刪除目前登入中的管理員帳號。';
				}

				if (empty($errors)) {
					$target_stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = :id LIMIT 1');
					$target_stmt->execute([':id' => $posted_user_id]);
					$target_user = $target_stmt->fetch();
					if (!$target_user) {
						$errors[] = '找不到指定的使用者。';
					} else {
						if ($target_user['role'] === 'admin') {
							$admin_count_stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
							$admin_count = (int) $admin_count_stmt->fetchColumn();
							if ($admin_count <= 1) {
								$errors[] = '至少需要保留一位管理員。';
							}
						}

						if (empty($errors)) {
							$owned_clubs_stmt = $pdo->prepare('SELECT COUNT(*) FROM clubs WHERE owner_user_id = :id');
							$owned_clubs_stmt->execute([':id' => $posted_user_id]);
							$owned_clubs = (int) $owned_clubs_stmt->fetchColumn();
							if ($owned_clubs > 0) {
								$errors[] = '此使用者仍擁有社團，請先轉移或刪除社團後再刪除使用者。';
							}
						}

						if (empty($errors)) {
							try {
								$pdo->beginTransaction();
								$delete_stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
								$delete_stmt->execute([':id' => $posted_user_id]);
								$pdo->commit();
								$_SESSION['flash_success'] = '使用者已刪除。';
								header('Location: ./user_CRUD.php');
								exit();
							} catch (Throwable $e) {
								if ($pdo->inTransaction()) {
									$pdo->rollBack();
								}
								$errors[] = '刪除使用者失敗，請稍後再試。';
							}
						}
					}
				}
			} elseif ($posted_action === 'add_membership') {
				$club_id = isset($_POST['club_id']) ? (int) $_POST['club_id'] : 0;
				$membership_role = isset($_POST['membership_role']) ? trim($_POST['membership_role']) : 'member';

				if ($club_id <= 0) {
					$errors[] = '請選擇要加入的社團。';
				}
				if (!in_array($membership_role, $allowed_membership_roles, true)) {
					$errors[] = '請選擇有效的社團角色。';
				}

				if (empty($errors)) {
					$club_stmt = $pdo->prepare('SELECT id, name FROM clubs WHERE id = :id LIMIT 1');
					$club_stmt->execute([':id' => $club_id]);
					$club_row = $club_stmt->fetch();
					if (!$club_row) {
						$errors[] = '找不到指定的社團。';
					} else {
						$exists_stmt = $pdo->prepare('SELECT id FROM club_memberships WHERE user_id = :uid AND club_id = :cid LIMIT 1');
						$exists_stmt->execute([':uid' => $posted_user_id, ':cid' => $club_id]);
						if ($exists_stmt->fetch()) {
							$errors[] = '此使用者已加入該社團。';
						} else {
							$add_stmt = $pdo->prepare('INSERT INTO club_memberships (user_id, club_id, role) VALUES (:uid, :cid, :role)');
							$add_stmt->execute([
								':uid' => $posted_user_id,
								':cid' => $club_id,
								':role' => $membership_role
							]);
							$_SESSION['flash_success'] = '已新增社團關聯。';
							header('Location: ./user_CRUD.php?id=' . $posted_user_id);
							exit();
						}
					}
				}
			} elseif ($posted_action === 'update_membership') {
				$membership_id = isset($_POST['membership_id']) ? (int) $_POST['membership_id'] : 0;
				$membership_role = isset($_POST['membership_role']) ? trim($_POST['membership_role']) : 'member';

				if ($membership_id <= 0) {
					$errors[] = '請選擇有效的社團關聯。';
				}
				if (!in_array($membership_role, $allowed_membership_roles, true)) {
					$errors[] = '請選擇有效的社團角色。';
				}

				if (empty($errors)) {
					$membership_stmt = $pdo->prepare('SELECT id FROM club_memberships WHERE id = :id AND user_id = :uid LIMIT 1');
					$membership_stmt->execute([':id' => $membership_id, ':uid' => $posted_user_id]);
					if (!$membership_stmt->fetch()) {
						$errors[] = '找不到指定的社團關聯。';
					} else {
						$update_membership = $pdo->prepare('UPDATE club_memberships SET role = :role WHERE id = :id AND user_id = :uid');
						$update_membership->execute([
							':role' => $membership_role,
							':id' => $membership_id,
							':uid' => $posted_user_id
						]);
						$_SESSION['flash_success'] = '社團角色已更新。';
						header('Location: ./user_CRUD.php?id=' . $posted_user_id);
						exit();
					}
				}
			} elseif ($posted_action === 'delete_membership') {
				$membership_id = isset($_POST['membership_id']) ? (int) $_POST['membership_id'] : 0;
				if ($membership_id <= 0) {
					$errors[] = '請選擇有效的社團關聯。';
				}
				if (empty($errors)) {
					$membership_stmt = $pdo->prepare('SELECT id FROM club_memberships WHERE id = :id AND user_id = :uid LIMIT 1');
					$membership_stmt->execute([':id' => $membership_id, ':uid' => $posted_user_id]);
					if (!$membership_stmt->fetch()) {
						$errors[] = '找不到指定的社團關聯。';
					} else {
						$delete_stmt = $pdo->prepare('DELETE FROM club_memberships WHERE id = :id AND user_id = :uid');
						$delete_stmt->execute([':id' => $membership_id, ':uid' => $posted_user_id]);
						$_SESSION['flash_success'] = '社團關聯已移除。';
						header('Location: ./user_CRUD.php?id=' . $posted_user_id);
						exit();
					}
				}
			}
		}
	}

	if (!isset($_SESSION['user_crud_token'])) {
		$_SESSION['user_crud_token'] = bin2hex(random_bytes(32));
	}

	if (!empty($_SESSION['user_crud_token'])) {
		$_SESSION['user_crud_token'] = bin2hex(random_bytes(32));
	}

	$user_query = 'SELECT u.id, u.username, u.email, u.role, u.created_at, COUNT(m.id) AS club_count FROM users u LEFT JOIN club_memberships m ON m.user_id = u.id';
	$params = [];
	if ($search !== '') {
		$user_query .= ' WHERE u.username LIKE :search OR u.email LIKE :search';
		$params[':search'] = '%' . $search . '%';
	}
	$user_query .= ' GROUP BY u.id ORDER BY u.created_at DESC, u.username ASC';
	$user_stmt = $pdo->prepare($user_query);
	foreach ($params as $key => $value) {
		$user_stmt->bindValue($key, $value, PDO::PARAM_STR);
	}
	$user_stmt->execute();
	$users = $user_stmt->fetchAll();

	if (empty($users)) {
		$selected_user = null;
	} else {
		if ($selected_id <= 0) {
			$selected_id = (int) $users[0]['id'];
		}
		$allowed_ids = array_map('intval', array_column($users, 'id'));
		if (!in_array($selected_id, $allowed_ids, true)) {
			$selected_id = (int) $users[0]['id'];
		}

		$selected_stmt = $pdo->prepare('SELECT id, username, email, role, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
		$selected_stmt->execute([':id' => $selected_id]);
		$selected_user = $selected_stmt->fetch();
		if ($selected_user) {
			$membership_stmt = $pdo->prepare('SELECT m.id, m.role, c.id AS club_id, c.name AS club_name FROM club_memberships m JOIN clubs c ON c.id = m.club_id WHERE m.user_id = :id ORDER BY c.name ASC');
			$membership_stmt->execute([':id' => $selected_id]);
			$memberships = $membership_stmt->fetchAll();
		foreach ($memberships as $membership) {
			$membership_map[(int) $membership['club_id']] = true;
		}

		$form_count_stmt = $pdo->prepare('SELECT COUNT(*) FROM forms WHERE creator_id = :id');
		$form_count_stmt->execute([':id' => $selected_id]);
		$form_count = (int) $form_count_stmt->fetchColumn();
	}
	}
} catch (Throwable $e) {
	$errors[] = '使用者管理頁載入失敗，請稍後再試。';
}
?>
<!doctype html>
<html lang="zh-Hant">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>使用者管理 | 社團表單系統</title>
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
				<h1>使用者管理</h1>
				<p class="muted">管理使用者名稱、密碼、電子郵件、系統角色與社團關聯。</p>

				<div style="display: flex; gap: 10px; flex-wrap: wrap; margin: 16px 0 20px">
					<a class="btn btn-ghost" href="./admin_index.php">回管理控制台</a>
					<a class="btn btn-ghost" href="./sql_view.php">SQL 檢視</a>
				</div>

				<?php if (!empty($errors)) : ?>
					<div class="error">
						<ul>
							<?php foreach ($errors as $error) : ?>
								<li><?php echo htmlspecialchars($error); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<div class="panel" style="padding: 20px; margin-bottom: 20px">
					<h2 style="margin-top: 0">建立新使用者</h2>
					<p class="muted">可同時建立帳號、設定系統角色，並選擇是否先加入社團。</p>
					<form method="post" action="./user_CRUD.php<?php echo $search !== '' ? '?q=' . urlencode($search) : ''; ?>">
						<?php echo csrf_field(); ?>
						<input type="hidden" name="action" value="create_user" />
						<div style="display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))">
							<div class="field">
								<label for="create_username">名稱</label>
								<input id="create_username" name="username" required minlength="3" value="<?php echo htmlspecialchars($create_form['username']); ?>" />
							</div>
							<div class="field">
								<label for="create_email">電子郵件</label>
								<input id="create_email" name="email" type="email" required value="<?php echo htmlspecialchars($create_form['email']); ?>" />
							</div>
							<div class="field">
								<label for="create_password">密碼</label>
								<input id="create_password" name="password" type="password" required minlength="6" placeholder="至少 6 個字元" />
							</div>
							<div class="field">
								<label for="create_role">系統角色</label>
								<select id="create_role" name="role">
									<?php foreach ($role_labels as $role_key => $label) : ?>
										<option value="<?php echo htmlspecialchars($role_key); ?>" <?php echo $create_form['role'] === $role_key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<div style="display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-top: 14px; align-items: end">
							<div class="field">
								<label for="create_club_id">初始社團（可選）</label>
								<select id="create_club_id" name="club_id">
									<option value="0">不加入社團</option>
									<?php foreach ($clubs as $club_item) : ?>
										<option value="<?php echo (int) $club_item['id']; ?>" <?php echo ((int) $create_form['club_id'] === (int) $club_item['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($club_item['name']); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="field">
								<label for="create_membership_role">社團角色</label>
								<select id="create_membership_role" name="membership_role">
									<?php foreach ($membership_labels as $role_key => $label) : ?>
										<option value="<?php echo htmlspecialchars($role_key); ?>" <?php echo $create_form['membership_role'] === $role_key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div>
								<button class="btn btn-primary" type="submit">建立使用者</button>
							</div>
						</div>
					</form>
				</div>

				<div style="display: grid; gap: 20px; grid-template-columns: minmax(260px, 320px) minmax(0, 1fr)">
					<aside class="panel" style="padding: 16px">
						<h2 style="margin-top: 0">使用者清單</h2>
						<form method="get" action="./user_CRUD.php" style="margin-bottom: 14px">
							<div class="field">
								<label for="q">搜尋</label>
								<input id="q" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="使用者名稱或信箱" />
							</div>
							<button class="btn btn-ghost" type="submit">查詢</button>
						</form>

						<div style="display: grid; gap: 10px; max-height: 760px; overflow: auto">
							<?php foreach ($users as $item) : ?>
								<?php $active = ((int) $item['id'] === $selected_id); ?>
								<a href="./user_CRUD.php?id=<?php echo (int) $item['id']; ?><?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>" class="panel" style="padding: 12px; border-color: <?php echo $active ? '#8bc9b4' : '#e0e9e3'; ?>; background: <?php echo $active ? '#eef7f3' : 'rgba(255,255,255,0.95)'; ?>">
									<strong><?php echo htmlspecialchars($item['username']); ?></strong>
									<p class="muted" style="margin-top: 4px"><?php echo htmlspecialchars($item['email']); ?></p>
									<p class="meta"><?php echo htmlspecialchars(isset($role_labels[$item['role']]) ? $role_labels[$item['role']] : $item['role']); ?> ・ 社團數：<?php echo number_format((int) $item['club_count']); ?></p>
								</a>
							<?php endforeach; ?>
						</div>
					</aside>

					<section style="display: grid; gap: 20px">
						<?php if (!$selected_user) : ?>
							<div class="panel" style="padding: 20px">
								<p class="muted">請先從左側選擇一位使用者。</p>
							</div>
						<?php else : ?>
							<div class="panel" style="padding: 20px">
								<h2 style="margin-top: 0">編輯使用者資料</h2>
								<p class="muted">使用者 ID：<?php echo (int) $selected_user['id']; ?> ・ 建立日：<?php echo !empty($selected_user['created_at']) ? htmlspecialchars(date('Y-m-d', strtotime($selected_user['created_at']))) : ''; ?></p>
								<form method="post" action="./user_CRUD.php<?php echo $search !== '' ? '?q=' . urlencode($search) . '&id=' . (int) $selected_user['id'] : '?id=' . (int) $selected_user['id']; ?>">
									<?php echo csrf_field(); ?>
									<input type="hidden" name="user_id" value="<?php echo (int) $selected_user['id']; ?>" />
									<input type="hidden" name="action" value="update_user" />

									<div style="display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))">
										<div class="field">
											<label for="username">名稱</label>
											<input id="username" name="username" required minlength="3" value="<?php echo htmlspecialchars($selected_user['username']); ?>" />
										</div>
										<div class="field">
											<label for="email">電子郵件</label>
											<input id="email" name="email" type="email" required value="<?php echo htmlspecialchars($selected_user['email']); ?>" />
										</div>
										<div class="field">
											<label for="role">系統角色</label>
											<select id="role" name="role">
												<?php foreach ($role_labels as $role_key => $label) : ?>
													<option value="<?php echo htmlspecialchars($role_key); ?>" <?php echo $selected_user['role'] === $role_key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
										<div class="field">
											<label for="new_password">新密碼</label>
											<input id="new_password" name="new_password" type="password" minlength="6" placeholder="留空則不變更" />
										</div>
									</div>

									<div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 8px">
										<button class="btn btn-primary" type="submit">儲存使用者資料</button>
									</div>
								</form>
								<form method="post" action="./user_CRUD.php<?php echo $search !== '' ? '?q=' . urlencode($search) . '&id=' . (int) $selected_user['id'] : '?id=' . (int) $selected_user['id']; ?>" style="margin-top: 12px">
									<?php echo csrf_field(); ?>
									<input type="hidden" name="user_id" value="<?php echo (int) $selected_user['id']; ?>" />
									<input type="hidden" name="action" value="delete_user" />
									<?php if ($form_count > 0) : ?>
										<p class="muted" style="color: #b33">⚠ 此使用者建立了 <?php echo number_format($form_count); ?> 份表單，刪除使用者將一併級聯刪除所有相關表單資料。</p>
									<?php endif; ?>
									<button class="btn btn-ghost" type="submit" onclick="return confirm('<?php echo $form_count > 0 ? '此使用者有 ' . number_format($form_count) . ' 份表單，刪除後表單也會被一併刪除。\\n\\n' : ''; ?>確定要刪除這個使用者嗎？此動作無法復原。');">刪除使用者</button>
								</form>
							</div>

							<div class="panel" style="padding: 20px">
								<div style="display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: center">
									<h2 style="margin-top: 0">社團關聯</h2>
									<p class="muted">可新增、調整或移除這位使用者的社團關聯。</p>
								</div>

								<div style="display: grid; gap: 12px; margin-top: 12px">
									<?php if (empty($memberships)) : ?>
										<p class="muted">尚未加入任何社團。</p>
									<?php else : ?>
										<?php foreach ($memberships as $membership) : ?>
											<div class="panel" style="padding: 14px; background: rgba(255,255,255,0.95)">
												<form method="post" action="./user_CRUD.php<?php echo $search !== '' ? '?q=' . urlencode($search) . '&id=' . (int) $selected_user['id'] : '?id=' . (int) $selected_user['id']; ?>" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: end">
													<?php echo csrf_field(); ?>
													<input type="hidden" name="user_id" value="<?php echo (int) $selected_user['id']; ?>" />
													<input type="hidden" name="membership_id" value="<?php echo (int) $membership['id']; ?>" />
													<div style="min-width: 220px">
														<strong><?php echo htmlspecialchars($membership['club_name']); ?></strong>
														<p class="muted" style="margin-top: 4px">目前角色：<?php echo htmlspecialchars(isset($membership_labels[$membership['role']]) ? $membership_labels[$membership['role']] : $membership['role']); ?></p>
													</div>
													<div class="field" style="min-width: 180px; margin: 0">
														<label>關聯角色</label>
														<select name="membership_role">
															<?php foreach ($membership_labels as $role_key => $label) : ?>
																<option value="<?php echo htmlspecialchars($role_key); ?>" <?php echo $membership['role'] === $role_key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
															<?php endforeach; ?>
														</select>
													</div>
													<div style="display: flex; gap: 8px; flex-wrap: wrap">
														<button class="btn btn-primary" type="submit" name="action" value="update_membership">更新</button>
														<button class="btn btn-ghost" type="submit" name="action" value="delete_membership" onclick="return confirm('確定要移除此社團關聯嗎？');">移除</button>
													</div>
												</form>
											</div>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>

								<div style="margin-top: 18px; border-top: 1px solid #e4efe8; padding-top: 18px">
									<h3 style="margin-top: 0">新增社團關聯</h3>
									<form method="post" action="./user_CRUD.php<?php echo $search !== '' ? '?q=' . urlencode($search) . '&id=' . (int) $selected_user['id'] : '?id=' . (int) $selected_user['id']; ?>" style="display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); align-items: end">
										<?php echo csrf_field(); ?>
										<input type="hidden" name="user_id" value="<?php echo (int) $selected_user['id']; ?>" />
										<input type="hidden" name="action" value="add_membership" />
										<div class="field">
											<label for="club_id">社團</label>
											<select id="club_id" name="club_id" required>
												<option value="">請選擇社團</option>
												<?php foreach ($clubs as $club_item) : ?>
													<?php if (isset($membership_map[(int) $club_item['id']])) { continue; } ?>
													<option value="<?php echo (int) $club_item['id']; ?>"><?php echo htmlspecialchars($club_item['name']); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
										<div class="field">
											<label for="membership_role_new">社團角色</label>
											<select id="membership_role_new" name="membership_role">
												<?php foreach ($membership_labels as $role_key => $label) : ?>
													<option value="<?php echo htmlspecialchars($role_key); ?>"><?php echo htmlspecialchars($label); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
										<div>
											<button class="btn btn-primary" type="submit">新增關聯</button>
										</div>
									</form>
								</div>
							</div>
						<?php endif; ?>
					</section>
				</div>
			</div>
		</main>

		<footer class="footer container">社團表單系統</footer>
		<script src="../js/app.js"></script>
	</body>
</html>

<?php
exit();
