<?php
require __DIR__ . '/../includes/admin_auth.php';
require __DIR__ . '/../includes/csrf.php';

$errors = [];
$success = '';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$selected_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$clubs_list = [];
$selected_club = null;
$selected_members = [];
$selected_owner = null;
$available_owners = [];

$join_labels = ['open' => '開放加入', 'request' => '需申請審核', 'invite_only' => '僅限邀請'];
$visibility_labels = ['public' => '公開', 'private' => '隱藏'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $errors[] = '表單驗證失敗，請重新整理後再試。';
        } else {
            $action = $_POST['action'] ?? '';
            $club_id = isset($_POST['club_id']) ? (int) $_POST['club_id'] : 0;

            if ($action === 'update_club') {
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $join_mode = $_POST['join_mode'] ?? 'request';
                $visibility = $_POST['visibility'] ?? 'public';

                if ($name === '' || strlen($name) < 2) {
                    $errors[] = '社團名稱至少需要 2 個字元。';
                }
                if (!in_array($join_mode, ['open', 'request', 'invite_only'], true)) {
                    $errors[] = '請選擇有效的加入模式。';
                }
                if (!in_array($visibility, ['public', 'private'], true)) {
                    $errors[] = '請選擇有效的可見性。';
                }

                if (empty($errors)) {
                    $dup = $pdo->prepare('SELECT id FROM clubs WHERE name = :n AND id <> :id LIMIT 1');
                    $dup->execute([':n' => $name, ':id' => $club_id]);
                    if ($dup->fetch()) {
                        $errors[] = '此社團名稱已被使用。';
                    }
                }

                if (empty($errors)) {
                    $upd = $pdo->prepare('UPDATE clubs SET name = :n, description = :d, join_mode = :j, visibility = :v WHERE id = :id');
                    $upd->execute([':n' => $name, ':d' => $description, ':j' => $join_mode, ':v' => $visibility, ':id' => $club_id]);
                    $_SESSION['flash_success'] = '社團資料已更新。';
                    header('Location: ./clubs_CRUD.php?id=' . $club_id . ($search !== '' ? '&q=' . urlencode($search) : ''));
                    exit();
                }
            } elseif ($action === 'transfer_owner') {
                $new_owner_id = isset($_POST['new_owner_id']) ? (int) $_POST['new_owner_id'] : 0;
                if ($new_owner_id <= 0) {
                    $errors[] = '請選擇新任社團持有人。';
                } else {
                    $check = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
                    $check->execute([':id' => $new_owner_id]);
                    if (!$check->fetch()) {
                        $errors[] = '找不到指定的使用者。';
                    } else {
                        try {
                            $pdo->beginTransaction();
                            $pdo->prepare('UPDATE clubs SET owner_user_id = :oid WHERE id = :id')->execute([':oid' => $new_owner_id, ':id' => $club_id]);

                            $member = $pdo->prepare('SELECT id, role FROM club_memberships WHERE user_id = :uid AND club_id = :cid LIMIT 1');
                            $member->execute([':uid' => $new_owner_id, ':cid' => $club_id]);
                            $existing = $member->fetch();

                            if ($existing) {
                                $pdo->prepare('UPDATE club_memberships SET role = :r WHERE id = :mid')->execute([':r' => 'club_officer', ':mid' => $existing['id']]);
                            } else {
                                $pdo->prepare('INSERT INTO club_memberships (user_id, club_id, role) VALUES (:uid, :cid, :r)')->execute([':uid' => $new_owner_id, ':cid' => $club_id, ':r' => 'club_officer']);
                            }

                            $pdo->commit();
                            $_SESSION['flash_success'] = '社團持有人已轉移。';
                            header('Location: ./clubs_CRUD.php?id=' . $club_id . ($search !== '' ? '&q=' . urlencode($search) : ''));
                            exit();
                        } catch (Throwable $e) {
                            if ($pdo->inTransaction()) $pdo->rollBack();
                            $errors[] = '轉移失敗，請稍後再試。';
                        }
                    }
                }
            } elseif ($action === 'delete_club') {
                $check = $pdo->prepare('SELECT id, name FROM clubs WHERE id = :id LIMIT 1');
                $check->execute([':id' => $club_id]);
                $club = $check->fetch();
                if (!$club) {
                    $errors[] = '找不到指定的社團。';
                } else {
                    try {
                    $pdo->prepare('DELETE FROM clubs WHERE id = :id')->execute([':id' => $club_id]);
                    $_SESSION['flash_success'] = '社團「' . $club['name'] . '」已刪除。';
                    header('Location: ./clubs_CRUD.php' . ($search !== '' ? '?q=' . urlencode($search) : ''));
                    exit();
                    } catch (Throwable $e) {
                        $errors[] = '刪除失敗，請稍後再試。';
                    }
                }
            }
        }
    }

    $query = 'SELECT c.*, u.username AS owner_name, (SELECT COUNT(*) FROM club_memberships WHERE club_id = c.id) AS member_count, (SELECT COUNT(*) FROM forms WHERE club_id = c.id) AS form_count FROM clubs c JOIN users u ON u.id = c.owner_user_id';
    $params = [];
    if ($search !== '') {
        $query .= ' WHERE c.name LIKE :search OR c.description LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }
    $query .= ' ORDER BY c.created_at DESC, c.name ASC';
    $stmt = $pdo->prepare($query);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $clubs_list = $stmt->fetchAll();

    if (!empty($clubs_list)) {
        if ($selected_id <= 0) {
            $selected_id = (int) $clubs_list[0]['id'];
        }
        $ids = array_map('intval', array_column($clubs_list, 'id'));
        if (!in_array($selected_id, $ids, true)) {
            $selected_id = (int) $clubs_list[0]['id'];
        }

        $sel = $pdo->prepare('SELECT c.*, u.username AS owner_name FROM clubs c JOIN users u ON u.id = c.owner_user_id WHERE c.id = :id LIMIT 1');
        $sel->execute([':id' => $selected_id]);
        $selected_club = $sel->fetch();

        if ($selected_club) {
            $mem = $pdo->prepare('SELECT m.*, u.username FROM club_memberships m JOIN users u ON u.id = m.user_id WHERE m.club_id = :cid ORDER BY u.username ASC');
            $mem->execute([':cid' => $selected_id]);
            $selected_members = $mem->fetchAll();

            $owners = $pdo->query('SELECT id, username FROM users ORDER BY username ASC')->fetchAll();
            $available_owners = $owners;
        }
    }
} catch (Throwable $e) {
    $errors[] = '社團管理頁載入失敗，請稍後再試。';
}
?>
<!doctype html>
<html lang="zh-Hant">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>社團管理 | 社團表單系統</title>
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
                <h1>社團管理</h1>
                <p class="muted">管理所有社團資料、持有人轉移與刪除。</p>

                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin: 16px 0 20px">
                    <a class="btn btn-ghost" href="./admin_index.php">回管理控制台</a>
                    <a class="btn btn-ghost" href="./user_CRUD.php">使用者管理</a>
                    <a class="btn btn-ghost" href="./forms_CRUD.php">表單管理</a>
                </div>

                <?php if (!empty($errors)) : ?>
                    <div class="error"><ul><?php foreach ($errors as $e) : ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <?php if ($success !== '') : ?>
                    <div class="panel" style="padding: 12px; margin-bottom: 16px; background: #e4f4eb; border-color: #8bc9b4; color: #085944;"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <div class="panel" style="padding: 16px; margin-bottom: 20px">
                    <form method="get" action="./clubs_CRUD.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: end">
                        <div class="field" style="min-width: 220px">
                            <label for="q">搜尋社團</label>
                            <input id="q" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="社團名稱或簡介關鍵字" />
                        </div>
                        <div><button class="btn btn-primary" type="submit">篩選</button><a class="btn btn-ghost" href="./clubs_CRUD.php">清除</a></div>
                    </form>
                </div>

                <div style="display: grid; gap: 20px; grid-template-columns: minmax(260px, 320px) minmax(0, 1fr)">
                    <aside class="panel" style="padding: 16px">
                        <h2 style="margin-top: 0">社團清單</h2>
                        <div style="display: grid; gap: 10px; max-height: 760px; overflow: auto">
                            <?php foreach ($clubs_list as $item) : ?>
                                <?php $active = ((int) $item['id'] === $selected_id); ?>
                                <a href="./clubs_CRUD.php?id=<?php echo (int) $item['id']; ?><?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>" class="panel" style="padding: 12px; border-color: <?php echo $active ? '#8bc9b4' : '#e0e9e3'; ?>; background: <?php echo $active ? '#eef7f3' : 'rgba(255,255,255,0.95)'; ?>">
                                    <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                    <p class="muted" style="margin-top: 4px">持有人：<?php echo htmlspecialchars($item['owner_name']); ?></p>
                                    <p class="meta"><?php echo htmlspecialchars($visibility_labels[$item['visibility']] ?? $item['visibility']); ?> ・ 成員：<?php echo number_format((int) $item['member_count']); ?> ・ 表單：<?php echo number_format((int) $item['form_count']); ?></p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </aside>

                    <section style="display: grid; gap: 20px">
                        <?php if (!$selected_club) : ?>
                            <div class="panel" style="padding: 20px"><p class="muted">請先從左側選擇一個社團。</p></div>
                        <?php else : ?>
                            <div class="panel" style="padding: 20px">
                                <h2 style="margin-top: 0">編輯社團資料</h2>
                                <p class="muted">社團 ID：<?php echo (int) $selected_club['id']; ?> ・ 建立日：<?php echo !empty($selected_club['created_at']) ? htmlspecialchars(date('Y-m-d', strtotime($selected_club['created_at']))) : ''; ?></p>
                                <form method="post" action="./clubs_CRUD.php<?php echo ($search !== '' ? '?q=' . urlencode($search) . '&id=' . (int) $selected_club['id'] : '?id=' . (int) $selected_club['id']); ?>">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="update_club" />
                                    <input type="hidden" name="club_id" value="<?php echo (int) $selected_club['id']; ?>" />
                                    <div style="display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))">
                                        <div class="field">
                                            <label for="name">社團名稱</label>
                                            <input id="name" name="name" required minlength="2" value="<?php echo htmlspecialchars($selected_club['name']); ?>" />
                                        </div>
                                        <div class="field">
                                            <label for="join_mode">加入模式</label>
                                            <select id="join_mode" name="join_mode">
                                                <?php foreach ($join_labels as $k => $v) : ?>
                                                    <option value="<?php echo $k; ?>" <?php echo $selected_club['join_mode'] === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($v); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label for="visibility">可見性</label>
                                            <select id="visibility" name="visibility">
                                                <?php foreach ($visibility_labels as $k => $v) : ?>
                                                    <option value="<?php echo $k; ?>" <?php echo $selected_club['visibility'] === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($v); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="field" style="margin-top: 14px">
                                        <label for="description">簡介</label>
                                        <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($selected_club['description'] ?? ''); ?></textarea>
                                    </div>
                                    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 8px">
                                        <button class="btn btn-primary" type="submit">儲存社團資料</button>
                                    </div>
                                </form>

                                <form method="post" action="./clubs_CRUD.php<?php echo ($search !== '' ? '?q=' . urlencode($search) . '&id=' . (int) $selected_club['id'] : '?id=' . (int) $selected_club['id']); ?>" style="margin-top: 16px">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="transfer_owner" />
                                    <input type="hidden" name="club_id" value="<?php echo (int) $selected_club['id']; ?>" />
                                    <h3 style="margin-top: 0">轉移社團持有人</h3>
                                    <p class="muted">目前持有人：<?php echo htmlspecialchars($selected_club['owner_name']); ?></p>
                                    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: end; margin-top: 8px">
                                        <div class="field" style="min-width: 220px">
                                            <label for="new_owner_id">新任持有人</label>
                                            <select id="new_owner_id" name="new_owner_id" required>
                                                <option value="">請選擇使用者</option>
                                                <?php foreach ($available_owners as $u) : ?>
                                                    <?php if ((int) $u['id'] === (int) $selected_club['owner_user_id']) continue; ?>
                                                    <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button class="btn btn-ghost" type="submit" onclick="return confirm('確定要轉移社團持有人嗎？');">轉移</button>
                                    </div>
                                </form>

                                <form method="post" action="./clubs_CRUD.php<?php echo $search !== '' ? '?q=' . urlencode($search) : ''; ?>" style="margin-top: 16px">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_club" />
                                    <input type="hidden" name="club_id" value="<?php echo (int) $selected_club['id']; ?>" />
                                    <button class="btn btn-ghost" type="submit" data-confirm="確定要刪除社團「<?php echo htmlspecialchars($selected_club['name']); ?>」嗎？所有相關成員、表單與活動記錄將一併刪除，此動作無法復原。" style="color: #b33">刪除社團</button>
                                </form>
                            </div>

                            <div class="panel" style="padding: 20px">
                                <h2 style="margin-top: 0">目前成員 (<?php echo count($selected_members); ?>)</h2>
                                <?php if (empty($selected_members)) : ?>
                                    <p class="muted">尚無成員。</p>
                                <?php else : ?>
                                    <div style="display: grid; gap: 8px; margin-top: 12px">
                                        <?php foreach ($selected_members as $m) : ?>
                                            <div style="padding: 8px 12px; border: 1px solid #e4efe8; border-radius: 8px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
                                                <strong><?php echo htmlspecialchars($m['username']); ?></strong>
                                                <span class="badge"><?php echo htmlspecialchars($m['role']); ?></span>
                                                <?php if ((int) $m['user_id'] === (int) $selected_club['owner_user_id']) : ?>
                                                    <span class="badge" style="background: #e4f4eb; color: #085944">持有人</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
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
<?php exit(); ?>
