<?php
// Author: Samy Belmihoub (40251504)

require_once __DIR__ . '/bootstrap.php';
require_once CFP_INCLUDE_DIR . '/auth.php';

cfp_logout();
header('Location: ' . cfp_url('index.php'));
exit;


