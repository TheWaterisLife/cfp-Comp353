<?php
// Author: Samy Belmihoub (40251504)

if (!defined('CFP_INCLUDE_DIR')) {
    $candidates = [
        __DIR__ . '/includes',    // deployed web root (includes as sibling)
    ];

    foreach ($candidates as $candidate) {
        $resolved = realpath($candidate);
        if ($resolved && is_dir($resolved) && file_exists($resolved . '/helpers.php')) {
            define('CFP_INCLUDE_DIR', $resolved);
            break;
        }
    }

    if (!defined('CFP_INCLUDE_DIR')) {
        http_response_code(500);
        echo 'CFP bootstrap error: unable to locate includes directory.';
        exit;
    }
}

require_once CFP_INCLUDE_DIR . '/helpers.php';
require_once CFP_INCLUDE_DIR . '/db.php';

// Compute a base path (URL prefix).
if (!defined('CFP_BASE_PATH')) {
    define('CFP_BASE_PATH', '/');
}


