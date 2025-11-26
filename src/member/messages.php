<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

cfp_require_login();

$pdo = cfp_get_pdo();
$user = cfp_current_user();
$error = '';

// Optional prefill from query string, e.g. when an author messages a commenter.
$prefillToEmail = trim($_GET['to'] ?? '');
$prefillSubject = trim($_GET['subject'] ?? '');

// Handle send
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toEmail = trim($_POST['to_email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if ($toEmail === '' || $subject === '' || $body === '') {
        $error = 'Recipient email, subject, and body are required.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM members WHERE primary_email = :email');
        $stmt->execute(['email' => $toEmail]);
        $to = $stmt->fetch();
        if (!$to) {
            $error = 'No member found with that email address.';
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO internal_messages (from_member, to_member, subject, body, is_private, is_read, sent_on)
                VALUES (:from, :to, :subject, :body, 1, 0, NOW())
            ');
            $stmt->execute([
                'from'    => $user['id'],
                'to'      => $to['id'],
                'subject' => $subject,
                'body'    => $body,
            ]);
        }
    }
}

// Inbox and sent
$inbox = $pdo->prepare('
    SELECT im.id, im.subject, im.body, im.sent_on, m.name AS from_name
    FROM internal_messages im
    JOIN members m ON im.from_member = m.id
    WHERE im.to_member = :mid
    ORDER BY im.sent_on DESC
    LIMIT 20
');
$inbox->execute(['mid' => $user['id']]);
$inbox = $inbox->fetchAll();

$sent = $pdo->prepare('
    SELECT im.id, im.subject, im.body, im.sent_on, m.name AS to_name
    FROM internal_messages im
    JOIN members m ON im.to_member = m.id
    WHERE im.from_member = :mid
    ORDER BY im.sent_on DESC
    LIMIT 20
');
$sent->execute(['mid' => $user['id']]);
$sent = $sent->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Messages · CFP</title>
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
            <?php echo e($user['name']); ?>
            &nbsp;·&nbsp;
            <a href="/logout.php">Logout</a>
        </div>
    </div>
</header>
<main class="cfp-main">
    <div class="cfp-main-inner">
        <section class="cfp-panel">
            <h1 class="cfp-h1">Messages</h1>
            <p class="cfp-muted">Internal messaging simulates email notifications within CFP.</p>

            <div class="cfp-grid cfp-grid-2" style="margin-top:1rem;">
                <div>
                    <h2 style="font-size:0.95rem;">Inbox</h2>
                    <ul class="cfp-list">
                        <?php foreach ($inbox as $m): ?>
                            <li>
                                <strong><?php echo e($m['subject']); ?></strong><br>
                                <span class="cfp-muted" style="font-size:0.78rem;">
                                    From <?php echo e($m['from_name']); ?> · <?php echo e($m['sent_on']); ?>
                                </span><br>
                                <span><?php echo nl2br(e($m['body'])); ?></span>
                            </li>
                        <?php endforeach; ?>
                        <?php if (!$inbox): ?>
                            <li class="cfp-muted">No messages.</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h2 style="font-size:0.95rem;">Sent</h2>
                    <ul class="cfp-list">
                        <?php foreach ($sent as $m): ?>
                            <li>
                                <strong><?php echo e($m['subject']); ?></strong><br>
                                <span class="cfp-muted" style="font-size:0.78rem;">
                                    To <?php echo e($m['to_name']); ?> · <?php echo e($m['sent_on']); ?>
                                </span><br>
                                <span><?php echo nl2br(e($m['body'])); ?></span>
                            </li>
                        <?php endforeach; ?>
                        <?php if (!$sent): ?>
                            <li class="cfp-muted">No messages sent.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div style="margin-top:1.5rem;">
                <h2 style="font-size:0.95rem;">Compose</h2>
                <?php if ($error): ?>
                    <div class="cfp-alert cfp-alert-error"><?php echo e($error); ?></div>
                <?php endif; ?>
                <form method="post">
                    <label class="cfp-label">
                        To (email)
                        <input class="cfp-input" type="email" name="to_email" required
                               value="<?php echo e($_POST['to_email'] ?? $prefillToEmail); ?>">
                    </label>
                    <label class="cfp-label">
                        Subject
                        <input class="cfp-input" type="text" name="subject" required
                               value="<?php echo e($_POST['subject'] ?? $prefillSubject); ?>">
                    </label>
                    <label class="cfp-label">
                        Message
                        <textarea class="cfp-textarea" name="body" required><?php echo e($_POST['body'] ?? ''); ?></textarea>
                    </label>
                    <button class="cfp-btn cfp-btn-primary" type="submit" style="margin-top:0.7rem;">Send</button>
                </form>
            </div>
        </section>
    </div>
</main>
</body>
</html>


