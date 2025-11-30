<?php
// Author: Zaree Choudhry Hameed (21026488)

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';

cfp_require_role('moderator');

$pdo = cfp_get_pdo();
$user = cfp_current_user();

$discussionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$message = '';

// Fetch discussion + item
$discussion = null;
if ($discussionId > 0) {
    $stmt = $pdo->prepare('
        SELECT d.*, ct.name AS committee_name, i.title AS item_title
        FROM discussions d
        JOIN committees ct ON d.committee_id = ct.id
        JOIN items i ON d.item_id = i.id
        WHERE d.id = :id
    ');
    $stmt->execute(['id' => $discussionId]);
    $discussion = $stmt->fetch();
}

if (!$discussion) {
    http_response_code(404);
    echo 'Discussion not found.';
    exit;
}

// Append a comment-style entry to the discussion content
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['append_note'])) {
    $note = trim($_POST['note'] ?? '');
    if ($note === '') {
        $error = 'Note text is required.';
    } else {
        $prefix = "\n\n---\n" . $user['name'] . ' (' . date('Y-m-d H:i') . "):\n";
        $newContent = $discussion['content'] . $prefix . $note;

        $pdo->prepare('UPDATE discussions SET content = :content WHERE id = :id')
            ->execute(['content' => $newContent, 'id' => $discussionId]);

        $discussion['content'] = $newContent;
        $message = 'Note added to discussion.';
    }
}

// Fetch votes for display
$votesStmt = $pdo->prepare('
    SELECT v.id, v.vote_date, vo.code AS vote_code, m.name AS voter_name
    FROM votes v
    JOIN vote_options vo ON v.vote_option_id = vo.id
    JOIN members m ON v.voter_id = m.id
    WHERE v.discussion_id = :id
    ORDER BY v.vote_date DESC
');
$votesStmt->execute(['id' => $discussionId]);
$votes = $votesStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Plagiarism discussion · CFP</title>
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
            <h1 class="cfp-h1">Plagiarism discussion</h1>
            <p class="cfp-muted">
                Committee: <?php echo e($discussion['committee_name']); ?> ·
                Item: <?php echo e($discussion['item_title']); ?>
            </p>

            <?php if ($error): ?>
                <div class="cfp-alert cfp-alert-error"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="cfp-alert cfp-alert-success"><?php echo e($message); ?></div>
            <?php endif; ?>

            <h2 style="font-size:0.95rem; margin-top:1rem;">Thread</h2>
            <pre style="white-space:pre-wrap; background:#020617; padding:0.75rem; border-radius:0.5rem; border:1px solid rgba(148,163,184,.4); font-size:0.85rem;"><?php echo e($discussion['content']); ?></pre>

            <form method="post" style="margin-top:1rem;">
                <label class="cfp-label">
                    Add note
                    <textarea class="cfp-textarea" name="note" required></textarea>
                </label>
                <button class="cfp-btn cfp-btn-primary" type="submit" name="append_note" value="1" style="margin-top:0.6rem;">Add to thread</button>
            </form>

            <div style="margin-top:1.5rem;">
                <h2 style="font-size:0.95rem;">Votes</h2>
                <ul class="cfp-list">
                    <?php foreach ($votes as $v): ?>
                        <li>
                            <strong><?php echo e($v['voter_name']); ?></strong>
                            <span class="cfp-muted" style="font-size:0.78rem;">
                                · <?php echo e($v['vote_code']); ?> · <?php echo e($v['vote_date']); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$votes): ?>
                        <li class="cfp-muted">No votes yet.</li>
                    <?php endif; ?>
                </ul>
            </div>

            <p style="margin-top:1rem; font-size:0.85rem;">
                <a href="<?php echo cfp_url('moderator/plagiarism_vote.php?discussion_id=' . (int)$discussionId); ?>">Go to voting page →</a>
            </p>
        </section>
    </div>
</main>
</body>
</html>


