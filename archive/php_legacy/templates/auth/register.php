<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>註冊 - 社團表單系統</title>
    <link href="/group_41/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .register-card {
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            border: none;
            border-radius: 15px;
            max-width: 550px;
            margin: 0 auto;
        }
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        .register-header h2 {
            margin: 0;
            font-weight: 700;
        }
        .register-body {
            padding: 30px;
        }
        .username-feedback {
            font-size: 0.875rem;
            margin-top: 5px;
        }
        .username-available {
            color: #28a745;
        }
        .username-unavailable {
            color: #dc3545;
        }
        .username-checking {
            color: #ffc107;
        }
    </style>
</head>
<body>
<div class="register-card">
    <div class="register-header">
        <h2>🎓 社團表單系統</h2>
        <p style="margin: 10px 0 0 0;">校內活動問卷統計平台</p>
    </div>
    <div class="register-body">
        <h4 class="mb-4">建立新帳號</h4>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo escape($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="registerForm">
            <div class="mb-3">
                <label for="username" class="form-label">帳號 <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="text" class="form-control" id="username" name="username" required
                           placeholder="3個以上字符" minlength="3">
                    <span class="input-group-text" id="username-check-spinner" style="display:none;">
                        ⏳ 檢查中...
                    </span>
                </div>
                <div class="username-feedback" id="username-feedback"></div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">電子郵件 <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="email" name="email" required
                       placeholder="example@school.edu">
            </div>

            <div class="mb-3">
                <label class="form-label d-block">社團設定 <span class="text-danger">*</span></label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="club_mode" id="club_mode_existing" value="existing" checked>
                    <label class="form-check-label" for="club_mode_existing">加入既有社團</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="club_mode" id="club_mode_new" value="new">
                    <label class="form-check-label" for="club_mode_new">建立新社團（自動成為社團幹部）</label>
                </div>
            </div>

            <div class="mb-3" id="existingClubGroup">
                <label for="existing_club" class="form-label">選擇社團 <span class="text-danger">*</span></label>
                <select class="form-select" id="existing_club" name="existing_club">
                    <option value="">-- 請選擇社團 --</option>
                    <?php foreach ($existing_clubs as $club_name): ?>
                        <option value="<?php echo escape($club_name); ?>"><?php echo escape($club_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3" id="newClubGroup" style="display:none;">
                <label for="new_club_name" class="form-label">新社團名稱 <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="new_club_name" name="new_club_name" placeholder="例如：資工系學會">
                <small class="text-muted">建立者會成為社團擁有者，其他人不可修改你的擁有者身份，除非你主動移轉。</small>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">密碼 <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="password" name="password" required
                       placeholder="至少6個字符" minlength="6">
                <small class="text-muted">密碼應包含字母、數字和特殊字符以增強安全性</small>
            </div>

            <div class="mb-3">
                <label for="password_confirm" class="form-label">確認密碼 <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required
                       placeholder="再次輸入密碼">
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3">註冊帳號</button>
        </form>

        <hr class="my-4">

        <p class="text-center text-muted">已有帳號？
            <a href="/group_41/login.php" class="text-decoration-none fw-bold">登入帳號</a>
        </p>
    </div>
</div>

<script src="/group_41/js/jquery-3.7.1.min.js"></script>
<script src="/group_41/js/sweetalert2@11.js"></script>
<script src="/group_41/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    let checkUsernameTimeout;
    $('#username').on('keyup', function() {
        let username = $(this).val().trim();
        let $feedback = $('#username-feedback');
        let $spinner = $('#username-check-spinner');

        clearTimeout(checkUsernameTimeout);

        if (username.length < 3) {
            $feedback.text('帳號至少需要3個字符').removeClass('username-available username-checking');
            $spinner.hide();
            return;
        }

        $feedback.text('檢查中...').addClass('username-checking');
        $spinner.show();

        checkUsernameTimeout = setTimeout(function() {
            $.ajax({
                url: '/group_41/api/check_username.php',
                type: 'POST',
                data: { username: username },
                dataType: 'json',
                success: function(response) {
                    $spinner.hide();
                    if (response.available) {
                        $feedback.text('✓ 帳號可用').removeClass('username-checking username-unavailable').addClass('username-available');
                    } else {
                        $feedback.text('✗ 帳號已存在').removeClass('username-checking username-available').addClass('username-unavailable');
                    }
                }
            });
        }, 500);
    });

    $('#registerForm').on('submit', function() {
        let password = $('#password').val();
        let passwordConfirm = $('#password_confirm').val();
        const clubMode = $('input[name="club_mode"]:checked').val();
        const existingClub = $('#existing_club').val();
        const newClubName = $('#new_club_name').val().trim();

        if (password !== passwordConfirm) {
            Swal.fire('錯誤', '兩次輸入的密碼不一致', 'error');
            return false;
        }

        if (password.length < 6) {
            Swal.fire('錯誤', '密碼至少需要6個字符', 'error');
            return false;
        }

        if (clubMode === 'existing' && !existingClub) {
            Swal.fire('錯誤', '請選擇要加入的社團', 'error');
            return false;
        }

        if (clubMode === 'new' && !newClubName) {
            Swal.fire('錯誤', '請輸入新社團名稱', 'error');
            return false;
        }

        return true;
    });

    function toggleClubMode() {
        const clubMode = $('input[name="club_mode"]:checked').val();
        if (clubMode === 'new') {
            $('#existingClubGroup').hide();
            $('#newClubGroup').show();
        } else {
            $('#existingClubGroup').show();
            $('#newClubGroup').hide();
        }
    }

    $('input[name="club_mode"]').on('change', toggleClubMode);
    toggleClubMode();

    <?php if (!empty($success)): ?>
    Swal.fire({
        title: '註冊成功',
        text: '您的帳號已建立，請登入',
        icon: 'success',
        confirmButtonText: '前往登入'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '/group_41/login.php';
        }
    });
    <?php endif; ?>
});
</script>

</body>
</html>
