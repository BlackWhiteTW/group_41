<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/header.php';

if (!is_logged_in() || !in_array($current_user['role'], ['admin', 'club_officer'])) {
    header('Location: /group_41/index.php');
    exit();
}

$form_id = $_GET['id'] ?? 0;

if (!$form_id) {
    header('Location: /group_41/index.php');
    exit();
}

try {
    $sql = "SELECT f.*, u.username FROM forms f 
            LEFT JOIN users u ON f.creator_id = u.id
            WHERE f.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$form_id]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$form) {
        header('Location: /group_41/index.php');
        exit();
    }
    
    // 獲取所有題目
    $sql = "SELECT * FROM form_questions WHERE form_id = ? ORDER BY question_order";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$form_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 獲取填寫者清單（同帳號或同訪客IP會合併）
    $sql = "SELECT
                fs.user_id,
                fs.ip_address,
                u.username,
                u.email,
                u.club_category,
                COUNT(*) AS submit_count,
                MAX(fs.submitted_at) AS last_submitted_at
            FROM form_submissions fs
            LEFT JOIN users u ON fs.user_id = u.id
            WHERE fs.form_id = ?
            GROUP BY fs.user_id, fs.ip_address, u.username, u.email, u.club_category
            ORDER BY last_submitted_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$form_id]);
    $respondents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    echo '錯誤：' . $e->getMessage();
    exit();
}
?>

<h2 class="mb-4">📊 表單統計：<?php echo escape($form['title']); ?></h2>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">總填寫人數</h5>
                <h3>
                    <?php
                    $sql = "SELECT COUNT(DISTINCT user_id, ip_address) as count FROM form_submissions WHERE form_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$form_id]);
                    $result = $stmt->fetch();
                    echo $result ? $result['count'] : 0;
                    ?>
                </h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">總填寫次數</h5>
                <h3>
                    <?php
                    $sql = "SELECT COUNT(*) as count FROM form_submissions WHERE form_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$form_id]);
                    echo $stmt->fetch()['count'];
                    ?>
                </h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">總題數</h5>
                <h3><?php echo count($questions); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h5 class="card-title">回覆率</h5>
                <h3>
                    <?php
                    $sql = "SELECT COUNT(*) as count FROM form_submissions WHERE form_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$form_id]);
                    $total_submissions = $stmt->fetch()['count'];
                    echo $total_submissions > 0 ? '100%' : '0%';
                    ?>
                </h3>
            </div>
        </div>
    </div>
</div>

<hr class="my-4">

<h3 class="mb-3">填寫者清單</h3>

<?php if (empty($respondents)): ?>
    <div class="alert alert-info">目前尚無人填寫此表單</div>
