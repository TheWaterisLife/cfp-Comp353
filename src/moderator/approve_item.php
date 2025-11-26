<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

cfp_require_role('moderator');

$pdo = cfp_get_pdo();
$user = cfp_current_user();
$error = '';

// Approve / reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $action = $_POST['action'] ?? '';

    if ($itemId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
        $error = 'Invalid action.';
    } else {
        $statusCode = $action === 'approve' ? 'approved' : 'rejected';
        $statusStmt = $pdo->prepare('SELECT id FROM item_statuses WHERE code = :code');
        $statusStmt->execute(['code' => $statusCode]);
        $statusId = $statusStmt->fetchColumn();

        if (!$statusId) {
            $error = 'Status not configured.';
        } else {
            $pdo->beginTransaction();

            $update = $pdo->prepare('UPDATE items SET status_id = :status_id WHERE id = :id');
            $update->execute([
                'status_id' => $statusId,
                'id'        => $itemId,
            ]);

            if ($action === 'approve') {
                // Mark latest version as approved
                $versionStmt = $pdo->prepare('
                    SELECT id
                    FROM item_versions
                    WHERE item_id = :item_id
                    ORDER BY version_number DESC
                    LIMIT 1
                ');
                $versionStmt->execute(['item_id' => $itemId]);
                $versionId = $versionStmt->fetchColumn();

                if ($versionId) {
                    $pdo->prepare('
                        UPDATE item_versions
                        SET approved_by = :moderator_id, approved_on = NOW()
                        WHERE id = :id
                    ')->execute([
                        'moderator_id' => $user['id'],
                        'id'           => $versionId,
                    ]);
                }
            }

            // Log moderation action
            $actionName = $action === 'approve' ? 'approve_item' : 'reject_item';
            $pdo->prepare('
                INSERT INTO moderation_logs (moderator_id, item_id, member_id, action, details, created_on)
                VALUES (:moderator_id, :item_id, NULL, :action, NULL, NOW())
            ')->execute([
                'moderator_id' => $user['id'],
                'item_id'      => $itemId,
                'action'       => $actionName,
            ]);

            $pdo->commit();
        }
    }
}

// List pending items
$pendingStatusStmt = $pdo->prepare('SELECT id FROM item_statuses WHERE code = :code');
$pendingStatusStmt->execute(['code' => 'pending_review']);
$pendingStatusId = $pendingStatusStmt->fetchColumn();

$pendingItems = [];
if ($pendingStatusId) {
    $stmt = $pdo->prepare('
        SELECT i.id, i.title, i.upload_date, m.name AS author_name
        FROM items i
        JOIN authors a ON i.author_id = a.member_id
        JOIN members m ON a.member_id = m.id
        WHERE i.status_id = :status_id
        ORDER BY i.upload_date DESC
    ');
    $stmt->execute(['status_id' => $pendingStatusId]);
    $pendingItems = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Moderator · Approve items</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background:#020617; color:#e5e7eb; }
        main { max-width:960px; margin:2rem auto; padding:1.5rem; background:#020617; border-radius:0.75rem; border:1px solid rgba(148,163,184,.4); }
        h1 { margin-top:0; font-size:1.5rem; }
        table { width:100%; border-collapse:collapse; margin-top:1rem; font-size:0.9rem; }
        th, td { padding:0.5rem; border-bottom:1px solid rgba(148,163,184,.3); text-align:left; }
        button { padding:0.3rem 0.7rem; border-radius:999px; border:none; cursor:pointer; font-size:0.8rem; }
        .approve { background:#22c55e; color:#022c22; }
        .reject { background:#ef4444; color:#fef2f2; }
        a { color:#7dd3fc; text-decoration:none; }
        .error { color:#fecaca; font-size:0.85rem; margin-top:0.5rem; }
        .top-bar { display:flex; justify-content:space-between; align-items:center; }
    </style>
</head>
<body>
<main>
    <div class="top-bar">
        <h1>Moderator · Pending approvals</h1>
        <div>
            <a href="<?php echo cfp_url('index.php'); ?>">← Home</a>
        </div>
    </div>

    <?php if ($error): ?><div class="error"><?php echo e($error); ?></div><?php endif; ?>

    <?php if (!$pendingItems): ?>
        <p>No items are currently awaiting review.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Author</th>
                <th>Uploaded</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($pendingItems as $item): ?>
                <tr>
                    <td><?php echo (int)$item['id']; ?></td>
                    <td><?php echo e($item['title']); ?></td>
                    <td><?php echo e($item['author_name']); ?></td>
                    <td><?php echo e($item['upload_date']); ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="item_id" value="<?php echo (int)$item['id']; ?>">
                            <button class="approve" type="submit" name="action" value="approve">Approve</button>
                        </form>
                        <form method="post" style="display:inline; margin-left:0.25rem;">
                            <input type="hidden" name="item_id" value="<?php echo (int)$item['id']; ?>">
                            <button class="reject" type="submit" name="action" value="reject">Reject</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>
</body>
</html>


