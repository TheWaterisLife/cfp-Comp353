<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';

cfp_require_role('moderator');

$pdo = cfp_get_pdo();
$user = cfp_current_user();

$discussionId = isset($_GET['discussion_id']) ? (int)$_GET['discussion_id'] : 0;
$error = '';
$message = '';

// Fetch discussion and related item
$discussionStmt = $pdo->prepare('
    SELECT d.*, i.author_id, i.id AS item_id, i.title AS item_title,
           i.status_id AS item_status_id, s.code AS item_status_code,
           c.name AS committee_name
    FROM discussions d
    JOIN items i ON d.item_id = i.id
    JOIN item_statuses s ON i.status_id = s.id
    JOIN committees c ON d.committee_id = c.id
    WHERE d.id = :id
');
$discussionStmt->execute(['id' => $discussionId]);
$discussion = $discussionStmt->fetch();

if (!$discussion) {
    http_response_code(404);
    echo 'Discussion not found.';
    exit;
}

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voteCode = $_POST['vote'] ?? '';

    if (!in_array($voteCode, ['yes', 'no', 'abstain'], true)) {
        $error = 'Invalid vote option.';
    } else {
        // Map vote code to vote_options.id
        $optStmt = $pdo->prepare('SELECT id FROM vote_options WHERE code = :code');
        $optStmt->execute(['code' => $voteCode]);
        $optionId = $optStmt->fetchColumn();

        if (!$optionId) {
            $error = 'Vote options not configured.';
        } else {
            // Upsert vote (one vote per discussion per voter)
            $existsStmt = $pdo->prepare('SELECT id FROM votes WHERE discussion_id = :did AND voter_id = :vid');
            $existsStmt->execute(['did' => $discussionId, 'vid' => $user['id']]);
            $existingId = $existsStmt->fetchColumn();

            if ($existingId) {
                $pdo->prepare('
                    UPDATE votes
                    SET vote_option_id = :opt, vote_date = NOW()
                    WHERE id = :id
                ')->execute([
                    'opt' => $optionId,
                    'id'  => $existingId,
                ]);
            } else {
                $pdo->prepare('
                    INSERT INTO votes (discussion_id, voter_id, vote_option_id, vote_date)
                    VALUES (:did, :vid, :opt, NOW())
                ')->execute([
                    'did' => $discussionId,
                    'vid' => $user['id'],
                    'opt' => $optionId,
                ]);
            }

            $message = 'Vote recorded.';
        }
    }
}

