<?php
// SQL 資料檢視頁面（管理區）：提供管理員查看資料庫內各資料表內容的介面
session_start();
require __DIR__ . '/../includes/db.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$user = !empty($user_raw) ? htmlspecialchars($user_raw) : null;
$current_user = null;
$errors = [];

if (empty($user_raw)) {
  header('Location: /group_41/login.php');
  exit();
}

$allowed_tables = [
  'users',
  'clubs',
  'club_memberships',
  'forms',
  'form_questions',
  'question_options',
  'form_submissions',
  'answers'
];

$selected = isset($_GET['table']) ? $_GET['table'] : $allowed_tables[0];
if (!in_array($selected, $allowed_tables, true)) {
  $selected = $allowed_tables[0];
}

$counts = [];
$columns = [];
$rows = [];
$total_rows = 0;

try {
  $pdo = get_db();
  if ($user_raw) {
    $u = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
    $u->execute([':u' => $user_raw]);
    $current_user = $u->fetch();
  }

  if (!$current_user || $current_user['role'] !== 'admin') {
    $_SESSION['flash_error'] = '需要管理員權限才能瀏覽資料表。';
    header('Location: /group_41/index.php');
    exit();
  }

  foreach ($allowed_tables as $table) {
    $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
  }

  $desc = $pdo->query('DESCRIBE `' . $selected . '`')->fetchAll();
  foreach ($desc as $col) {
    $columns[] = $col['Field'];
  }

  $total_rows = isset($counts[$selected]) ? $counts[$selected] : 0;
  $order_sql = in_array('id', $columns, true) ? ' ORDER BY `id` DESC' : '';
  $stmt = $pdo->query('SELECT * FROM `' . $selected . '`' . $order_sql . ' LIMIT 200');
  $rows = $stmt->fetchAll();
} catch (Throwable $e) {
  $errors[] = '資料庫讀取失敗，請稍後再試。';
}
?>
<!doctype html>
<html lang="zh-Hant">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>SQL 資料檢視 | 管理</title>
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
        <h1>SQL 資料檢視 (管理區)</h1>
        <p class="muted">顯示資料庫內的各資料表內容（最多 200 筆）。</p>

        <?php if (!empty($errors)) : ?>
          <div class="error">
            <ul>
              <?php foreach ($errors as $e) : ?>
                <li><?php echo htmlspecialchars($e); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php else : ?>
          <div class="panel" style="padding: 20px; margin-bottom: 16px">
            <form method="get" action="/group_41/admin/sql_view.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center">
              <label for="table">選擇資料表</label>
              <select id="table" name="table">
                <?php foreach ($allowed_tables as $table) : ?>
                  <option value="<?php echo htmlspecialchars($table); ?>" <?php echo $table === $selected ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($table); ?> (<?php echo number_format($counts[$table] ?? 0); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-primary" type="submit">切換</button>
              <span class="muted">共 <?php echo number_format($total_rows); ?> 筆，顯示前 200 筆</span>
            </form>
            <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap">
              <?php foreach ($allowed_tables as $table) : ?>
                <a class="btn btn-ghost btn-small" href="/group_41/admin/sql_view.php?table=<?php echo urlencode($table); ?>">
                  <?php echo htmlspecialchars($table); ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="panel" style="padding: 20px; overflow: auto">
            <?php if (empty($columns)) : ?>
              <p class="muted">找不到資料欄位。</p>
            <?php elseif (empty($rows)) : ?>
              <p class="muted">目前沒有資料。</p>
            <?php else : ?>
              <table style="width: 100%; border-collapse: collapse">
                <thead>
                  <tr>
                    <?php foreach ($columns as $col) : ?>
                      <th style="text-align: left; padding: 8px; border-bottom: 1px solid #e4efe8; white-space: nowrap">
                        <?php echo htmlspecialchars($col); ?>
                      </th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $row) : ?>
                    <tr>
                      <?php foreach ($columns as $col) : ?>
                        <?php
                          $value = isset($row[$col]) ? $row[$col] : '';
                          if (is_null($value)) {
                            $value = '';
                          }
                        ?>
                        <td style="padding: 8px; border-bottom: 1px solid #f0f4f1; vertical-align: top; min-width: 120px">
                          <?php echo nl2br(htmlspecialchars((string) $value)); ?>
                        </td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
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
