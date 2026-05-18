<?php
session_start();
session_unset();
session_destroy();
header('Location: /group_41/index.php');
exit();