// Tally votes
$tallyStmt = $pdo->prepare('
    SELECT vo.code, COUNT(*) AS cnt
    FROM votes v
    JOIN vote_options vo ON v.vote_option_id = vo.id
    WHERE v.discussion_id = :did
    GROUP BY vo.code
');
$tallyStmt->execute(['did' => $discussionId]);
$rows = $tallyStmt->fetchAll();

$counts = ['yes' => 0, 'no' => 0, 'abstain' => 0];
foreach ($rows as $row) {
    $code = $row['code'];
    if (isset($counts[$code])) {
        $counts[$code] = (int)$row['cnt'];
    }
}

$totalNonAbstain = $counts['yes'] + $counts['no'];
$decision = null;

if ($totalNonAbstain > 0) {
    $ratioYes = $counts['yes'] / $totalNonAbstain;

    if ($ratioYes >= (2.0 / 3.0)) {
        $decision = 'blacklist';
    } elseif ($ratioYes <= (1.0 / 3.0)) {
        $decision = 'no_blacklist';
    } else {
        $decision = 'no_decision_yet';
    }
}

// Determine committee type and current item state
$committeeName = $discussion['committee_name'] ?? '';
$isAppealsCommittee = stripos($committeeName, 'appeal') !== false;
$itemStatusCode = $discussion['item_status_code'] ?? '';
$isCurrentlyBlacklisted = $itemStatusCode === 'blacklisted';

// Apply automatic actions on 2/3 majority (blacklist) or appeal reversal
if ($decision === 'blacklist' && !$isCurrentlyBlacklisted) {
    $pdo->beginTransaction();
    try {
        // Set item status to blacklisted
        $statusStmt = $pdo->prepare('SELECT id FROM item_statuses WHERE code = "blacklisted"');
        $statusStmt->execute();
        $blacklistedStatusId = $statusStmt->fetchColumn();

        if ($blacklistedStatusId) {
            $pdo->prepare('UPDATE items SET status_id = :sid WHERE id = :id')
                ->execute(['sid' => $blacklistedStatusId, 'id' => $discussion['item_id']]);

            // Log moderation action
            $pdo->prepare('
                INSERT INTO moderation_logs (moderator_id, item_id, member_id, action, details, created_on)
                VALUES (:mid, :item_id, :member_id, "blacklist_item", "2/3 majority vote reached", NOW())
            ')->execute([
                'mid'       => $user['id'],
                'item_id'   => $discussion['item_id'],
                'member_id' => $discussion['author_id'],
            ]);

            // Notify author
            $pdo->prepare('
                INSERT INTO internal_messages (from_member, to_member, subject, body, is_private, is_read, sent_on)
                VALUES (:from, :to, :subject, :body, 1, 0, NOW())
            ')->execute([
                'from'    => $user['id'],
                'to'      => $discussion['author_id'],
                'subject' => 'Your item has been blacklisted',
                'body'    => 'The committee has voted to blacklist your item "' . $discussion['item_title'] . '".',
            ]);

            // Check if author has 3 or more blacklisted items
            $countStmt = $pdo->prepare('
                SELECT COUNT(*) FROM items i
                JOIN item_statuses s ON i.status_id = s.id
                WHERE i.author_id = :aid AND s.code = "blacklisted"
            ');
            $countStmt->execute(['aid' => $discussion['author_id']]);
            $blacklistedCount = (int)$countStmt->fetchColumn();

            if ($blacklistedCount >= 3) {
                // Suspend author account
                $statusMemberStmt = $pdo->prepare('SELECT id FROM member_statuses WHERE code = "suspended"');
                $statusMemberStmt->execute();
                $suspendedStatusId = $statusMemberStmt->fetchColumn();

                if ($suspendedStatusId) {
                    $pdo->prepare('UPDATE members SET status_id = :sid WHERE id = :id')
                        ->execute(['sid' => $suspendedStatusId, 'id' => $discussion['author_id']]);

                    // Log suspension
                    $pdo->prepare('
                        INSERT INTO moderation_logs (moderator_id, item_id, member_id, action, details, created_on)
                        VALUES (:mid, NULL, :member_id, "suspend_author", "Author reached 3 blacklisted items", NOW())
                    ')->execute([
                        'mid'       => $user['id'],
                        'member_id' => $discussion['author_id'],
                    ]);

                    // Notify author
                    $pdo->prepare('
                        INSERT INTO internal_messages (from_member, to_member, subject, body, is_private, is_read, sent_on)
                        VALUES (:from, :to, :subject, :body, 1, 0, NOW())
                    ')->execute([
                        'from'    => $user['id'],
                        'to'      => $discussion['author_id'],
                        'subject' => 'Your author account has been suspended',
                        'body'    => 'Your account has been suspended due to 3 or more blacklisted items.',
                    ]);
                }
            }
        }

        $pdo->commit();
        $isCurrentlyBlacklisted = true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

