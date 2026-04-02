<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/header.php';

$form_id = $_GET['form_id'] ?? 0;

if (!$form_id) {
    header('Location: /group_41/index.php');
    exit();
}

try {
    $sql = "SELECT title FROM forms WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$form_id]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$form) {
        header('Location: /group_41/index.php');
        exit();
    }
} catch (Exception $e) {
    header('Location: /group_41/index.php');
    exit();
}
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-6 text-center">
        <div class="card border-success">
            <div class="card-body p-5">
                <div style="font-size: 4rem; margin-bottom: 20px;">✓</div>
                <h2 class="card-title text-success mb-3">感謝您填寫表單</h2>
                <p class="card-text mb-4">
                    您已成功提交「<strong><?php echo escape($form['title']); ?></strong>」<br>
                    感謝您的參與！
                </p>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="/group_41/index.php" class="btn btn-primary">
                        返回首頁
                    </a>
                    <a href="/group_41/forms/list.php" class="btn btn-outline-primary">
                        查看其他表單
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/footer.php'; ?>

