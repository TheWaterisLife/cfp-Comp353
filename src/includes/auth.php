<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Attempt to authenticate a user by email + password + optional matrix code.
 *
 * Passwords are stored using password_hash(); this function uses
 * password_verify() and also supports upgrading legacy plain-text passwords
 * that may exist from older seed data.
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

    $storedPassword = (string)($member['password_hash'] ?? '');

    // Prefer secure password_verify() for hashed passwords.
    $info = password_get_info($storedPassword);
    $isHashed = ($info['algo'] ?? 0) !== 0;

    $validPassword = false;

    if ($isHashed) {
        $validPassword = password_verify($password, $storedPassword);
    } else {
        // Backwards-compatibility for any legacy plain-text passwords
        // (e.g., from old sample_data); if it matches, upgrade in-place.
        if (hash_equals($storedPassword, $password)) {
            $validPassword = true;

            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update = $pdo->prepare('UPDATE members SET password_hash = :hash WHERE id = :id');
            $update->execute([
                'hash' => $newHash,
                'id'   => $member['id'],
            ]);

            $storedPassword = $newHash;
        }
    }

    if (!$validPassword) {
        return false;
    }

    // Matrix-based second factor: if a matrix is set and not expired, require
    // a code derived from the stored matrix. For backwards compatibility with
    // legacy seed data (where auth_matrix is a plain string), we skip this
    // check when the stored value is not valid JSON.
    if (!empty($member['auth_matrix']) && $member['matrix_expiry'] !== null) {
        $expiry = strtotime($member['matrix_expiry']);
        if ($expiry !== false && $expiry < time()) {
            // Matrix expired; require renewal in later phases.
            return false;
        }

        // Require a matrix code when a valid matrix is configured.
        if ($matrixCode === null || trim($matrixCode) === '') {
            return false;
        }

        $matrixData = json_decode($member['auth_matrix'], true);

        // If auth_matrix is not valid JSON, treat it as legacy and skip
        // matrix verification to keep existing demo users working.
        if (is_array($matrixData) && isset($matrixData['grid']) && is_array($matrixData['grid'])) {
            $expected = '';
            $grid = $matrixData['grid'];

            // Fixed coordinates for Phase 6: (row, col) = (0,0), (1,1), (2,2)
            $coords = [[0, 0], [1, 1], [2, 2]];
            foreach ($coords as $coord) {
                $r = $coord[0];
                $c = $coord[1];
                if (isset($grid[$r][$c])) {
                    $expected .= (string)$grid[$r][$c];
                }
            }

            $expected = strtoupper($expected);
            $provided = strtoupper(trim($matrixCode));

            if ($expected === '' || !hash_equals($expected, $provided)) {
                return false;
            }
        }
    }

    $_SESSION['member'] = [
        'id'          => $member['id'],
        'name'        => $member['name'],
        'email'       => $member['primary_email'],
        'role_id'     => $member['role_id'],
        'role_code'   => $member['role_code'],
        'status_id'   => $member['status_id'],
        'status_code' => $member['status_code'],
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
 * Generate an authentication matrix for a member.
 *
 * We store a small character matrix as JSON in members.auth_matrix. The
 * structure is:
 *   {
 *     "rows": 4,
 *     "cols": 4,
 *     "grid": [["A","B",...], ...]
 *   }
 *
 * For Phase 6 we keep the usage simple: during login we derive a short
 * verification code from fixed coordinates in this grid.
 */
function cfp_generate_auth_matrix(): string
{
    $rows = 4;
    $cols = 4;
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // avoid ambiguous chars
    $grid = [];

    for ($r = 0; $r < $rows; $r++) {
        $row = [];
        for ($c = 0; $c < $cols; $c++) {
            $row[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $grid[] = $row;
    }

    return json_encode([
        'rows' => $rows,
        'cols' => $cols,
        'grid' => $grid,
    ]);
}


