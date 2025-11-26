<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

cfp_require_login();

$pdo = cfp_get_pdo();
$user = cfp_current_user();
$error = '';
$message = '';

// Fetch all committees for the select box
$committeesStmt = $pdo->query('SELECT id, name, description FROM committees ORDER BY name');
$committees = $committeesStmt->fetchAll();

// Existing requests for this member
$requestsStmt = $pdo->prepare('
    SELECT cr.id, cr.status, cr.requested_on, cr.decided_on, cr.note, ct.name AS committee_name
    FROM committee_requests cr
    JOIN committees ct ON cr.committee_id = ct.id
    WHERE cr.member_id = :mid
    ORDER BY cr.requested_on DESC
');
$requestsStmt->execute(['mid' => $user['id']]);
$existingRequests = $requestsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $committeeId = (int)($_POST['committee_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if ($committeeId <= 0) {
        $error = 'Please select a committee.';
    } else {
        // Check committee exists
        $check = $pdo->prepare('SELECT id FROM committees WHERE id = :id');
        $check->execute(['id' => $committeeId]);
        if (!$check->fetchColumn()) {
            $error = 'Selected committee does not exist.';
        } else {
            // Check if already a member of this committee
            $isMemberStmt = $pdo->prepare('
                SELECT 1 FROM committee_members
                WHERE committee_id = :cid AND member_id = :mid
            ');
            $isMemberStmt->execute(['cid' => $committeeId, 'mid' => $user['id']]);
            if ($isMemberStmt->fetchColumn()) {
                $error = 'You are already a member of this committee.';
            } else {
                // Either create or update a pending request
                $existsStmt = $pdo->prepare('
                    SELECT id, status FROM committee_requests
                    WHERE committee_id = :cid AND member_id = :mid
                ');
                $existsStmt->execute(['cid' => $committeeId, 'mid' => $user['id']]);
                $existing = $existsStmt->fetch();

                if ($existing && $existing['status'] === 'pending') {
                    $error = 'You already have a pending request for this committee.';
                } elseif ($existing) {
                    // Re-open a previous request
                    $upd = $pdo->prepare('
                        UPDATE committee_requests
                        SET status = "pending",
                            requested_on = NOW(),
                            decided_on = NULL,
                            decided_by = NULL,
                            note = :note
                        WHERE id = :id
                    ');
                    $upd->execute([
                        'note' => $note !== '' ? $note : null,
                        'id'   => $existing['id'],
                    ]);
                    $message = 'Your request has been resubmitted for review.';
                } else {
                    $ins = $pdo->prepare('
                        INSERT INTO committee_requests (committee_id, member_id, status, requested_on, note)
                        VALUES (:cid, :mid, "pending", NOW(), :note)
                    ');
                    $ins->execute([
                        'cid'  => $committeeId,
                        'mid'  => $user['id'],
                        'note' => $note !== '' ? $note : null,
                    ]);
                    $message = 'Your request has been submitted.';
                }

                // Refresh existing requests after change
                $requestsStmt->execute(['mid' => $user['id']]);
                $existingRequests = $requestsStmt->fetchAll();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request committee membership · CFP</title>
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
            <h1 class="cfp-h1">Request to join a committee</h1>
            <p class="cfp-muted">
                Choose a committee you would like to join and submit a short note explaining your interest.
                An administrator will review your request.
            </p>

            <?php if ($error): ?>
                <div class="cfp-alert cfp-alert-error" style="margin-top:0.75rem;"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="cfp-alert cfp-alert-success" style="margin-top:0.75rem;"><?php echo e($message); ?></div>
            <?php endif; ?>

            <form method="post" style="margin-top:1rem;">
                <label class="cfp-label">
                    Committee
                    <select class="cfp-input" name="committee_id" required>
                        <option value="">Select a committee…</option>
                        <?php foreach ($committees as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="cfp-label">
                    Note to admin (optional)
                    <textarea class="cfp-textarea" name="note" rows="3"
                              placeholder="Briefly explain why you would like to join this committee."></textarea>
                </label>
                <button class="cfp-btn cfp-btn-primary" type="submit" style="margin-top:0.75rem;">Submit request</button>
            </form>

            <div style="margin-top:1.5rem;">
                <h2 style="font-size:0.95rem;">Your committee requests</h2>
                <?php if (!$existingRequests): ?>
                    <p class="cfp-muted">You have not submitted any committee membership requests yet.</p>
                <?php else: ?>
                    <table class="cfp-table">
                        <thead>
                        <tr>
                            <th>Committee</th>
                            <th>Status</th>
                            <th>Requested on</th>
                            <th>Decided on</th>
                            <th>Note</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($existingRequests as $r): ?>
                            <tr>
                                <td><?php echo e($r['committee_name']); ?></td>
                                <td><?php echo e($r['status']); ?></td>
                                <td><?php echo e($r['requested_on']); ?></td>
                                <td><?php echo e($r['decided_on'] ?? '-'); ?></td>
                                <td><?php echo e($r['note'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>
</body>
</html>


