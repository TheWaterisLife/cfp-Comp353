<?php
// Author: Zaree Choudhry Hameed (21026488)

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';

cfp_require_role('moderator');

$pdo = cfp_get_pdo();
$user = cfp_current_user();

$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$error = '';
$message = '';

// Fetch item for context
$item = null;
if ($itemId > 0) {
    $stmt = $pdo->prepare('
        SELECT i.id, i.title, m.name AS author_name
        FROM items i
        JOIN authors a ON i.author_id = a.member_id
        JOIN members m ON a.member_id = m.id
        WHERE i.id = :id
    ');
    $stmt->execute(['id' => $itemId]);
    $item = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $summary = trim($_POST['summary'] ?? '');

    if ($itemId <= 0 || $summary === '') {
        $error = 'Item and summary are required.';
    } else {
        // For now we assume committee_id = 1 is the Plagiarism Review Committee (from seed.sql).
        $committeeId = 1;

        $pdo->prepare('
            INSERT INTO discussions (committee_id, item_id, status_id, subject, content, created_by, created_on)
            VALUES (
                :committee_id,
                :item_id,
                (SELECT id FROM discussion_statuses WHERE code = "open"),
                :subject,
                :content,
                :created_by,
                NOW()
            )
        ')->execute([
            'committee_id' => $committeeId,
            'item_id'      => $itemId,
            'subject'      => 'Plagiarism report for item #' . $itemId,
            'content'      => $summary,
            'created_by'   => $user['id'],
        ]);

        $message = 'Plagiarism report submitted and discussion opened for the committee.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Moderator · Plagiarism report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo cfp_url('assets/css/main.css'); ?>">
</head>
<body class="cfp-shell">
<header class="cfp-header">
    <div class="cfp-header-inner">
        <div class="cfp-logo"><a href="<?php echo cfp_url('index.php'); ?>">CopyForward Publishing</a></div>
        <nav class="cfp-nav">
            <a href="<?php echo cfp_url('index.php'); ?>">Home</a>
        </nav>
    </div>
</header>
<main class="cfp-main">
    <div class="cfp-main-inner">
        <section class="cfp-panel">
            <h1 class="cfp-h1">Submit plagiarism report</h1>
            <?php if ($item): ?>
                <p class="cfp-muted">
                    Item: <strong><?php echo e($item['title']); ?></strong> by <?php echo e($item['author_name']); ?>
                </p>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="cfp-alert cfp-alert-error"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="cfp-alert cfp-alert-success"><?php echo e($message); ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="item_id" value="<?php echo (int)$itemId; ?>">
                <label class="cfp-label">
                    Summary of concern
                    <textarea class="cfp-textarea" name="summary" required></textarea>
                </label>
                <button class="cfp-btn cfp-btn-primary" type="submit" style="margin-top:0.7rem;">Open committee discussion</button>
            </form>
        </section>
    </div>
</main>
</body>
</html>


