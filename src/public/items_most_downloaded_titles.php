<?php
require_once __DIR__ . '/bootstrap.php';

$pdo = cfp_get_pdo();

// Most downloaded titles: group by title across all approved items.
$stmt = $pdo->prepare('
    SELECT i.title,
           m.name AS primary_author_name,
           COUNT(d.id) AS downloads
    FROM items i
    JOIN authors a ON i.author_id = a.member_id
    JOIN members m ON a.member_id = m.id
    JOIN item_statuses s ON i.status_id = s.id
    LEFT JOIN downloads d ON d.item_id = i.id
    WHERE s.code = "approved"
    GROUP BY i.title, m.name
    ORDER BY downloads DESC, i.title ASC
    LIMIT 50
');
$stmt->execute();
$rows = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Most downloaded titles · CFP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="cfp-shell">
<header class="cfp-header">
    <div class="cfp-header-inner">
        <div class="cfp-logo"><a href="/index.php">CopyForward Publishing</a></div>
        <nav class="cfp-nav">
            <a href="/index.php">Home</a>
            <a href="/search.php">Search</a>
        </nav>
    </div>
</header>
<main class="cfp-main">
    <div class="cfp-main-inner">
        <section class="cfp-panel">
            <h1 class="cfp-h1">Most downloaded titles</h1>
            <p class="cfp-muted">Titles with the highest total number of downloads.</p>

            <table class="cfp-table" style="margin-top:1rem;">
                <thead>
                <tr>
                    <th>Title</th>
                    <th>Primary author</th>
                    <th>Downloads</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo e($row['title']); ?></td>
                        <td><?php echo e($row['primary_author_name']); ?></td>
                        <td><?php echo (int)$row['downloads']; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="3" class="cfp-muted">No downloads recorded yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>
</main>
</body>
</html>


