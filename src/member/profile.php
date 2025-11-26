<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

cfp_require_login();

$pdo = cfp_get_pdo();
$user = cfp_current_user();

$profileError = '';
$profileMessage = '';

// Handle profile update (basic self-service edit).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name          = trim($_POST['name'] ?? '');
    $org           = trim($_POST['org'] ?? '');
    $address       = trim($_POST['address'] ?? '');
    $recoveryEmail = trim($_POST['recovery_email'] ?? '');

    if ($name === '') {
        $profileError = 'Name is required.';
    } elseif ($recoveryEmail !== '' && !filter_var($recoveryEmail, FILTER_VALIDATE_EMAIL)) {
        $profileError = 'Recovery email must be a valid email address.';
    } else {
        $stmt = $pdo->prepare('
            UPDATE members
            SET name = :name,
                org = :org,
                address = :address,
                recovery_email = :recovery_email
            WHERE id = :id
        ');
        $stmt->execute([
            'name'           => $name,
            'org'            => $org !== '' ? $org : null,
            'address'        => $address !== '' ? $address : null,
            'recovery_email' => $recoveryEmail !== '' ? $recoveryEmail : null,
            'id'             => $user['id'],
        ]);

        // Keep session display name in sync.
        $_SESSION['member']['name'] = $name;
        $user['name'] = $name;

        $profileMessage = 'Profile updated successfully.';
    }
}

// Fetch fresh profile data for display.
$profileStmt = $pdo->prepare('
    SELECT name, org, address, primary_email, recovery_email
    FROM members
    WHERE id = :id
');
$profileStmt->execute(['id' => $user['id']]);
$profile = $profileStmt->fetch() ?: [
    'name'          => $user['name'],
    'org'           => null,
    'address'       => null,
    'primary_email' => $user['email'],
    'recovery_email'=> null,
];

// Recent downloads
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

// Recent donations
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

// Donation total for current year
$donationTotalStmt = $pdo->prepare('
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM donations
    WHERE member_id = :mid
      AND YEAR(date) = YEAR(CURDATE())
');
$donationTotalStmt->execute(['mid' => $user['id']]);
$donationTotal = (float)$donationTotalStmt->fetchColumn();

// Committees for this member
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
            <p class="cfp-muted"><?php echo e($profile['primary_email'] ?? $user['email']); ?></p>

            <?php if ($profileError): ?>
                <div class="cfp-alert cfp-alert-error" style="margin-top:0.75rem;"><?php echo e($profileError); ?></div>
            <?php endif; ?>
            <?php if ($profileMessage): ?>
                <div class="cfp-alert cfp-alert-success" style="margin-top:0.75rem;"><?php echo e($profileMessage); ?></div>
            <?php endif; ?>

            <form method="post" style="margin-top:1rem;">
                <div class="cfp-grid cfp-grid-2">
                    <div>
                        <h2 style="font-size:0.95rem;">Account details</h2>
                        <label class="cfp-label">
                            Full name
                            <input class="cfp-input" type="text" name="name"
                                   value="<?php echo e($profile['name'] ?? ''); ?>" required>
                        </label>
                        <label class="cfp-label">
                            Organisation
                            <input class="cfp-input" type="text" name="org"
                                   value="<?php echo e($profile['org'] ?? ''); ?>">
                        </label>
                        <label class="cfp-label">
                            Address
                            <input class="cfp-input" type="text" name="address"
                                   value="<?php echo e($profile['address'] ?? ''); ?>">
                        </label>
                        <label class="cfp-label">
                            Recovery email
                            <input class="cfp-input" type="email" name="recovery_email"
                                   value="<?php echo e($profile['recovery_email'] ?? ''); ?>">
                        </label>
                        <button class="cfp-btn cfp-btn-primary" type="submit" name="update_profile"
                                value="1" style="margin-top:0.75rem;">
                            Save changes
                        </button>
                    </div>
                    <div>
                        <h2 style="font-size:0.95rem;">Donations summary</h2>
                        <p class="cfp-muted" style="margin-top:0.5rem;">
                            Donation total for the current year:
                        </p>
                        <p style="font-size:1.1rem; font-weight:600; margin-top:0.25rem;">
                            <?php echo number_format($donationTotal, 2); ?>
                        </p>
                    </div>
                </div>
            </form>

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
                <p style="margin-top:0.75rem; font-size:0.85rem;">
                    <a href="/member/committee_request.php">Request to join a committee →</a>
                </p>
            </div>
        </section>
    </div>
</main>
</body>
</html>


