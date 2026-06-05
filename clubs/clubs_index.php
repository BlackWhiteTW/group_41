<?php
session_start();

require __DIR__ . '/../includes/db.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$user = !empty($user_raw) ? htmlspecialchars($user_raw) : null;
$current_user = null;
$is_admin = false;
$errors = [];
$clubs = [];
$all_clubs = [];
$my_club_ids = [];
$my_invitations = [];

$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;

try {
    $pdo = get_db();
    if ($user_raw) {
        $user_stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
        $user_stmt->execute([':u' => $user_raw]);
        $current_user = $user_stmt->fetch();
        if ($current_user && $current_user['role'] === 'admin') {
            $is_admin = true;
        }
    }

    if ($current_user) {
        $mid_stmt = $pdo->prepare('SELECT club_id FROM club_memberships WHERE user_id = :id');
        $mid_stmt->execute([':id' => $current_user['id']]);
        $my_club_ids = array_map('intval', array_column($mid_stmt->fetchAll(), 'club_id'));

        $inv_stmt = $pdo->prepare('SELECT ci.id, ci.club_id, ci.status, c.name AS club_name FROM club_invitations ci JOIN clubs c ON c.id = ci.club_id WHERE ci.user_id = :id AND ci.status = "pending"');
        $inv_stmt->execute([':id' => $current_user['id']]);
        $my_invitations = $inv_stmt->fetchAll();
    }

    $where = '';
    $params = [];

    if (!$is_admin && !$current_user) {
        $where = 'WHERE c.visibility = "public"';
    } elseif (!$is_admin) {
        if (!empty($my_club_ids)) {
            $placeholders = implode(',', array_fill(0, count($my_club_ids), '?'));
            $where = 'WHERE (c.visibility = "public" OR c.id IN (' . $placeholders . '))';
            $params = $my_club_ids;
        } else {
            $where = 'WHERE c.visibility = "public"';
        }
    }

    if ($search !== '') {
        $search_clause = 'c.name LIKE ?';
        $params[] = '%' . $search . '%';
        $where = ($where === '') ? 'WHERE ' . $search_clause : $where . ' AND ' . $search_clause;
    }

    $count_sql = 'SELECT COUNT(*) FROM clubs c JOIN users u ON u.id = c.owner_user_id ' . $where;
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total = (int)$count_stmt->fetchColumn();
    $total_pages = max(1, (int)ceil($total / $per_page));
    if ($page > $total_pages) $page = $total_pages;
    $offset = ($page - 1) * $per_page;

    $list_sql = 'SELECT c.id, c.name, c.description, c.join_mode, c.visibility, u.username AS owner_name, c.created_at FROM clubs c JOIN users u ON u.id = c.owner_user_id ' . $where . ' ORDER BY c.name ASC LIMIT ' . $per_page . ' OFFSET ' . $offset;
    $list_stmt = $pdo->prepare($list_sql);
    $list_stmt->execute($params);
    $all_clubs = $list_stmt->fetchAll();

    $clubs = $all_clubs;
} catch (Throwable $e) {
    $errors[] = '社團中心載入失敗，請稍後再試。';
}

