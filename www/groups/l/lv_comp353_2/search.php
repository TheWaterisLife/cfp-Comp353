<?php
// Author: Samy Belmihoub (40251504)

// Public entrypoint: locate shared includes via bootstrap so this file works
require_once __DIR__ . '/bootstrap.php';

$pdo = cfp_get_pdo();

$q = trim($_GET['q'] ?? '');
$results = [];

if ($q !== '') {
    $stmt = $pdo->prepare('
        SELECT i.id, i.title, i.description, i.upload_date, m.name AS author_name
        FROM items i
        JOIN authors a ON i.author_id = a.member_id
        JOIN members m ON a.member_id = m.id
        JOIN item_statuses s ON i.status_id = s.id
        WHERE s.code = "approved"
          AND (
              i.title LIKE :q
              OR i.description LIKE :q
              OR i.topic LIKE :q
              OR i.keywords LIKE :q
              OR m.name LIKE :q
          )
        ORDER BY i.upload_date DESC
        LIMIT 30
    ');
    $stmt->execute(['q' => '%' . $q . '%']);
    $results = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search · CFP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo cfp_url('assets/css/main.css'); ?>">
    <script src="<?php echo cfp_url('assets/js/main.js'); ?>" defer></script>
</head>
<body class="cfp-shell">
<header class="cfp-header">
    <div class="cfp-header-inner">
        <div class="cfp-logo">CopyForward Publishing</div>
        <nav class="cfp-nav">
            <a href="<?php echo cfp_url('index.php'); ?>">Home</a>
            <a href="<?php echo cfp_url('search.php'); ?>">Search</a>
        </nav>
        <div style="font-size:0.8rem;">
            <?php if (cfp_is_logged_in()): ?>
                <?php $u = cfp_current_user(); ?>
                <?php echo e($u['name']); ?> (<?php echo e(cfp_current_role() ?? ''); ?>)
                &nbsp;·&nbsp;
                <a href="<?php echo cfp_url('logout.php'); ?>">Logout</a>
            <?php else: ?>
                <a href="<?php echo cfp_url('login.php'); ?>">Login</a>
                &nbsp;·&nbsp;
                <a href="<?php echo cfp_url('register.php'); ?>">Register</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<main class="cfp-main">
    <div class="cfp-main-inner">
        <section class="cfp-panel">
            <h1 class="cfp-h1">Search</h1>
            <p class="cfp-muted">
                Find approved items by <strong>title</strong>, <strong>topic</strong>, <strong>keywords</strong>, or <strong>author name</strong>.
            </p>

            <form action="<?php echo cfp_url('search.php'); ?>" method="get" style="margin-top:1rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
                <input class="cfp-input" type="search" name="q" value="<?php echo e($q); ?>" placeholder="Search for algorithms, preservation, plagiarism…">
                <button class="cfp-btn cfp-btn-primary" type="submit">Search</button>
            </form>

            <?php if ($q === ''): ?>
                <p class="cfp-muted" style="margin-top:1rem;">Enter a search term to get started.</p>
            <?php else: ?>
                <h2 style="margin-top:1.25rem; font-size:0.95rem;">Results for "<?php echo e($q); ?>"</h2>
                <?php if (!$results): ?>
                    <p class="cfp-muted">No items matched your search.</p>
                <?php else: ?>
                    <div class="cfp-grid" style="margin-top:0.75rem;">
                        <?php foreach ($results as $item): ?>
                            <article class="cfp-panel" style="padding:0.8rem 1rem; box-shadow:none;">
                                <h3 style="margin:0 0 0.2rem; font-size:1rem;">
                                    <a href="<?php echo cfp_url('item.php?id=' . (int)$item['id']); ?>"><?php echo e($item['title']); ?></a>
                                </h3>
                                <p class="cfp-muted" style="margin:0 0 0.25rem;">
                                    by <?php echo e($item['author_name']); ?>
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
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>


