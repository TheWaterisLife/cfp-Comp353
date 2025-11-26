<?php

/**
 * Bootstraps public entrypoints by locating the shared includes directory
 * regardless of whether the files live in src/public (dev) or the web root (prod).
 */
if (!defined('CFP_INCLUDE_DIR')) {
    $candidates = [
        __DIR__ . '/../includes', // local dev (src/public)
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

// Compute a base path (URL prefix) so the app can be served from a subdirectory,
// e.g. http://localhost/cfp/src/public/index.php under MAMP.
if (!defined('CFP_BASE_PATH')) {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/') ?: '/';
    $pos = strpos($scriptDir, '/public');
    if ($pos !== false) {
        $base = substr($scriptDir, 0, $pos + strlen('/public'));
    } else {
        $base = $scriptDir;
    }
    define('CFP_BASE_PATH', rtrim($base, '/') . '/');
}


