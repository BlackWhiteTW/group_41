<?php
// SQL 資料檢視頁面（管理區）：提供管理員查看資料庫內各資料表內容的介面

session_start();
require '../includes/db.php';

$user_raw = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$user = !empty($user_raw) ? htmlspecialchars($user_raw) : null;
$current_user = null;
$errors = [];

if (empty($user_raw)) {
  header('Location: ../login.php');
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

$table_descriptions = [
  'users'              => '使用者帳號表 — 儲存所有使用者基本資料、系統層級角色，以及記住我 Token 雜湊（remember_token_hash）。',
  'clubs'              => '社團基本資料表 — 每個社團由一位使用者建立，記錄名稱、簡介、加入模式與可見性。',
  'club_memberships'   => '社團成員關聯表 — 使用者 ↔ 社團的多對多關係，記錄每位成員在社團中的角色。',
  'forms'              => '表單主表 — 每張表單的標題、權限、狀態與開放時間，可掛在社團下或設為全域表單。',
  'form_questions'     => '表單題目表 — 一張表單可包含多個題目，支援簡答、長答、單選、多選、檔案上傳。',
  'question_options'   => '選擇題選項表 — 僅用於單選 / 多選題，每個選項屬於一道題目。',
  'form_submissions'   => '表單填寫記錄表 — 每次提交產生一筆，記錄填答者與提交時間。',
  'answers'            => '答案明細表 — 一筆提交中每題的具體答案，文字/選項/檔案路徑擇一存放。',
];

$selected = isset($_GET['table']) ? $_GET['table'] : $allowed_tables[0];
if (!in_array($selected, $allowed_tables, true)) {
  $selected = $allowed_tables[0];
}

$counts      = [];
$columns     = [];   // ['Field' => ..., 'Type' => ..., 'Comment' => ...]
$column_names = [];
$rows        = [];
$total_rows  = 0;
$table_comment = '';

try {
  $pdo = get_db();
  if ($user_raw) {
    $u = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :u LIMIT 1');
    $u->execute([':u' => $user_raw]);
    $current_user = $u->fetch();
  }

  if (!$current_user || $current_user['role'] !== 'admin') {
    $_SESSION['flash_error'] = '需要管理員權限才能瀏覽資料表。';
    header('Location: ../index.php');
    exit();
  }

  foreach ($allowed_tables as $table) {
    $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
  }

  // 取得完整欄位資訊（含 Comment）
  $col_info = $pdo->query('SHOW FULL COLUMNS FROM `' . $selected . '`')->fetchAll();
  foreach ($col_info as $col) {
    $columns[] = $col;
    $column_names[] = $col['Field'];
  }

  // 取得資料表註解
  $tbl_status = $pdo->query("SHOW TABLE STATUS WHERE Name = '$selected'")->fetch();
  $table_comment = $tbl_status['Comment'] ?? '';

  $total_rows = isset($counts[$selected]) ? $counts[$selected] : 0;
  $order_sql = in_array('id', $column_names, true) ? ' ORDER BY `id` DESC' : '';
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
    <link rel="stylesheet" href="../css/app.css" />
  </head>
  <body>
    <?php $base_url = '../'; require '../includes/header.php'; ?>

    <?php require __DIR__ . '/../includes/right.php'; ?>

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
            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center">
              <?php foreach ($allowed_tables as $table) : ?>
                <a class="btn <?php echo $table === $selected ? 'btn-primary' : 'btn-ghost'; ?>"
                   href="sql_view.php?table=<?php echo urlencode($table); ?>">
                  <?php echo htmlspecialchars($table); ?>
                  <span class="badge" style="margin-left: 4px; background: <?php echo $table === $selected ? 'rgba(255,255,255,0.25)' : '#e4efe8'; ?>; color: <?php echo $table === $selected ? '#fff' : '#2a7d4f'; ?>; font-size: 11px">
                    <?php echo number_format($counts[$table] ?? 0); ?>
                  </span>
                </a>
              <?php endforeach; ?>
            </div>
            <?php $desc_text = $table_comment ?: ($table_descriptions[$selected] ?? ''); ?>
            <?php if ($desc_text) : ?>
              <p style="margin-top: 12px; padding: 8px 14px; background: #f5faf7; border-left: 4px solid #2a7d4f; border-radius: 4px; font-size: 13px; color: #2a4d36">
                <?php echo htmlspecialchars($desc_text); ?>
              </p>
            <?php endif; ?>
            <div style="margin-top: 8px; font-size: 12px; color: #888">
              共 <?php echo number_format($total_rows); ?> 筆資料，顯示前 200 筆
            </div>
          </div>

          <div class="panel" style="padding: 0; overflow: auto">
            <?php if (empty($columns)) : ?>
              <p class="muted" style="padding: 20px">找不到資料欄位。</p>
            <?php elseif (empty($rows)) : ?>
              <p class="muted" style="padding: 20px">目前沒有資料。</p>
            <?php else : ?>
              <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 13px">
                <thead>
                  <tr>
                    <?php foreach ($columns as $col) : ?>
                      <th style="text-align: left; padding: 10px 12px; border-bottom: 2px solid #2a7d4f; background: #f5faf7; white-space: nowrap; position: sticky; top: 0">
                        <div style="font-weight: 700"><?php echo htmlspecialchars($col['Field']); ?></div>
                        <div style="font-weight: 400; font-size: 11px; color: #6b8f71"><?php echo htmlspecialchars($col['Type']); ?></div>
                        <?php if (!empty($col['Comment'])) : ?>
                          <div style="font-weight: 400; font-size: 11px; color: #999; max-width: 180px; white-space: normal"><?php echo htmlspecialchars($col['Comment']); ?></div>
                        <?php endif; ?>
                      </th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $idx => $row) : ?>
                    <tr style="background: <?php echo $idx % 2 === 0 ? '#fff' : '#f9fbfa'; ?>">
                      <?php foreach ($columns as $col) : ?>
                        <?php
                          $field = $col['Field'];
                          $value = $row[$field] ?? null;
                        ?>
                        <td style="padding: 8px 12px; border-bottom: 1px solid #e4efe8; vertical-align: top; min-width: 100px; max-width: 360px; word-break: break-all">
                          <?php if (is_null($value)) : ?>
                            <span style="color: #c0c8c3; font-style: italic; font-size: 11px">NULL</span>
                          <?php else : ?>
                            <?php echo nl2br(htmlspecialchars((string) $value)); ?>
                          <?php endif; ?>
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
    <script src="../js/app.js"></script>
  </body>
</html>

<?php
exit();
