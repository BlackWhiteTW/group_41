<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/db.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/functions.php';

$current_user = get_current_user_info();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>社團表單系統</title>
    <link href="/group_41/css/bootstrap.min.css" rel="stylesheet">
    <link href="/group_41/css/datatables.min.css" rel="stylesheet">
    <link href="/group_41/css/style.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .container-main {
            margin-top: 30px;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            margin-bottom: 20px;
        }
        .form-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="/group_41/index.php">
            <strong>社團表單系統</strong>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <?php if (is_logged_in()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            📋 表單
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="/group_41/forms/list.php">查看表單</a></li>
                            <?php if (in_array($current_user['role'], ['club_officer', 'admin'])): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/group_41/forms/create.php">建立新表單</a></li>
                                <li><a class="dropdown-item" href="/group_41/forms/my_forms.php">我的表單</a></li>
                                <li><a class="dropdown-item" href="/group_41/clubs/manage.php">社團管理</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php if ($current_user['role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/group_41/admin.php">⚙️ 管理</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            👤 <?php echo escape($current_user['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><span class="dropdown-item-text">身份: <?php echo escape($current_user['role']); ?></span></li>
                            <li><span class="dropdown-item-text">社團: <?php echo escape($current_user['club_category']); ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/group_41/logout.php">登出</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/group_41/login.php">登入</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/group_41/register.php">註冊</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div class="container container-main">

