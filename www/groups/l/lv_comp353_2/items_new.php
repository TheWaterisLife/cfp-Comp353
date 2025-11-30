<?php
// Author: Sem Axil Rais (40113324)

require_once __DIR__ . '/bootstrap.php';

$pdo = cfp_get_pdo();

// New items: approved items ordered by upload date (newest first).
$stmt = $pdo->prepare('
    SELECT i.id,
           i.title,
           i.description,
           i.upload_date,
           m.name AS author_name
    FROM items i
    JOIN authors a ON i.author_id = a.member_id
    JOIN members m ON a.member_id = m.id
    JOIN item_statuses s ON i.status_id = s.id
    WHERE s.code = "approved"
    ORDER BY i.upload_date DESC
    LIMIT 50
');
$stmt->execute();
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New items · CFP</title>
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
    </div>
</header>
<main class="cfp-main">
    <div class="cfp-main-inner">
        <section class="cfp-panel">
            <h1 class="cfp-h1">New items</h1>
            <p class="cfp-muted">Most recently approved items in CFP.</p>

            <?php if (!$items): ?>
                <p class="cfp-muted" style="margin-top:1rem;">No approved items are available yet.</p>
            <?php else: ?>
                <div class="cfp-grid" style="margin-top:1rem;">
                    <?php foreach ($items as $item): ?>
                        <article class="cfp-panel" style="padding:0.8rem 1rem; box-shadow:none;">
                            <h2 style="margin:0 0 0.25rem; font-size:1rem;">
                                <a href="<?php echo cfp_url('item.php?id=' . (int)$item['id']); ?>"><?php echo e($item['title']); ?></a>
                            </h2>
                            <p class="cfp-muted" style="margin:0 0 0.25rem; font-size:0.85rem;">
                                by <?php echo e($item['author_name']); ?> · uploaded <?php echo e($item['upload_date']); ?>
                            </p>
                            <p style="margin:0.25rem 0 0.5rem; font-size:0.86rem;">
                                <?php echo e(mb_strimwidth($item['description'] ?? '', 0, 140, '…')); ?>
                            </p>
                            <div style="display:flex; gap:0.5rem; margin-top:0.35rem;">
                                <a class="cfp-btn cfp-btn-outline" href="<?php echo cfp_url('item.php?id=' . (int)$item['id']); ?>">View details</a>
                                <a class="cfp-btn cfp-btn-primary" href="<?php echo cfp_url('member/download.php?item_id=' . (int)$item['id']); ?>">Download</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>