if ($isAppealsCommittee && $decision === 'no_blacklist' && $isCurrentlyBlacklisted) {
    $pdo->beginTransaction();
    try {
        // Reinstate item to approved status
        $approvedStatusStmt = $pdo->prepare('SELECT id FROM item_statuses WHERE code = "approved"');
        $approvedStatusStmt->execute();
        $approvedStatusId = $approvedStatusStmt->fetchColumn();

        if ($approvedStatusId) {
            $pdo->prepare('UPDATE items SET status_id = :sid WHERE id = :id')
                ->execute(['sid' => $approvedStatusId, 'id' => $discussion['item_id']]);

            // Log reinstatement
            $pdo->prepare('
                INSERT INTO moderation_logs (moderator_id, item_id, member_id, action, details, created_on)
                VALUES (:mid, :item_id, :member_id, "reinstate_item", "Appeals committee overturned blacklist", NOW())
            ')->execute([
                'mid'       => $user['id'],
                'item_id'   => $discussion['item_id'],
                'member_id' => $discussion['author_id'],
            ]);

            // Notify author
            $pdo->prepare('
                INSERT INTO internal_messages (from_member, to_member, subject, body, is_private, is_read, sent_on)
                VALUES (:from, :to, :subject, :body, 1, 0, NOW())
            ')->execute([
                'from'    => $user['id'],
                'to'      => $discussion['author_id'],
                'subject' => 'Your item has been reinstated',
                'body'    => 'The appeals committee voted to reinstate your item "' . $discussion['item_title'] . '".',
            ]);

            // Re-check blacklisted count and unsuspend if applicable
            $countStmt = $pdo->prepare('
                SELECT COUNT(*) FROM items i
                JOIN item_statuses s ON i.status_id = s.id
                WHERE i.author_id = :aid AND s.code = "blacklisted"
            ');
            $countStmt->execute(['aid' => $discussion['author_id']]);
            $remainingBlacklisted = (int)$countStmt->fetchColumn();

            if ($remainingBlacklisted < 3) {
                $memberStatusStmt = $pdo->prepare('
                    SELECT m.status_id, s.code
                    FROM members m
                    JOIN member_statuses s ON m.status_id = s.id
                    WHERE m.id = :id
                ');
                $memberStatusStmt->execute(['id' => $discussion['author_id']]);
                $statusRow = $memberStatusStmt->fetch();

                if (($statusRow['code'] ?? '') === 'suspended') {
                    $activeStatusStmt = $pdo->prepare('SELECT id FROM member_statuses WHERE code = "active"');
                    $activeStatusStmt->execute();
                    $activeStatusId = $activeStatusStmt->fetchColumn();

                    if ($activeStatusId) {
                        $pdo->prepare('UPDATE members SET status_id = :sid WHERE id = :id')
                            ->execute(['sid' => $activeStatusId, 'id' => $discussion['author_id']]);

                        $pdo->prepare('
                            INSERT INTO moderation_logs (moderator_id, item_id, member_id, action, details, created_on)
                            VALUES (:mid, NULL, :member_id, "reinstate_author", "Appeals committee reduced blacklist count", NOW())
                        ')->execute([
                            'mid'       => $user['id'],
                            'member_id' => $discussion['author_id'],
                        ]);

                        $pdo->prepare('
                            INSERT INTO internal_messages (from_member, to_member, subject, body, is_private, is_read, sent_on)
                            VALUES (:from, :to, :subject, :body, 1, 0, NOW())
                        ')->execute([
                            'from'    => $user['id'],
                            'to'      => $discussion['author_id'],
                            'subject' => 'Your suspension has been lifted',
                            'body'    => 'Following the appeals decision, your author account is active again.',
                        ]);
                    }
                }
            }
        }

        $pdo->commit();
        $isCurrentlyBlacklisted = false;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Plagiarism voting · CFP</title>
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
            <h1 class="cfp-h1">Plagiarism vote</h1>
            <p class="cfp-muted">
                Item: <?php echo e($discussion['item_title']); ?>
            </p>

            <?php if ($error): ?>
                <div class="cfp-alert cfp-alert-error"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="cfp-alert cfp-alert-success"><?php echo e($message); ?></div>
            <?php endif; ?>

            <form method="post" style="margin-top:1rem;">
                <label class="cfp-label">Your vote</label>
                <div class="cfp-pill-row">
                    <label><input type="radio" name="vote" value="yes" required> Yes (blacklist)</label>
                    <label><input type="radio" name="vote" value="no"> No (keep)</label>
                    <label><input type="radio" name="vote" value="abstain"> Abstain</label>
                </div>
                <button class="cfp-btn cfp-btn-primary" type="submit" style="margin-top:0.7rem;">Submit vote</button>
            </form>

            <div style="margin-top:1.5rem;">
                <h2 style="font-size:0.95rem;">Current tally</h2>
                <ul class="cfp-list">
                    <li>Yes: <?php echo (int)$counts['yes']; ?></li>
                    <li>No: <?php echo (int)$counts['no']; ?></li>
                    <li>Abstain: <?php echo (int)$counts['abstain']; ?></li>
                </ul>
                <?php if ($decision === 'blacklist'): ?>
                    <p class="cfp-alert cfp-alert-error">Decision: Item blacklisted (≥ 2/3 yes votes).</p>
                <?php elseif ($decision === 'no_blacklist'): ?>
                    <p class="cfp-alert cfp-alert-success">Decision: Item not blacklisted (≤ 1/3 yes votes).</p>
                <?php elseif ($decision === 'no_decision_yet'): ?>
                    <p class="cfp-muted">No clear 2/3 majority yet; continue voting.</p>
                <?php else: ?>
                    <p class="cfp-muted">No votes yet.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>
</body>
</html>


