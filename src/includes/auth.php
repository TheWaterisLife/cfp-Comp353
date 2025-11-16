<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Attempt to authenticate a user by email + password + optional matrix code.
 *
 * NOTE: For Phase 3 we still use placeholder hashes ('changeme'); later phases
 * should replace this with password_hash / password_verify.
 *
 * @return bool true on success, false on failure
 */
function cfp_login(string $email, string $password, ?string $matrixCode = null): bool
{
    $pdo = cfp_get_pdo();

    $stmt = $pdo->prepare("
        SELECT m.*, r.name AS role_code, ms.code AS status_code
        FROM members m
        JOIN roles r ON m.role_id = r.id
        JOIN member_statuses ms ON m.status_id = ms.id
        WHERE m.primary_email = :email
        LIMIT 1
    ");
    $stmt->execute(['email' => $email]);
    $member = $stmt->fetch();

    if (!$member) {
        return false;
    }

    if ($member['status_code'] !== 'active') {
        return false;
    }

    // TODO: Replace with password_verify in a later phase.
    if ($member['password_hash'] !== $password) {
        return false;
    }

    // Simple matrix check placeholder: if auth_matrix is set and not expired,
    // require a non-empty matrix code that matches a fixed value.
    if (!empty($member['auth_matrix']) && $member['matrix_expiry'] !== null) {
        $expiry = strtotime($member['matrix_expiry']);
        if ($expiry !== false && $expiry < time()) {
            // Matrix expired; require renewal in later phases.
            return false;
        }

        if ($matrixCode === null || $matrixCode === '') {
            return false;
        }

        // For now, accept any non-empty code; real logic can parse auth_matrix.
    }

    $_SESSION['member'] = [
        'id'         => $member['id'],
        'name'       => $member['name'],
        'email'      => $member['primary_email'],
        'role_id'    => $member['role_id'],
        'role_code'  => $member['role_code'],
        'status_id'  => $member['status_id'],
        'status_code'=> $member['status_code'],
    ];

    return true;
}

/**
 * Log out the current user.
 */
function cfp_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Stub for generating an authentication matrix on registration.
 *
 * For now this returns a simple string; later phases can encode structured
 * matrix data (e.g., JSON).
 */
function cfp_generate_auth_matrix(): string
{
    return 'matrix-' . bin2hex(random_bytes(4));
}


