<?php
// Author: Samy Belmihoub (40251504)

// Public entrypoint: locate shared includes via bootstrap so this file works
require_once __DIR__ . '/bootstrap.php';
require_once CFP_INCLUDE_DIR . '/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $matrix = $_POST['matrix_code'] ?? null;

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } elseif (cfp_login($email, $password, $matrix)) {
        header('Location: ' . cfp_url('index.php'));
        exit;
    } else {
        $error = 'Invalid credentials or inactive account.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CFP Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #020617; color: #e5e7eb; display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .card { background:#020617; border-radius:0.75rem; padding:2rem; width:100%; max-width:360px; border:1px solid rgba(148,163,184,.3); box-shadow:0 18px 35px rgba(0,0,0,.5); }
        h1 { margin:0 0 1rem; font-size:1.4rem; }
        label { display:block; margin-top:0.75rem; font-size:0.85rem; color:#9ca3af; }
        input { width:100%; margin-top:0.25rem; padding:0.5rem 0.6rem; border-radius:0.4rem; border:1px solid rgba(148,163,184,.4); background:#020617; color:#e5e7eb; }
        button { margin-top:1rem; width:100%; padding:0.6rem 0.8rem; border-radius:999px; border:none; background:#38bdf8; color:#0f172a; font-weight:600; cursor:pointer; }
        button:hover { background:#0ea5e9; }
        .error { margin-top:0.75rem; color:#fecaca; font-size:0.85rem; }
        .hint { margin-top:0.75rem; font-size:0.8rem; color:#9ca3af; }
        a { color:#7dd3fc; text-decoration:none; }
    </style>
</head>
<body>
<main class="card">
    <h1>Sign in to CFP</h1>
    <?php if ($error): ?>
        <div class="error"><?php echo e($error); ?></div>
    <?php endif; ?>
    <form method="post">
        <label>
            Email
            <input type="email" name="email" required>
        </label>
        <label>
            Password
            <input type="password" name="password" required>
        </label>
        <label>
            Matrix code
            <input type="text" name="matrix_code" placeholder="3-letter code from your matrix">
        </label>
        <button type="submit">Login</button>
    </form>
    <p class="hint">
        Demo users are loaded via <code>db/seed.sql</code> (e.g. <code>alice.admin@example.org</code>, password <code>changeme</code>).
        When a verification matrix is configured for your account, enter the 3-letter code built from cells (row,column) (1,1), (2,2) and (3,3).
    </p>
    <p class="hint">
        No account? <a href="<?php echo cfp_url('register.php'); ?>">Register</a>.
    </p>
</main>
</body>
</html>


