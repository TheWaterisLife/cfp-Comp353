<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Any logged-in member (of any role) can review and flag items.
cfp_require_login();

$pdo   = cfp_get_pdo();
$user  = cfp_current_user();
$error = '';
$message = '';

$authorId = isset($_GET['author_id']) ? (int)$_GET['author_id'] : 0;

if ($authorId <= 0) {
    http_response_code(404);
    echo 'Author not specified.';
    exit;
}

// Fetch author info and confirm they have at least one blacklisted item.
$authorStmt = $pdo->prepare('
    SELECT m.id,
           m.name,
           COUNT(CASE WHEN s.code = "blacklisted" THEN 1 END) AS blacklisted_count
    FROM authors a
    JOIN members m ON a.member_id = m.id
    LEFT JOIN items i ON i.author_id = a.member_id
    LEFT JOIN item_statuses s ON i.status_id = s.id
    WHERE a.member_id = :id
    GROUP BY m.id, m.name
');
$authorStmt->execute(['id' => $authorId]);
$author = $authorStmt->fetch();

if (!$author || (int)$author['blacklisted_count'] <= 0) {
    http_response_code(404);
    echo 'This author does not have any blacklisted items or does not exist.';
    exit;
}

// Handle "mark as suspicious" submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['flag_item_id'])) {
    $flagItemId = (int)($_POST['flag_item_id'] ?? 0);
    $reason     = trim($_POST['reason'] ?? '');

    if ($flagItemId <= 0) {
        $error = 'Invalid item selected.';
    } else {
        // Ensure the item belongs to this author and is not already blacklisted.
        $itemCheck = $pdo->prepare('
            SELECT i.id, i.title, s.code AS status_code
            FROM items i
            JOIN item_statuses s ON i.status_id = s.id
            WHERE i.id = :item_id
              AND i.author_id = :author_id
        ');
        $itemCheck->execute([
            'item_id'   => $flagItemId,
            'author_id' => $authorId,
        ]);
        $item = $itemCheck->fetch();

        if (!$item) {
            $error = 'Item not found for this author.';
        } elseif ($item['status_code'] === 'blacklisted') {
            $error = 'This item is already blacklisted.';
        } else {
            // Avoid opening duplicate open discussions for the same item
            // in the Plagiarism Review Committee (committee_id = 1 from seed data).
            $existing = $pdo->prepare('
                SELECT d.id
                FROM discussions d
                WHERE d.item_id = :item_id
                  AND d.committee_id = 1
                  AND d.status_id = (SELECT id FROM discussion_statuses WHERE code = "open")
                LIMIT 1
            ');
            $existing->execute(['item_id' => $flagItemId]);
            $existingDiscussion = $existing->fetchColumn();

            if ($existingDiscussion) {
                $error = 'This item is already under plagiarism review by the committee.';
            } else {
                $summaryLines = [
                    'Member "' . $user['name'] . '" (ID ' . $user['id'] . ') has flagged this item as suspicious/plagiarized.',
                ];
                if ($reason !== '') {
                    $summaryLines[] = 'Reason: ' . $reason;
                }

                $summary = implode("\n\n", $summaryLines);

                $insert = $pdo->prepare('
                    INSERT INTO discussions (
                        committee_id, item_id, status_id,
                        subject, content,
                        created_by, created_on
                    )
                    VALUES (
                        1,
                        :item_id,
                        (SELECT id FROM discussion_statuses WHERE code = "open"),
                        :subject,
                        :content,
                        :created_by,
                        NOW()
                    )
                ');
                $insert->execute([
                    'item_id'    => $flagItemId,
                    'subject'    => 'Member suspicion report for item #' . $flagItemId,
                    'content'    => $summary,
                    'created_by' => $user['id'],
                ]);

                $message = 'Thank you. Your suspicion report has been sent to the plagiarism review committee.';
            }
        }
    }
}

// Fetch other (non-blacklisted) items by this author for review.
$itemsStmt = $pdo->prepare('
    SELECT i.id,
           i.title,
           i.upload_date,
           s.code AS status_code
    FROM items i
    JOIN item_statuses s ON i.status_id = s.id
    WHERE i.author_id = :author_id
      AND s.code <> "blacklisted"
    ORDER BY i.upload_date DESC
');
$itemsStmt->execute(['author_id' => $authorId]);
$items = $itemsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review items by blacklisted author · CFP</title>
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
            <?php echo e($user['name']); ?> (<?php echo e(cfp_current_role() ?? ''); ?>)
            &nbsp;·&nbsp;
            <a href="<?php echo cfp_url('logout.php'); ?>">Logout</a>
        </div>
    </div>
</header>
<main class="cfp-main">
    <div class="cfp-main-inner">
        <section class="cfp-panel">
            <h1 class="cfp-h1">Review other items by blacklisted author</h1>
            <p class="cfp-muted">
                Author: <?php echo e($author['name']); ?> ·
                Blacklisted items: <?php echo (int)$author['blacklisted_count']; ?>
            </p>

            <?php if ($error): ?>
                <div class="cfp-alert cfp-alert-error"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="cfp-alert cfp-alert-success"><?php echo e($message); ?></div>
            <?php endif; ?>

            <?php if (!$items): ?>
                <p class="cfp-muted" style="margin-top:1rem;">
                    This author has no other items available for review.
                </p>
            <?php else: ?>
                <p class="cfp-muted" style="margin-top:1rem;">
                    Browse this author's other items and flag any that look suspicious or plagiarized.
                    Your reports will be sent to the plagiarism review committee for follow-up.
                </p>

                <div class="cfp-grid cfp-grid-2" style="margin-top:1rem;">
                    <?php foreach ($items as $it): ?>
                        <article class="cfp-panel" style="padding:0.9rem 1rem; box-shadow:none;">
                            <h2 style="margin:0 0 0.25rem; font-size:1rem;"><?php echo e($it['title']); ?></h2>
                            <p class="cfp-muted" style="margin:0 0 0.25rem; font-size:0.8rem;">
                                Uploaded <?php echo e($it['upload_date']); ?> · Status: <?php echo e($it['status_code']); ?>
                            </p>
                            <div style="margin-top:0.5rem;">
                                <form method="post">
                                    <input type="hidden" name="flag_item_id" value="<?php echo (int)$it['id']; ?>">
                                    <label class="cfp-label" style="font-size:0.8rem;">
                                        Optional reason
                                        <textarea class="cfp-textarea" name="reason" rows="2"
                                                  placeholder="Briefly explain why this item seems suspicious (optional)."></textarea>
                                    </label>
                                    <button class="cfp-btn cfp-btn-outline" type="submit"
                                            style="margin-top:0.5rem; font-size:0.8rem;">
                                        Mark as suspicious / plagiarized
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p style="margin-top:1.5rem; font-size:0.85rem;">
                <a href="<?php echo cfp_url('index.php'); ?>">← Back to home</a>
            </p>
        </section>
    </div>
</main>
</body>
</html>


