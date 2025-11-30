<?php
// Author: Sem Axil Rais (40113324)

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

cfp_require_role('author');

$pdo = cfp_get_pdo();
$user = cfp_current_user();
$error = '';
$message = '';

// Handle delete (soft delete via status code "removed")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item_id'])) {
    $itemId = (int)($_POST['delete_item_id'] ?? 0);

    if ($itemId > 0) {
        // Ensure the item belongs to this author
        $stmt = $pdo->prepare('SELECT id FROM items WHERE id = :id AND author_id = :aid');
        $stmt->execute(['id' => $itemId, 'aid' => $user['id']]);
        $ownItem = $stmt->fetchColumn();

        if ($ownItem) {
            // Look up removed status; if not present, fall back to draft.
            $statusStmt = $pdo->prepare('SELECT id FROM item_statuses WHERE code = :code');
            $statusStmt->execute(['code' => 'removed']);
            $removedStatusId = $statusStmt->fetchColumn();

            if (!$removedStatusId) {
                $statusStmt->execute(['code' => 'draft']);
                $removedStatusId = $statusStmt->fetchColumn();
            }

            if ($removedStatusId) {
                $upd = $pdo->prepare('UPDATE items SET status_id = :sid WHERE id = :id');
                $upd->execute(['sid' => $removedStatusId, 'id' => $itemId]);
                $message = 'Item has been removed from public listings.';
            } else {
                $error = 'Unable to update item status; status code not configured.';
            }
        } else {
            $error = 'You are not allowed to modify this item.';
        }
    }
}

// Fetch items belonging to this author
$itemsStmt = $pdo->prepare('
    SELECT i.id,
           i.title,
           i.upload_date,
           s.code AS status_code
    FROM items i
    JOIN item_statuses s ON i.status_id = s.id
    WHERE i.author_id = :aid
    ORDER BY i.upload_date DESC
    LIMIT 50
');
$itemsStmt->execute(['aid' => $user['id']]);
$items = $itemsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Author · My items</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo cfp_url('assets/css/main.css'); ?>">
</head>
<body class="cfp-shell">
<header class="cfp-header">
    <div class="cfp-header-inner">
        <div class="cfp-logo"><a href="<?php echo cfp_url('index.php'); ?>">CopyForward Publishing</a></div>
        <nav class="cfp-nav">
            <a href="<?php echo cfp_url('index.php'); ?>">Home</a>
            <a href="<?php echo cfp_url('search.php'); ?>">Search</a>
        </nav>
        <div style="font-size:0.8rem;">
            <?php echo e($user['name']); ?> (author)
            &nbsp;·&nbsp;
            <a href="<?php echo cfp_url('logout.php'); ?>">Logout</a>
        </div>
    </div>
</header>
<main class="cfp-main">
    <div class="cfp-main-inner">
        <section class="cfp-panel">
            <h1 class="cfp-h1">My items</h1>
            <p class="cfp-muted">
                Manage items you have submitted to CFP: view status, edit metadata, or remove items from public listings.
            </p>

            <?php if ($error): ?>
                <div class="cfp-alert cfp-alert-error" style="margin-top:0.75rem;"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="cfp-alert cfp-alert-success" style="margin-top:0.75rem;"><?php echo e($message); ?></div>
            <?php endif; ?>

            <p style="margin-top:0.75rem; font-size:0.85rem;">
                <a class="cfp-btn cfp-btn-outline" href="<?php echo cfp_url('author/upload_item.php'); ?>">Upload new item</a>
            </p>

            <table class="cfp-table" style="margin-top:1rem;">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Uploaded</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo (int)$item['id']; ?></td>
                        <td><?php echo e($item['title']); ?></td>
                        <td><?php echo e($item['upload_date']); ?></td>
                        <td><?php echo e($item['status_code']); ?></td>
                        <td>
                            <a class="cfp-link" href="<?php echo cfp_url('author/item_edit.php?id=' . (int)$item['id']); ?>">Edit</a>
                            <?php if ($item['status_code'] !== 'removed' && $item['status_code'] !== 'blacklisted'): ?>
                                &nbsp;·&nbsp;
                                <form method="post" style="display:inline;" onsubmit="return confirm('Remove this item from public listings?');">
                                    <input type="hidden" name="delete_item_id" value="<?php echo (int)$item['id']; ?>">
                                    <button class="cfp-link" type="submit" style="background:none; border:none; padding:0; color:#fca5a5; cursor:pointer;">
                                        Remove
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$items): ?>
                    <tr><td colspan="5" class="cfp-muted">You have not submitted any items yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>
</main>
</body>
</html>


