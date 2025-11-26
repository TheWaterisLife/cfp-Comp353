<?php
require_once __DIR__ . '/bootstrap.php';

$pdo = cfp_get_pdo();

// Featured: newest approved items (short list).
$featuredStmt = $pdo->prepare('
    SELECT i.id, i.title, i.description, i.upload_date, m.name AS author_name
    FROM items i
    JOIN authors a ON i.author_id = a.member_id
    JOIN members m ON a.member_id = m.id
    JOIN item_statuses s ON i.status_id = s.id
    WHERE s.code = "approved"
    ORDER BY i.upload_date DESC
    LIMIT 5
');
$featuredStmt->execute();
$featuredItems = $featuredStmt->fetchAll();

// Authors list for home page.
$authorsStmt = $pdo->query('
    SELECT a.member_id, m.name
    FROM authors a
    JOIN members m ON a.member_id = m.id
    ORDER BY m.name
    LIMIT 20
');
$authors = $authorsStmt->fetchAll();

// Basic stats: counts of approved items, total downloads, total donations.
$stats = [
    'items'      => 0,
    'downloads'  => 0,
    'donations'  => 0.0,
];

$itemsCountStmt = $pdo->query('
    SELECT COUNT(*) AS cnt
    FROM items i
    JOIN item_statuses s ON i.status_id = s.id
    WHERE s.code = "approved"
');
$row = $itemsCountStmt->fetch();
if ($row) {
    $stats['items'] = (int)$row['cnt'];
}

$downloadsCountStmt = $pdo->query('SELECT COUNT(*) AS cnt FROM downloads');
$row = $downloadsCountStmt->fetch();
if ($row) {
    $stats['downloads'] = (int)$row['cnt'];
}

$donationsSumStmt = $pdo->query('SELECT COALESCE(SUM(amount), 0) AS total FROM donations');
$row = $donationsSumStmt->fetch();
if ($row) {
    $stats['donations'] = (float)$row['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CopyForward Publishing (CFP)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/main.css">
    <script src="/assets/js/main.js" defer></script>
</head>
<body class="cfp-shell">
<header class="cfp-header">
    <div class="cfp-header-inner">
        <div class="cfp-logo">CopyForward Publishing</div>
        <nav class="cfp-nav">
            <a href="/index.php">Home</a>
            <a href="/search.php">Search</a>
            <?php if (cfp_is_logged_in()): ?>
                <?php if (cfp_current_role() === 'admin'): ?>
                    <a href="/admin/members.php">Admin</a>
                <?php endif; ?>
                <?php if (cfp_current_role() === 'author'): ?>
                    <a href="/author/index.php">Author</a>
                <?php endif; ?>
                <a href="/member/profile.php">Profile</a>
                <a href="/member/messages.php">Messages</a>
            <?php endif; ?>
        </nav>
        <div style="font-size:0.8rem;">
            <?php if (cfp_is_logged_in()): ?>
                <?php $u = cfp_current_user(); ?>
                <?php echo e($u['name']); ?> (<?php echo e(cfp_current_role() ?? ''); ?>)
                &nbsp;·&nbsp;
                <a href="/logout.php">Logout</a>
            <?php else: ?>
                <a href="/login.php">Login</a>
                &nbsp;·&nbsp;
                <a href="/register.php">Register</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<main class="cfp-main">
    <div class="cfp-main-inner">
        <section class="cfp-panel">
            <div class="cfp-badge cfp-badge-pill">CFP · Phase 4–5 prototype</div>
            <h1 class="cfp-h1">Discover open-access items</h1>
            <p class="cfp-muted">
                Search, download, and support authors and charities through the CopyForward model.
            </p>

            <form action="/search.php" method="get" style="margin-top:1rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
                <input class="cfp-input" type="search" name="q" placeholder="Search by title or description…">
                <button class="cfp-btn cfp-btn-primary" type="submit">Search</button>
            </form>

            <div style="margin-top:1.5rem;">
                <h2 style="font-size:0.95rem; text-transform:uppercase; letter-spacing:0.16em; color:#9ca3af;">Featured items</h2>
                <div class="cfp-grid cfp-grid-2" style="margin-top:0.75rem;">
                    <?php if (!$featuredItems): ?>
                        <p class="cfp-muted">No approved items available yet.</p>
                    <?php else: ?>
                        <?php foreach ($featuredItems as $item): ?>
                            <article class="cfp-panel" style="padding:0.9rem 1rem; box-shadow:none;">
                                <h3 style="margin:0 0 0.25rem; font-size:1rem;"><?php echo e($item['title']); ?></h3>
                                <p class="cfp-muted" style="margin:0 0 0.25rem;">
                                    by <?php echo e($item['author_name']); ?>
                                </p>
                                <p style="margin:0.25rem 0 0.5rem; font-size:0.86rem;">
                                    <?php echo e(mb_strimwidth($item['description'] ?? '', 0, 120, '…')); ?>
                                </p>
                                <div style="display:flex; gap:0.5rem; margin-top:0.35rem;">
                                    <a class="cfp-btn cfp-btn-outline" href="/item.php?id=<?php echo (int)$item['id']; ?>">View details</a>
                                    <a class="cfp-btn cfp-btn-primary" href="/member/download.php?item_id=<?php echo (int)$item['id']; ?>">Download</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <p style="margin-top:0.75rem; font-size:0.85rem;">
                    Browse more:
                    <a href="/items_new.php">New items</a> ·
                    <a href="/items_popular.php">Popular items</a> ·
                    <a href="/items_most_downloaded_titles.php">Most downloaded titles</a>
                </p>
            </div>

            <div class="cfp-grid cfp-grid-2" style="margin-top:1.75rem;">
                <div>
                    <h2 style="font-size:0.9rem;">Authors</h2>
                    <?php if (!$authors): ?>
                        <p class="cfp-muted">No authors registered yet.</p>
                    <?php else: ?>
                        <ul class="cfp-list">
                            <?php foreach ($authors as $a): ?>
                                <li><?php echo e($a['name']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div>
                    <h2 style="font-size:0.9rem;">At a glance</h2>
                    <ul class="cfp-list">
                        <li>Total approved items: <strong><?php echo (int)$stats['items']; ?></strong></li>
                        <li>Total downloads: <strong><?php echo (int)$stats['downloads']; ?></strong></li>
                        <li>Total donations: <strong><?php echo number_format($stats['donations'], 2); ?></strong></li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</main>
</body>
</html>


