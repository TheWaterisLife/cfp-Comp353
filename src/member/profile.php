<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

cfp_require_login();

$pdo = cfp_get_pdo();
$user = cfp_current_user();

$downloads = $pdo->prepare('
    SELECT d.download_date, i.title
    FROM downloads d
    JOIN items i ON d.item_id = i.id
    WHERE d.member_id = :mid
    ORDER BY d.download_date DESC
    LIMIT 20
');
$downloads->execute(['mid' => $user['id']]);
$downloads = $downloads->fetchAll();

$donations = $pdo->prepare('
    SELECT dn.date, dn.amount, i.title, c.name AS charity_name
    FROM donations dn
    JOIN items i ON dn.item_id = i.id
    JOIN charities c ON dn.charity_id = c.id
    WHERE dn.member_id = :mid
    ORDER BY dn.date DESC
    LIMIT 20
');
$donations->execute(['mid' => $user['id']]);
$donations = $donations->fetchAll();

$committees = $pdo->prepare('
    SELECT cm.role, ct.name
    FROM committee_members cm
    JOIN committees ct ON cm.committee_id = ct.id
    WHERE cm.member_id = :mid
');
$committees->execute(['mid' => $user['id']]);
$committees = $committees->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile · CFP</title>
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
            <?php echo e($user['name']); ?> (<?php echo e(cfp_current_role() ?? ''); ?>)
            &nbsp;·&nbsp;
            <a href="/logout.php">Logout</a>
        </div>
    </div>
</header>
<main class="cfp-main">
    <div class="cfp-main-inner">
        <section class="cfp-panel">
            <h1 class="cfp-h1">Your profile</h1>
            <p class="cfp-muted"><?php echo e($user['email']); ?></p>

            <div class="cfp-grid cfp-grid-2" style="margin-top:1rem;">
                <div>
                    <h2 style="font-size:0.95rem;">Recent downloads</h2>
                    <table class="cfp-table">
                        <thead>
                        <tr><th>Date</th><th>Item</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($downloads as $d): ?>
                            <tr>
                                <td><?php echo e($d['download_date']); ?></td>
                                <td><?php echo e($d['title']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$downloads): ?>
                            <tr><td colspan="2" class="cfp-muted">No downloads yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div>
                    <h2 style="font-size:0.95rem;">Recent donations</h2>
                    <table class="cfp-table">
                        <thead>
                        <tr><th>Date</th><th>Amount</th><th>Item</th><th>Charity</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($donations as $dn): ?>
                            <tr>
                                <td><?php echo e($dn['date']); ?></td>
                                <td><?php echo e($dn['amount']); ?></td>
                                <td><?php echo e($dn['title']); ?></td>
                                <td><?php echo e($dn['charity_name']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$donations): ?>
                            <tr><td colspan="4" class="cfp-muted">No donations yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="margin-top:1.5rem;">
                <h2 style="font-size:0.95rem;">Committees</h2>
                <?php if (!$committees): ?>
                    <p class="cfp-muted">You are not currently assigned to any committees.</p>
                <?php else: ?>
                    <ul class="cfp-list">
                        <?php foreach ($committees as $c): ?>
                            <li><?php echo e($c['name']); ?> — <span class="cfp-muted"><?php echo e($c['role']); ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>
</body>
</html>


