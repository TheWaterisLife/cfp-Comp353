<?php
require_once __DIR__ . '/../includes/auth.php';

cfp_logout();
header('Location: /index.php');
exit;


