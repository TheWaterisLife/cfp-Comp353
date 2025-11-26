<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';

cfp_require_role('moderator');

$pdo = cfp_get_pdo();
$user = cfp_current_user();

// New items awaiting approval
$pendingItems = [];
$pendingStatusStmt = $pdo->prepare('SELECT id FROM item_statuses WHERE code = :code');
$pendingStatusStmt->execute(['code' => 'pending_review']);
$pendingStatusId = $pendingStatusStmt->fetchColumn();

if ($pendingStatusId) {
    $stmt = $pdo->prepare('
        SELECT i.id, i.title, i.upload_date,
               m.name AS author_name
        FROM items i
        JOIN authors a ON i.author_id = a.member_id
        JOIN members m ON a.member_id = m.id
        WHERE i.status_id = :status_id
        ORDER BY i.upload_date DESC
        LIMIT 20
    ');
    $stmt->execute(['status_id' => $pendingStatusId]);
    $pendingItems = $stmt->fetchAll();
}

// Open plagiarism cases / discussions
$openDiscussionsStmt = $pdo->prepare('
    SELECT d.id, d.created_on, ct.name AS committee_name,
           i.id AS item_id, i.title AS item_title
    FROM discussions d
    JOIN committees ct ON d.committee_id = ct.id
    JOIN items i ON d.item_id = i.id
    JOIN discussion_statuses ds ON d.status_id = ds.id
    WHERE ds.code = "open"
    ORDER BY d.created_on DESC
    LIMIT 20
');
$openDiscussionsStmt->execute();
$openDiscussions = $openDiscussionsStmt->fetchAll();

// Blacklisted items and authors
$blacklistedItemsStmt = $pdo->prepare('
    SELECT i.id, i.title, i.upload_date,
           m.id AS author_id, m.name AS author_name
    FROM items i
    JOIN item_statuses s ON i.status_id = s.id
    JOIN authors a ON i.author_id = a.member_id
    JOIN members m ON a.member_id = m.id
    WHERE s.code = "blacklisted"
    ORDER BY i.upload_date DESC
    LIMIT 20
');
$blacklistedItemsStmt->execute();
$blacklistedItems = $blacklistedItemsStmt->fetchAll();

$blacklistedAuthorsStmt = $pdo->prepare('
    SELECT m.id AS author_id, m.name AS author_name,
           COUNT(*) AS blacklisted_items
    FROM items i
    JOIN item_statuses s ON i.status_id = s.id
    JOIN authors a ON i.author_id = a.member_id
    JOIN members m ON a.member_id = m.id
    WHERE s.code = "blacklisted"
    GROUP BY m.id, m.name
    ORDER BY blacklisted_items DESC, m.name ASC
    LIMIT 10
');
$blacklistedAuthorsStmt->execute();
$blacklistedAuthors = $blacklistedAuthorsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Moderator · Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="cfp-shell">
<header class="cfp-header">
    <div class="cfp-header-inner">
        <div class="cfp-logo"><a href="/index.php">CopyForward Publishing</a></div>
        <nav class="cfp-nav">
            <a href="/index.php">Home</a>
        </nav>
        <div style="font-size:0.8rem;">
            <?php echo e($user['name']); ?> (moderator)
            &nbsp;·&nbsp;
            <a href="/logout.php">Logout</a>
        </div>
    </div>
</header>
<main class="cfp-main">
    <div class="cfp-main-inner">
        <section class="cfp-panel">
            <h1 class="cfp-h1">Moderator dashboard</h1>
            <p class="cfp-muted">
                Overview of items awaiting approval, open plagiarism cases, and blacklisted content/authors.
            </p>

            <div class="cfp-grid cfp-grid-2" style="margin-top:1.5rem;">
                <div>
                    <h2 style="font-size:0.95rem;">New items awaiting approval</h2>
                    <?php if (!$pendingItems): ?>
                        <p class="cfp-muted">No items are currently awaiting review.</p>
                    <?php else: ?>
                        <table class="cfp-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Uploaded</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pendingItems as $i): ?>
                                <tr>
                                    <td><?php echo (int)$i['id']; ?></td>
                                    <td><?php echo e($i['title']); ?></td>
                                    <td><?php echo e($i['author_name']); ?></td>
                                    <td><?php echo e($i['upload_date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p style="margin-top:0.5rem; font-size:0.8rem;">
                            <a href="/moderator/approve_item.php">Go to approvals →</a>
                        </p>
                    <?php endif; ?>
                </div>
                <div>
                    <h2 style="font-size:0.95rem;">Open plagiarism cases</h2>
                    <?php if (!$openDiscussions): ?>
                        <p class="cfp-muted">No open discussions.</p>
                    <?php else: ?>
                        <table class="cfp-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Committee</th>
                                <th>Item</th>
                                <th>Opened</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($openDiscussions as $d): ?>
                                <tr>
                                    <td><?php echo (int)$d['id']; ?></td>
                                    <td><?php echo e($d['committee_name']); ?></td>
                                    <td><?php echo e($d['item_title']); ?></td>
                                    <td><?php echo e($d['created_on']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p style="margin-top:0.5rem; font-size:0.8rem;">
                            <a href="/moderator/plagiarism_report.php">Open new plagiarism report →</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cfp-grid cfp-grid-2" style="margin-top:1.5rem;">
                <div>
                    <h2 style="font-size:0.95rem;">Blacklisted items</h2>
                    <?php if (!$blacklistedItems): ?>
                        <p class="cfp-muted">No blacklisted items.</p>
                    <?php else: ?>
                        <table class="cfp-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Blacklisted on</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($blacklistedItems as $i): ?>
                                <tr>
                                    <td><?php echo (int)$i['id']; ?></td>
                                    <td><?php echo e($i['title']); ?></td>
                                    <td><?php echo e($i['author_name']); ?></td>
                                    <td><?php echo e($i['upload_date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <div>
                    <h2 style="font-size:0.95rem;">Authors with blacklisted items</h2>
                    <?php if (!$blacklistedAuthors): ?>
                        <p class="cfp-muted">No authors currently have blacklisted items.</p>
                    <?php else: ?>
                        <ul class="cfp-list">
                            <?php foreach ($blacklistedAuthors as $a): ?>
                                <li>
                                    <?php echo e($a['author_name']); ?>
                                    <span class="cfp-muted" style="font-size:0.8rem;">
                                        · <?php echo (int)$a['blacklisted_items']; ?> blacklisted item(s)
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</main>
</body>
</html>


