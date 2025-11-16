<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

cfp_require_role('admin');

$pdo = cfp_get_pdo();
$error = '';

// Update role/status for an existing member
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $roleId = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
    $statusId = isset($_POST['status_id']) ? (int)$_POST['status_id'] : 0;

    if ($id > 0 && $roleId > 0 && $statusId > 0) {
        $stmt = $pdo->prepare('UPDATE members SET role_id = :role_id, status_id = :status_id, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'role_id'   => $roleId,
            'status_id' => $statusId,
            'id'        => $id,
        ]);
        header('Location: /admin/members.php');
        exit;
    } else {
        $error = 'Invalid role or status selection.';
    }
}

// Simple "deactivate" helper
if (($_GET['action'] ?? '') === 'deactivate' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("
        UPDATE members
        SET status_id = (SELECT id FROM member_statuses WHERE code = 'disabled')
        WHERE id = :id
    ");
    $stmt->execute(['id' => $id]);
    header('Location: /admin/members.php');
    exit;
}

$roles = $pdo->query('SELECT id, name FROM roles ORDER BY id')->fetchAll();
$statuses = $pdo->query('SELECT id, code FROM member_statuses ORDER BY id')->fetchAll();

$members = $pdo->query("
    SELECT m.id, m.name, m.primary_email, r.name AS role_name, ms.code AS status_code,
           m.role_id, m.status_id
    FROM members m
    JOIN roles r ON m.role_id = r.id
    JOIN member_statuses ms ON m.status_id = ms.id
    ORDER BY m.id
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin · Members</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background:#020617; color:#e5e7eb; }
        main { max-width:1100px; margin:2rem auto; padding:1.5rem; background:#020617; border-radius:0.75rem; border:1px solid rgba(148,163,184,.4); }
        h1 { margin-top:0; font-size:1.5rem; }
        table { width:100%; border-collapse:collapse; margin-top:1rem; font-size:0.9rem; }
        th, td { padding:0.5rem; border-bottom:1px solid rgba(148,163,184,.3); text-align:left; }
        a { color:#7dd3fc; text-decoration:none; }
        select { padding:0.25rem 0.4rem; border-radius:0.4rem; border:1px solid rgba(148,163,184,.4); background:#020617; color:#e5e7eb; font-size:0.8rem; }
        button { padding:0.25rem 0.7rem; border-radius:999px; border:none; background:#38bdf8; color:#0f172a; cursor:pointer; font-size:0.8rem; }
        .error { color:#fecaca; font-size:0.8rem; margin-top:0.5rem; }
        .top-bar { display:flex; justify-content:space-between; align-items:center; }
    </style>
</head>
<body>
<main>
    <div class="top-bar">
        <h1>Admin · Members</h1>
        <div>
            <a href="/index.php">← Home</a>
        </div>
    </div>

    <p style="font-size:0.85rem; color:#9ca3af;">
        Manage member roles and statuses. New members can be created via the public registration page.
    </p>

    <?php if ($error): ?><div class="error"><?php echo e($error); ?></div><?php endif; ?>

    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($members as $m): ?>
            <tr>
                <td><?php echo (int)$m['id']; ?></td>
                <td><?php echo e($m['name']); ?></td>
                <td><?php echo e($m['primary_email']); ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                        <select name="role_id">
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo (int)$r['id']; ?>" <?php if ($r['id'] == $m['role_id']) echo 'selected'; ?>>
                                    <?php echo e($r['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                </td>
                <td>
                        <select name="status_id">
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?php echo (int)$s['id']; ?>" <?php if ($s['id'] == $m['status_id']) echo 'selected'; ?>>
                                    <?php echo e($s['code']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                </td>
                <td>
                        <button type="submit">Save</button>
                        <a href="/admin/members.php?action=deactivate&amp;id=<?php echo (int)$m['id']; ?>" style="margin-left:0.4rem; font-size:0.8rem;" onclick="return confirm('Deactivate this member?');">
                            Deactivate
                        </a>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
</body>
</html>


