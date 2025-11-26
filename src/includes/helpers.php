<?php

/**
 * Common helper functions for CFP.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get current logged in member (row from `members`) or null.
 *
 * @return array|null
 */
function cfp_current_user(): ?array
{
    return $_SESSION['member'] ?? null;
}

/**
 * True if a user is logged in.
 */
function cfp_is_logged_in(): bool
{
    return cfp_current_user() !== null;
}

/**
 * Get current user role code (admin, moderator, author, member) or null.
 */
function cfp_current_role(): ?string
{
    $user = cfp_current_user();
    return $user['role_code'] ?? null;
}

/**
 * Require login; redirects to login page if not logged in.
 */
function cfp_require_login(): void
{
    if (!cfp_is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Require a specific role (or one of several); redirects if not authorized.
 *
 * @param string|array $roles
 */
function cfp_require_role($roles): void
{
    cfp_require_login();
    $current = cfp_current_role();
    $rolesList = is_array($roles) ? $roles : [$roles];

    if (!in_array($current, $rolesList, true)) {
        http_response_code(403);
        echo 'Forbidden (insufficient role).';
        exit;
    }
}

/**
 * Escape HTML.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Build an application-relative URL that works even when the site is served
 * from a subdirectory (e.g. /cfp/src/public under MAMP).
 */
function cfp_url(string $path): string
{
    $base = defined('CFP_BASE_PATH') ? CFP_BASE_PATH : '/';
    return $base . ltrim($path, '/');
}


