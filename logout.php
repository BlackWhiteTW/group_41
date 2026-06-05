<?php
require_once './includes/cookies.php';
require './includes/db.php';
clear_user_session();
header('Location: ./index.php');
exit();

