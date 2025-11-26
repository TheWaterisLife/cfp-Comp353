<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

cfp_require_role('admin');

$pdo = cfp_get_pdo();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $error = 'Name is required.';
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE committees SET name = :name, description = :description, updated_at = NOW() WHERE id = :id');
            $stmt->execute([
                'name'        => $name,
                'description' => $description,
                'id'          => $id,
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO committees (name, description, created_at, updated_at) VALUES (:name, :description, NOW(), NOW())');
            $stmt->execute([
                'name'        => $name,
                'description' => $description,
            ]);
        }
        header('Location: /admin/committees.php');
        exit;
    }
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare('DELETE FROM committees WHERE id = :id');
    $stmt->execute(['id' => $id]);
    header('Location: /admin/committees.php');
    exit;
}

$editCommittee = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare('SELECT * FROM committees WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $editCommittee = $stmt->fetch();
}

$committees = $pdo->query('SELECT * FROM committees ORDER BY created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin · Committees</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background:#020617; color:#e5e7eb; }
        main { max-width:960px; margin:2rem auto; padding:1.5rem; background:#020617; border-radius:0.75rem; border:1px solid rgba(148,163,184,.4); }
        h1 { margin-top:0; font-size:1.5rem; }
        table { width:100%; border-collapse:collapse; margin-top:1rem; font-size:0.9rem; }
        th, td { padding:0.5rem; border-bottom:1px solid rgba(148,163,184,.3); text-align:left; }
        a { color:#7dd3fc; text-decoration:none; }
        input, textarea { width:100%; padding:0.4rem 0.5rem; border-radius:0.4rem; border:1px solid rgba(148,163,184,.4); background:#020617; color:#e5e7eb; }
        textarea { min-height:80px; }
        button { padding:0.4rem 0.8rem; border-radius:999px; border:none; background:#38bdf8; color:#0f172a; cursor:pointer; font-size:0.85rem; }
        .error { color:#fecaca; font-size:0.8rem; margin-top:0.5rem; }
        .top-bar { display:flex; justify-content:space-between; align-items:center; }
    </style>
</head>
<body>
<main>
    <div class="top-bar">
        <h1>Admin · Committees</h1>
        <div>
            <a href="<?php echo cfp_url('index.php'); ?>">← Home</a>
        </div>
    </div>

    <section>
        <h2 style="font-size:1rem; margin-top:1.5rem;">Existing committees</h2>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($committees as $c): ?>
                <tr>
                    <td><?php echo (int)$c['id']; ?></td>
                    <td><?php echo e($c['name']); ?></td>
                    <td><?php echo e($c['description'] ?? ''); ?></td>
                    <td>
                        <a href="<?php echo cfp_url('admin/committees.php?action=edit&amp;id=' . (int)$c['id']); ?>">Edit</a>
                        &nbsp;·&nbsp;
                        <a href="<?php echo cfp_url('admin/committees.php?action=delete&amp;id=' . (int)$c['id']); ?>" onclick="return confirm('Delete this committee?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section>
        <h2 style="font-size:1rem; margin-top:1.5rem;"><?php echo $editCommittee ? 'Edit committee' : 'Add committee'; ?></h2>
        <?php if ($error): ?><div class="error"><?php echo e($error); ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="id" value="<?php echo $editCommittee ? (int)$editCommittee['id'] : 0; ?>">
            <label>
                Name
                <input type="text" name="name" required value="<?php echo $editCommittee ? e($editCommittee['name']) : ''; ?>">
            </label>
            <label>
                Description
                <textarea name="description"><?php echo $editCommittee ? e($editCommittee['description'] ?? '') : ''; ?></textarea>
            </label>
            <div style="margin-top:0.8rem;">
                <button type="submit"><?php echo $editCommittee ? 'Update committee' : 'Create committee'; ?></button>
                <?php if ($editCommittee): ?>
                    <a href="<?php echo cfp_url('admin/committees.php'); ?>" style="margin-left:0.5rem; font-size:0.85rem;">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </section>
</main>
</body>
</html>


