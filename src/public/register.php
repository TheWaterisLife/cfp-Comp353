<?php
require_once __DIR__ . '/../includes/auth.php';

$error = '';
$message = '';
$matrixJson = null;
$matrixGrid = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Name, email, and password are required.';
    } else {
        $pdo = cfp_get_pdo();

        // Check for existing email
        $stmt = $pdo->prepare('SELECT id FROM members WHERE primary_email = :email');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $error = 'An account with that email already exists.';
        } else {
            $matrixJson = cfp_generate_auth_matrix();
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO members (
                    introducer_id, name, org, address,
                    primary_email, recovery_email, password_hash,
                    auth_matrix, matrix_expiry, orcid,
                    role_id, status_id, created_at, updated_at
                ) VALUES (
                    NULL, :name, NULL, NULL,
                    :email, NULL, :password_hash,
                    :matrix, DATE_ADD(NOW(), INTERVAL 1 YEAR), NULL,
                    (SELECT id FROM roles WHERE name = 'member'),
                    (SELECT id FROM member_statuses WHERE code = 'active'),
                    NOW(), NOW()
                )
            ");
            $stmt->execute([
                'name'          => $name,
                'email'         => $email,
                'password_hash' => $passwordHash,
                'matrix'        => $matrixJson,
            ]);

            // Decode matrix for display to the user (so they can record it).
            $decoded = json_decode($matrixJson, true);
            if (is_array($decoded) && isset($decoded['grid']) && is_array($decoded['grid'])) {
                $matrixGrid = $decoded['grid'];
            }

            $message = 'Registration successful. Your verification matrix is shown below; you will need it for login.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CFP Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #020617; color: #e5e7eb; display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .card { background:#020617; border-radius:0.75rem; padding:2rem; width:100%; max-width:400px; border:1px solid rgba(148,163,184,.3); box-shadow:0 18px 35px rgba(0,0,0,.5); }
        h1 { margin:0 0 1rem; font-size:1.4rem; }
        label { display:block; margin-top:0.75rem; font-size:0.85rem; color:#9ca3af; }
        input { width:100%; margin-top:0.25rem; padding:0.5rem 0.6rem; border-radius:0.4rem; border:1px solid rgba(148,163,184,.4); background:#020617; color:#e5e7eb; }
        button { margin-top:1rem; width:100%; padding:0.6rem 0.8rem; border-radius:999px; border:none; background:#22c55e; color:#022c22; font-weight:600; cursor:pointer; }
        button:hover { background:#16a34a; }
        .error { margin-top:0.75rem; color:#fecaca; font-size:0.85rem; }
        .message { margin-top:0.75rem; color:#bbf7d0; font-size:0.85rem; }
        .hint { margin-top:0.75rem; font-size:0.8rem; color:#9ca3af; }
        a { color:#7dd3fc; text-decoration:none; }
    </style>
</head>
<body>
<main class="card">
    <h1>Create a CFP account</h1>
    <?php if ($error): ?>
        <div class="error"><?php echo e($error); ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="message"><?php echo e($message); ?></div>
        <?php if ($matrixGrid): ?>
            <div class="hint" style="margin-top:0.75rem;">
                <p>Your verification matrix (rows 1–4, columns 1–4):</p>
                <table style="border-collapse:collapse; margin-top:0.25rem;">
                    <?php foreach ($matrixGrid as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td style="border:1px solid rgba(148,163,184,.6); padding:0.25rem 0.5rem; text-align:center; font-weight:600;">
                                    <?php echo e($cell); ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <p style="margin-top:0.5rem;">
                    For login, enter the 3-letter code made from the characters at positions (row,column) (1,1), (2,2), and (3,3).
                </p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <form method="post">
        <label>
            Full name
            <input type="text" name="name" required>
        </label>
        <label>
            Email
            <input type="email" name="email" required>
        </label>
        <label>
            Password
            <input type="password" name="password" required>
        </label>
        <button type="submit">Register</button>
    </form>
    <p class="hint">
        Already registered? <a href="/login.php">Login</a>.
    </p>
</main>
</body>
</html>


