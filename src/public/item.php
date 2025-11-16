<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = cfp_get_pdo();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item = null;
$comments = [];

if ($id > 0) {
    $stmt = $pdo->prepare('
        SELECT i.id, i.title, i.description, i.upload_date, i.file_path,
               m.name AS author_name
        FROM items i
        JOIN authors a ON i.author_id = a.member_id
        JOIN members m ON a.member_id = m.id
        JOIN item_statuses s ON i.status_id = s.id
        WHERE i.id = :id AND s.code = "approved"
    ');
    $stmt->execute(['id' => $id]);
    $item = $stmt->fetch();

    if ($item) {
        $cStmt = $pdo->prepare('
            SELECT c.content, c.created_on, m.name AS author_name
            FROM comments c
            JOIN members m ON c.author_id = m.id
            WHERE c.item_id = :id
            ORDER BY c.created_on DESC
            LIMIT 20
        ');
        $cStmt->execute(['id' => $id]);
        $comments = $cStmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $item ? e($item['title']) . ' · CFP' : 'Item not found · CFP'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/main.css">
    <script src="/assets/js/main.js" defer></script>
</head>
<body class="cfp-shell">
<header class="cfp-header">
    <div class="cfp-header-inner">
        <div class="cfp-logo"><a href="/index.php">CopyForward Publishing</a></div>
        <nav class="cfp-nav">
            <a href="/index.php">Home</a>
            <a href="/search.php">Search</a>
        </nav>
        <div style="font-size:0.8rem;">
            <?php if (cfp_is_logged_in()): ?>
                <?php $u = cfp_current_user(); ?>
                <?php echo e($u['name']); ?> (<?php echo e(cfp_current_role() ?? ''); ?>)
                &nbsp;·&nbsp;
                <a href="/logout.php">Logout</a>
            <?php else: ?>
                <a href="/login.php">Login</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<main class="cfp-main">
    <div class="cfp-main-inner">
        <section class="cfp-panel">
            <?php if (!$item): ?>
                <h1 class="cfp-h1">Item not found</h1>
                <p class="cfp-muted">The requested item is not available or has not been approved.</p>
            <?php else: ?>
                <h1 class="cfp-h1"><?php echo e($item['title']); ?></h1>
                <p class="cfp-muted">
                    by <?php echo e($item['author_name']); ?> · uploaded <?php echo e($item['upload_date']); ?>
                </p>
                <p style="margin-top:0.75rem;">
                    <?php echo nl2br(e($item['description'] ?? '')); ?>
                </p>
                <div style="margin-top:0.75rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
                    <a class="cfp-btn cfp-btn-primary" href="/member/download.php?item_id=<?php echo (int)$item['id']; ?>">Download</a>
                    <a class="cfp-btn cfp-btn-outline" href="/member/donate.php?item_id=<?php echo (int)$item['id']; ?>">Donate</a>
                </div>

                <div style="margin-top:1.5rem;">
                    <h2 style="font-size:0.95rem;">Comments</h2>
                    <ul id="cfp-comments-list" class="cfp-list">
                        <?php foreach ($comments as $c): ?>
                            <li>
                                <strong><?php echo e($c['author_name']); ?></strong>
                                <span class="cfp-muted" style="font-size:0.75rem;"> · <?php echo e($c['created_on']); ?></span><br>
                                <span><?php echo nl2br(e($c['content'])); ?></span>
                            </li>
                        <?php endforeach; ?>
                        <?php if (!$comments): ?>
                            <li class="cfp-muted">No comments yet.</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <?php if (cfp_is_logged_in()): ?>
                    <div style="margin-top:1rem;">
                        <h3 style="font-size:0.9rem;">Add a comment</h3>
                        <div id="cfp-comment-message"></div>
                        <form method="post" action="/item_comment.php" data-cfp-comment-ajax="1">
                            <input type="hidden" name="item_id" value="<?php echo (int)$item['id']; ?>">
                            <label class="cfp-label">
                                Comment
                                <textarea class="cfp-textarea" name="content" required></textarea>
                            </label>
                            <button class="cfp-btn cfp-btn-primary" type="submit" style="margin-top:0.6rem;">Post comment</button>
                        </form>
                    </div>
                <?php else: ?>
                    <p class="cfp-muted" style="margin-top:1rem;">
                        <a href="/login.php">Log in</a> to post a comment.
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>


