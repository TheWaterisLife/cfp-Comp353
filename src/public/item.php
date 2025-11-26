<?php
require_once __DIR__ . '/bootstrap.php';

$pdo = cfp_get_pdo();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item = null;
$comments = [];
$nestedReplies = [];
$canComment = false;
$isItemAuthor = false;
$authorBlacklistedCount = 0;
$downloadCount = 0;
$donationTotal = 0.0;
$versions = [];

if ($id > 0) {
    $stmt = $pdo->prepare('
        SELECT i.id, i.title, i.description, i.upload_date, i.file_path,
               i.author_id,
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
        // Compute how many blacklisted items this author has.
        $blStmt = $pdo->prepare('
            SELECT COUNT(*) FROM items i2
            JOIN item_statuses s2 ON i2.status_id = s2.id
            WHERE i2.author_id = :aid
              AND s2.code = "blacklisted"
        ');
        $blStmt->execute(['aid' => $item['author_id']]);
        $authorBlacklistedCount = (int)$blStmt->fetchColumn();

        // Per-item download count
        $dlStmt = $pdo->prepare('SELECT COUNT(*) FROM downloads WHERE item_id = :id');
        $dlStmt->execute(['id' => $id]);
        $downloadCount = (int)$dlStmt->fetchColumn();

        // Total donations for this item
        $donStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM donations WHERE item_id = :id');
        $donStmt->execute(['id' => $id]);
        $donationTotal = (float)$donStmt->fetchColumn();

        // Derived versions from item_versions
        $verStmt = $pdo->prepare('
            SELECT version_number, file_path, approved_on
            FROM item_versions
            WHERE item_id = :id
            ORDER BY version_number ASC
        ');
        $verStmt->execute(['id' => $id]);
        $versions = $verStmt->fetchAll();

        if (cfp_is_logged_in()) {
            $u = cfp_current_user();
            $isItemAuthor = ((int)$item['author_id'] === (int)$u['id']);
        }

        $cStmt = $pdo->prepare('
            SELECT c.id,
                   c.parent_comment_id,
                   c.content,
                   c.created_on,
                   m.id   AS author_id,
                   m.name AS author_name,
                   m.primary_email AS author_email
            FROM comments c
            JOIN members m ON c.author_id = m.id
            WHERE c.item_id = :id
            ORDER BY c.created_on ASC
            LIMIT 50
        ');
        $cStmt->execute(['id' => $id]);
        $rows = $cStmt->fetchAll();

        // Split into top-level comments and nested replies
        foreach ($rows as $row) {
            if (!empty($row['parent_comment_id'])) {
                $parentId = (int)$row['parent_comment_id'];
                if (!isset($nestedReplies[$parentId])) {
                    $nestedReplies[$parentId] = [];
                }
                $nestedReplies[$parentId][] = $row;
            } else {
                $comments[] = $row;
            }
        }

        // Determine whether the current user (if any) is allowed to comment:
        // comments are only allowed for members who have downloaded the item.
        if (cfp_is_logged_in()) {
            $u = cfp_current_user();

            // Item authors can always comment; other members must have downloaded.
            if ($isItemAuthor) {
                $canComment = true;
            } else {
                $downloadCheck = $pdo->prepare('
                    SELECT COUNT(*) FROM downloads
                    WHERE member_id = :mid AND item_id = :item_id
                ');
                $downloadCheck->execute([
                    'mid'      => $u['id'],
                    'item_id'  => $id,
                ]);
                $canComment = (int)$downloadCheck->fetchColumn() > 0;
            }
        }
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

                <div style="margin-top:1.25rem; display:flex; flex-wrap:wrap; gap:1.25rem;">
                    <div>
                        <h2 style="font-size:0.9rem;">Usage & support</h2>
                        <ul class="cfp-list">
                            <li>Downloads: <strong><?php echo (int)$downloadCount; ?></strong></li>
                            <li>Total donations: <strong><?php echo number_format($donationTotal, 2); ?></strong></li>
                        </ul>
                    </div>
                    <div>
                        <h2 style="font-size:0.9rem;">Versions</h2>
                        <?php if (!$versions): ?>
                            <p class="cfp-muted" style="font-size:0.85rem;">No versions have been recorded yet.</p>
                        <?php else: ?>
                            <ul class="cfp-list">
                                <?php foreach ($versions as $v): ?>
                                    <li>
                                        v<?php echo (int)$v['version_number']; ?>
                                        <span class="cfp-muted" style="font-size:0.8rem;">
                                            · <?php echo e($v['file_path']); ?>
                                            <?php if (!empty($v['approved_on'])): ?>
                                                · approved <?php echo e($v['approved_on']); ?>
                                            <?php endif; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-top:1.5rem;">
                    <h2 style="font-size:0.95rem;">Comments</h2>
                    <ul id="cfp-comments-list" class="cfp-list">
                        <?php foreach ($comments as $c): ?>
                            <li data-comment-id="<?php echo (int)$c['id']; ?>">
                                <strong><?php echo e($c['author_name']); ?></strong>
                                <span class="cfp-muted" style="font-size:0.75rem;"> · <?php echo e($c['created_on']); ?></span><br>
                                <span><?php echo nl2br(e($c['content'])); ?></span>
                                <?php if ($isItemAuthor && isset($u) && (int)$c['author_id'] !== (int)$u['id']): ?>
                                    <div style="margin-top:0.35rem; font-size:0.8rem;">
                                        <a href="#" data-cfp-comment-reply
                                           data-comment-id="<?php echo (int)$c['id']; ?>"
                                           data-comment-author="<?php echo e($c['author_name']); ?>">
                                            Reply publicly
                                        </a>
                                        &nbsp;·&nbsp;
                                        <a href="/member/messages.php?to=<?php echo urlencode($c['author_email']); ?>&subject=<?php echo urlencode('Regarding your comment on \"' . $item['title'] . '\"'); ?>">
                                            Message privately
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($nestedReplies[(int)$c['id']] ?? [])): ?>
                                    <ul class="cfp-list" style="margin-top:0.5rem; margin-left:1rem;">
                                        <?php foreach ($nestedReplies[(int)$c['id']] as $r): ?>
                                            <li>
                                                <strong><?php echo e($r['author_name']); ?></strong>
                                                <span class="cfp-muted" style="font-size:0.75rem;"> · <?php echo e($r['created_on']); ?></span><br>
                                                <span><?php echo nl2br(e($r['content'])); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        <?php if (!$comments): ?>
                            <li class="cfp-muted">No comments yet.</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <?php if (cfp_is_logged_in() && $canComment): ?>
                    <div style="margin-top:1rem;">
                        <h3 style="font-size:0.9rem;">Add a comment</h3>
                        <div id="cfp-comment-message"></div>
                        <p id="cfp-comment-replying-to" class="cfp-muted" style="font-size:0.8rem; margin-top:0.25rem;"></p>
                        <form method="post" action="/item_comment.php" data-cfp-comment-ajax="1">
                            <input type="hidden" name="item_id" value="<?php echo (int)$item['id']; ?>">
                            <input type="hidden" name="parent_comment_id" value="0">
                            <label class="cfp-label">
                                Comment
                                <textarea class="cfp-textarea" name="content" required></textarea>
                            </label>
                            <button class="cfp-btn cfp-btn-primary" type="submit" style="margin-top:0.6rem;">Post comment</button>
                        </form>
                    </div>
                <?php elseif (cfp_is_logged_in()): ?>
                    <p class="cfp-muted" style="margin-top:1rem;">
                        You must download this item before you can post a comment.
                    </p>
                <?php else: ?>
                    <p class="cfp-muted" style="margin-top:1rem;">
                        <a href="/login.php">Log in</a> to post a comment.
                    </p>
                <?php endif; ?>

                <?php if (cfp_is_logged_in() && $authorBlacklistedCount > 0): ?>
                    <div style="margin-top:1.5rem; padding-top:1rem; border-top:1px solid rgba(148,163,184,.3);">
                        <p class="cfp-muted" style="font-size:0.85rem;">
                            This author has <?php echo (int)$authorBlacklistedCount; ?> blacklisted item(s).
                            You can help the community by reviewing their other works for potential plagiarism.
                        </p>
                        <a class="cfp-btn cfp-btn-outline"
                           href="/member/review_blacklisted_author.php?author_id=<?php echo (int)$item['author_id']; ?>"
                           style="margin-top:0.5rem; font-size:0.85rem;">
                            Review other items by this author
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var replyLinks = document.querySelectorAll('[data-cfp-comment-reply]');
    var form = document.querySelector('[data-cfp-comment-ajax="1"]');
    var parentInput = form ? form.querySelector('input[name="parent_comment_id"]') : null;
    var banner = document.getElementById('cfp-comment-replying-to');

    replyLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            if (!form || !parentInput) return;

            var cid = this.getAttribute('data-comment-id') || '0';
            var author = this.getAttribute('data-comment-author') || '';

            parentInput.value = cid;
            if (banner) {
                banner.textContent = author ? ('Replying to ' + author) : '';
            }

            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
});
</script>
</html>