$join_mode_labels = [
    'open' => '開放加入',
    'request' => '申請加入',
    'invite_only' => '僅限邀請',
];
$visibility_labels = [
    'public' => '公開',
    'private' => '私人',
];
?>
<!doctype html>
<html lang="zh-Hant">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>社團中心 | 社團表單系統</title>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;600;700&display=swap" rel="stylesheet" />
        <link rel="stylesheet" href="../css/app.css" />
    </head>
    <body>
        <?php $base_url = '../'; require __DIR__ . '/../includes/header.php'; ?>

        <main class="section">
            <div class="container" style="display: grid; gap: 20px; grid-template-columns: minmax(0, 1fr); align-items: start">
            <?php require __DIR__ . '/../includes/right.php'; ?>

                <div>
                <h1>社團中心</h1>
                <p class="muted">進入社團管理、成員檢視與表單關聯功能。</p>

                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin: 16px 0 20px">
                    <a class="btn btn-primary" href="./manage.php">查看社團資訊</a>
                    <a class="btn btn-ghost" href="./create.php">建立社團</a>
                </div>

                <?php if (!empty($errors)) : ?>
                    <div class="error"><?php echo htmlspecialchars(implode(' ', $errors)); ?></div>
                <?php endif; ?>

                <?php if ($user && !empty($my_invitations)) : ?>
                <div class="panel" style="padding:16px;margin-bottom:16px;border-color:#8bc9b4;background:#eef7f3">
                    <p><strong>你有待處理的社團邀請：</strong></p>
                    <?php foreach ($my_invitations as $inv): ?>
                    <div style="display:flex;align-items:center;gap:8px;margin-top:6px">
                        <span><?php echo htmlspecialchars($inv['club_name']); ?></span>
                        <form method="POST" action="update_setting.php" style="display:inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="club_id" value="<?php echo $inv['club_id']; ?>">
                            <input type="hidden" name="invitation_id" value="<?php echo $inv['id']; ?>">
                            <input type="hidden" name="action" value="accept_invitation">
                            <button type="submit" class="btn btn-small btn-primary">接受</button>
                        </form>
                        <form method="POST" action="update_setting.php" style="display:inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="club_id" value="<?php echo $inv['club_id']; ?>">
                            <input type="hidden" name="invitation_id" value="<?php echo $inv['id']; ?>">
                            <input type="hidden" name="action" value="decline_invitation">
                            <button type="submit" class="btn btn-small btn-ghost">拒絕</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- 搜尋列 -->
                <div class="panel" style="padding: 14px 20px; margin-bottom: 20px">
                    <form method="GET" action="clubs_index.php" style="display:flex;gap:8px;align-items:end">
                        <div class="field" style="margin-bottom:0;flex:1">
                            <label for="club_search">搜尋社團</label>
                            <input id="club_search" name="search" type="text" placeholder="輸入社團名稱..." value="<?php echo htmlspecialchars($search); ?>" />
                        </div>
                        <button type="submit" class="btn btn-primary btn-small">搜尋</button>
                        <?php if ($search !== ''): ?>
                            <a href="clubs_index.php" class="btn btn-ghost btn-small">清除</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="panel" style="padding: 20px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:16px">
                        <h2 style="margin:0">社團清單</h2>
                        <span class="meta" style="margin-top:0">共 <?php echo number_format($total); ?> 個社團</span>
                    </div>
                    <?php if (empty($all_clubs)) : ?>
                        <p class="muted">
                            <?php if ($search !== ''): ?>
                                找不到符合「<?php echo htmlspecialchars($search); ?>」的社團。
                            <?php else: ?>
                                目前尚無社團。
                            <?php endif; ?>
                        </p>
                    <?php else : ?>
                        <div class="card-grid">
                            <?php foreach ($all_clubs as $club) : ?>
                                <?php $is_member = in_array((int)$club['id'], $my_club_ids, true); ?>
                                <article class="panel" style="padding: 16px">
                                    <h3><?php echo htmlspecialchars($club['name']); ?></h3>
                                    <?php if (!empty($club['description'])): ?>
                                        <p class="muted" style="font-size:0.88rem;margin:4px 0"><?php echo htmlspecialchars(mb_substr($club['description'], 0, 60)) . (mb_strlen($club['description']) > 60 ? '...' : ''); ?></p>
                                    <?php endif; ?>
                                    <p class="muted">擁有人：<?php echo htmlspecialchars($club['owner_name']); ?></p>
                                    <p class="meta">建立日：<?php echo !empty($club['created_at']) ? htmlspecialchars(date('Y-m-d', strtotime($club['created_at']))) : ''; ?></p>
                                    <div style="margin-top:6px;display:flex;gap:4px;flex-wrap:wrap">
                                        <span class="pill"><?php echo htmlspecialchars($join_mode_labels[$club['join_mode']] ?? '申請加入'); ?></span>
                                        <?php if (($club['visibility'] ?? 'public') === 'private'): ?>
                                            <span class="pill" style="background:#f3e8fa;color:#7a3b9e">私人</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
                                    <?php if ($is_member): ?>
                                        <a class="btn btn-ghost" href="./manage.php?id=<?php echo (int) $club['id']; ?>">查看社團</a>
                                    <?php elseif ($user && ($club['join_mode'] ?? 'request') === 'invite_only'): ?>
                                        <span class="muted" style="font-size:0.85rem">僅限邀請加入</span>
                                    <?php elseif ($user): ?>
                                        <form method="POST" action="update_setting.php" style="display:inline">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="club_id" value="<?php echo (int) $club['id']; ?>">
                                            <input type="hidden" name="action" value="request_join">
                                            <button type="submit" class="btn btn-primary btn-small">
                                                <?php echo ($club['join_mode'] ?? 'request') === 'open' ? '立即加入' : '申請加入'; ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($total_pages > 1): ?>
                        <div style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:20px">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-ghost btn-small" href="?page=<?php echo $page - 1; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>">上一頁</a>
                            <?php endif; ?>
                            <span class="meta" style="margin-top:0">第 <?php echo $page; ?> / <?php echo $total_pages; ?> 頁</span>
                            <?php if ($page < $total_pages): ?>
                                <a class="btn btn-ghost btn-small" href="?page=<?php echo $page + 1; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>">下一頁</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <footer class="footer container">社團表單系統</footer>
        <script src="../js/app.js"></script>
    </body>
</html>

<?php
exit();
