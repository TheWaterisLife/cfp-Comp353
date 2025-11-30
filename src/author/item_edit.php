<?php
// Author: Sem Axil Rais (40113324)

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

cfp_require_role('author');

$pdo = cfp_get_pdo();
$user = cfp_current_user();

$itemId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$message = '';
$item = null;

if ($itemId > 0) {
    $stmt = $pdo->prepare('
        SELECT i.id, i.title, i.description, i.file_path, i.upload_date, s.code AS status_code
        FROM items i
        JOIN item_statuses s ON i.status_id = s.id
        WHERE i.id = :id AND i.author_id = :aid
    ');
    $stmt->execute(['id' => $itemId, 'aid' => $user['id']]);
    $item = $stmt->fetch();
}

if (!$item) {
    http_response_code(404);
    echo 'Item not found or you do not have permission to edit it.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $filePath = trim($_POST['file_path'] ?? '');

    if ($title === '' || $filePath === '') {
        $error = 'Title and file path are required.';
    } else {
        $upd = $pdo->prepare('
            UPDATE items
            SET title = :title,
                description = :description,
                file_path = :file_path
            WHERE id = :id AND author_id = :aid
        ');
        $upd->execute([
            'title'       => $title,
            'description' => $description !== '' ? $description : null,
            'file_path'   => $filePath,
            'id'          => $itemId,
            'aid'         => $user['id'],
        ]);

        $message = 'Item updated.';

        // Refresh data for display
        $stmt->execute(['id' => $itemId, 'aid' => $user['id']]);
        $item = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Author · Edit item</title>
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
            <h1 class="cfp-h1">Edit item</h1>
            <p class="cfp-muted">
                Item #<?php echo (int)$item['id']; ?> · current status: <?php echo e($item['status_code']); ?>
            </p>

            <?php if ($error): ?>
                <div class="cfp-alert cfp-alert-error" style="margin-top:0.75rem;"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="cfp-alert cfp-alert-success" style="margin-top:0.75rem;"><?php echo e($message); ?></div>
            <?php endif; ?>

            <form method="post" style="margin-top:1rem;">
                <label class="cfp-label">
                    Title
                    <input class="cfp-input" type="text" name="title"
                           value="<?php echo e($item['title']); ?>" required>
                </label>
                <label class="cfp-label">
                    Description
                    <textarea class="cfp-textarea" name="description"><?php echo e($item['description'] ?? ''); ?></textarea>
                </label>
                <label class="cfp-label">
                    File path (placeholder)
                    <input class="cfp-input" type="text" name="file_path"
                           value="<?php echo e($item['file_path']); ?>" required>
                </label>
                <button class="cfp-btn cfp-btn-primary" type="submit" style="margin-top:0.75rem;">Save changes</button>
            </form>

            <p style="margin-top:1rem; font-size:0.85rem;">
                <a href="<?php echo cfp_url('author/items.php'); ?>">← Back to my items</a>
            </p>
        </section>
    </div>
</main>
</body>
</html>


