<?php
// Author: Sem Axil Rais (40113324)

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

cfp_require_role('author');

$pdo = cfp_get_pdo();
$user = cfp_current_user();
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $fakePath = trim($_POST['file_path'] ?? '');

    if ($title === '' || $fakePath === '') {
        $error = 'Title and file path are required. (File upload UI will be enhanced in a later phase.)';
    } else {
        // Look up the author_id for the current member
        $stmt = $pdo->prepare('SELECT member_id FROM authors WHERE member_id = :mid');
        $stmt->execute(['mid' => $user['id']]);
        $author = $stmt->fetch();

        if (!$author) {
            $error = 'Your account is not registered as an author.';
        } else {
            // Create the item as pending_review
            $statusStmt = $pdo->prepare('SELECT id FROM item_statuses WHERE code = :code');
            $statusStmt->execute(['code' => 'pending_review']);
            $statusId = $statusStmt->fetchColumn();

            $stmt = $pdo->prepare("
                INSERT INTO items (author_id, title, description, file_path, upload_date, status_id, version_parent_id)
                VALUES (:author_id, :title, :description, :file_path, NOW(), :status_id, NULL)
            ");
            $stmt->execute([
                'author_id'   => $user['id'],
                'title'       => $title,
                'description' => $description,
                'file_path'   => $fakePath,
                'status_id'   => $statusId,
            ]);

            $itemId = (int)$pdo->lastInsertId();

            $stmtV = $pdo->prepare("
                INSERT INTO item_versions (item_id, version_number, file_path, approved_by, approved_on)
                VALUES (:item_id, 1, :file_path, NULL, NULL)
            ");
            $stmtV->execute([
                'item_id'   => $itemId,
                'file_path' => $fakePath,
            ]);

            $message = 'Item submitted for review.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Author · Upload item</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background:#020617; color:#e5e7eb; }
        main { max-width:800px; margin:2rem auto; padding:1.5rem; background:#020617; border-radius:0.75rem; border:1px solid rgba(148,163,184,.4); }
        h1 { margin-top:0; font-size:1.5rem; }
        label { display:block; margin-top:0.75rem; font-size:0.85rem; color:#9ca3af; }
        input, textarea { width:100%; padding:0.45rem 0.6rem; border-radius:0.4rem; border:1px solid rgba(148,163,184,.4); background:#020617; color:#e5e7eb; }
        textarea { min-height:120px; }
        button { margin-top:1rem; padding:0.6rem 1.1rem; border-radius:999px; border:none; background:#38bdf8; color:#0f172a; cursor:pointer; font-weight:600; }
        .error { margin-top:0.75rem; color:#fecaca; font-size:0.85rem; }
        .message { margin-top:0.75rem; color:#bbf7d0; font-size:0.85rem; }
        a { color:#7dd3fc; text-decoration:none; }
    </style>
</head>
<body>
<main>
    <h1>Upload new item</h1>
    <p style="font-size:0.85rem; color:#9ca3af;">
        Provide metadata and a file path placeholder. In later phases this will be replaced with
        a full file upload workflow.
    </p>
    <?php if ($error): ?><div class="error"><?php echo e($error); ?></div><?php endif; ?>
    <?php if ($message): ?><div class="message"><?php echo e($message); ?></div><?php endif; ?>

    <form method="post">
        <label>
            Title
            <input type="text" name="title" required>
        </label>
        <label>
            Description
            <textarea name="description"></textarea>
        </label>
        <label>
            File path (placeholder)
            <input type="text" name="file_path" placeholder="/files/items/your-file.pdf" required>
        </label>
        <button type="submit">Submit item</button>
    </form>

    <p style="margin-top:1rem; font-size:0.85rem;">
        <a href="/index.php">← Back to home</a>
    </p>
</main>
</body>
</html>