<?php else: ?>
    <div class="table-responsive mb-4">
        <table class="table table-sm table-hover">
            <thead class="table-light">
                <tr>
                    <th>填寫者</th>
                    <th>Email/IP</th>
                    <th>社團</th>
                    <th>填寫次數</th>
                    <th>最後填寫時間</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($respondents as $row): ?>
                    <?php
                    $is_member = !empty($row['user_id']);
                    $display_name = $is_member
                        ? $row['username']
                        : ('訪客' . (!empty($row['ip_address']) ? '（' . $row['ip_address'] . '）' : ''));
                    $contact = $is_member
                        ? ($row['email'] ?: '-')
                        : ($row['ip_address'] ?: '-');
                    $club = $is_member ? ($row['club_category'] ?: '-') : '-';
                    ?>
                    <tr>
                        <td><?php echo escape($display_name); ?></td>
                        <td><?php echo escape($contact); ?></td>
                        <td><?php echo escape($club); ?></td>
                        <td><?php echo (int)$row['submit_count']; ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($row['last_submitted_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<hr class="my-4">

<h3>詳細統計</h3>

<div class="accordion mb-4" id="statisticsAccordion">
    <?php foreach ($questions as $q_index => $question): ?>
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading<?php echo $question['id']; ?>">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" 
                        data-bs-target="#collapse<?php echo $question['id']; ?>">
                    Q<?php echo $q_index + 1; ?>: <?php echo escape($question['question_text']); ?>
                </button>
            </h2>
            <div id="collapse<?php echo $question['id']; ?>" class="accordion-collapse collapse" 
                 data-bs-parent="#statisticsAccordion">
                <div class="accordion-body">
                    <?php if (in_array($question['question_type'], ['short_answer', 'long_answer'])): ?>
                        <!-- 文字題統計 -->
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>編號</th>
                                        <th>答案</th>
                                        <th>填寫時間</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql = "SELECT a.answer_text, fs.submitted_at FROM answers a 
                                            INNER JOIN form_submissions fs ON a.submission_id = fs.id
                                            WHERE a.question_id = ? ORDER BY fs.submitted_at DESC";
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute([$question['id']]);
                                    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($answers as $a_index => $answer):
                                    ?>
                                        <tr>
                                            <td><?php echo $a_index + 1; ?></td>
                                            <td>
                                                <small><?php echo escape(substr($answer['answer_text'], 0, 100)); ?>
                                                    <?php if (strlen($answer['answer_text']) > 100): ?>...<?php endif; ?>
                                                </small>
                                            </td>
                                            <td><small><?php echo date('Y-m-d H:i', strtotime($answer['submitted_at'])); ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    
                    <?php else: // multiple_choice or multi_choice ?>
                        <!-- 選擇題統計圖表 -->
                        <div class="row">
                            <div class="col-md-6">
                                <canvas id="chart_<?php echo $question['id']; ?>"></canvas>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>選項</th>
                                            <th>選擇數</th>
                                            <th>百分比</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sql = "SELECT qo.id, qo.option_text, COUNT(a.id) as count 
                                                FROM question_options qo 
                                                LEFT JOIN answers a ON qo.id = a.option_id AND a.question_id = ?
                                                WHERE qo.question_id = ?
                                                GROUP BY qo.id
                                                ORDER BY qo.option_order";
                                        $stmt = $pdo->prepare($sql);
                                        $stmt->execute([$question['id'], $question['id']]);
                                        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        $total_count = array_sum(array_column($options, 'count'));
                                        
                                        foreach ($options as $option):
                                            $percentage = $total_count > 0 ? round(($option['count'] / $total_count) * 100, 1) : 0;
                                        ?>
                                            <tr>
                                                <td><?php echo escape($option['option_text']); ?></td>
                                                <td><?php echo $option['count']; ?></td>
                                                <td>
                                                    <strong><?php echo $percentage; ?>%</strong>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-info">
                                            <td><strong>合計</strong></td>
                                            <td><strong><?php echo $total_count; ?></strong></td>
                                            <td><strong>100%</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <?php if ($question['question_type'] === 'multi_choice'): ?>
                                    <small class="text-muted">多選題百分比以「總勾選次數」為基準計算。</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <script>
                        (function() {
                            const ctx = document.getElementById('chart_<?php echo $question['id']; ?>').getContext('2d');
                            
                            const labels = [
                                <?php foreach ($options as $option): ?>
                                    '<?php echo addslashes(escape($option['option_text'])); ?>',
                                <?php endforeach; ?>
                            ];
                            
                            const data = [
                                <?php foreach ($options as $option): ?>
                                    <?php echo $option['count']; ?>,
                                <?php endforeach; ?>
                            ];
                            
                            const bgColors = [
                                '#667eea', '#764ba2', '#f093fb', '#4facfe', 
                                '#43e97b', '#fa709a', '#fee140', '#30cfd0',
                                '#a8edea', '#fed6e3', '#74b9ff', '#a29bfe'
                            ];
                            
                            new Chart(ctx, {
                                type: 'doughnut',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        data: data,
                                        backgroundColor: bgColors.slice(0, labels.length),
                                        borderColor: '#fff',
                                        borderWidth: 2
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: {
                                        legend: {
                                            position: 'bottom'
                                        }
                                    }
                                }
                            });
                        })();
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="text-end mb-3">
    <a href="/group_41/forms/list.php" class="btn btn-outline-secondary">返回表單列表</a>
</div>

<?php include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/footer.php'; ?>

