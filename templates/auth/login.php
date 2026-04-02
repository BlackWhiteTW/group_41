<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登入 - 社團表單系統</title>
    <link href="/group_41/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            border: none;
            border-radius: 15px;
            max-width: 450px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        .login-header h2 {
            margin: 0;
            font-weight: 700;
        }
        .login-body {
            padding: 30px;
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-header">
        <h2>🎓 社團表單系統</h2>
        <p style="margin: 10px 0 0 0;">校內活動問卷統計平台</p>
    </div>
    <div class="login-body">
        <h4 class="mb-4">登入帳號</h4>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo escape($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="mb-3">
                <label for="username" class="form-label">帳號</label>
                <input type="text" class="form-control" id="username" name="username" required placeholder="請輸入您的帳號">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">密碼</label>
                <input type="password" class="form-control" id="password" name="password" required
                    placeholder="請輸入您的密碼">
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3">登入</button>
        </form>

        <hr class="my-4">

        <p class="text-center text-muted">還沒有帳號？
            <a href="/group_41/register.php" class="text-decoration-none fw-bold">立即註冊</a>
        </p>
    </div>
</div>

<script src="/group_41/js/jquery-3.7.1.min.js"></script>
<script src="/group_41/js/sweetalert2@11.js"></script>
<script src="/group_41/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    $('#loginForm').on('submit', function() {
        let username = $('#username').val().trim();
        let password = $('#password').val().trim();

        if (!username || !password) {
            Swal.fire('欠缺資料', '帳號和密碼都是必填的', 'warning');
            return false;
        }
    });
});
</script>

</body>
</html>
