<?php
// Author: Adam Mohammed Dahmane (40251506)

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';

cfp_require_role('admin');

$pdo = cfp_get_pdo();
$user = cfp_current_user();
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($requestId <= 0 || !in_array($action, ['approve', 'deny'], true)) {
        $error = 'Invalid action.';
    } else {
        $stmt = $pdo->prepare('
            SELECT cr.*, ct.name AS committee_name, m.name AS member_name
            FROM committee_requests cr
            JOIN committees ct ON cr.committee_id = ct.id
            JOIN members m ON cr.member_id = m.id
            WHERE cr.id = :id
        ');
        $stmt->execute(['id' => $requestId]);
        $req = $stmt->fetch();

        if (!$req) {
            $error = 'Request not found.';
        } elseif ($req['status'] !== 'pending') {
            $error = 'This request has already been processed.';
        } else {
            if ($action === 'approve') {
                // Add to committee_members if not already a member
                $check = $pdo->prepare('
                    SELECT 1 FROM committee_members
                    WHERE committee_id = :cid AND member_id = :mid
                ');
                $check->execute(['cid' => $req['committee_id'], 'mid' => $req['member_id']]);
                if (!$check->fetchColumn()) {
                    $ins = $pdo->prepare('
                        INSERT INTO committee_members (committee_id, member_id, role, joined_on)
                        VALUES (:cid, :mid, "member", NOW())
                    ');
                    $ins->execute([
                        'cid' => $req['committee_id'],
                        'mid' => $req['member_id'],
                    ]);
                }

                $upd = $pdo->prepare('
                    UPDATE committee_requests
                    SET status = "approved",
                        decided_on = NOW(),
                        decided_by = :admin_id
                    WHERE id = :id
                ');
                $upd->execute([
                    'admin_id' => $user['id'],
                    'id'       => $requestId,
                ]);

                $message = 'Request approved and member added to committee.';
            } else {
                $upd = $pdo->prepare('
                    UPDATE committee_requests
                    SET status = "denied",
                        decided_on = NOW(),
                        decided_by = :admin_id
                    WHERE id = :id
                ');
                $upd->execute([
                    'admin_id' => $user['id'],
                    'id'       => $requestId,
                ]);

                $message = 'Request denied.';
            }
        }
    }
}

// Pending requests
$pendingStmt = $pdo->query('
    SELECT cr.id, cr.requested_on, cr.note,
           ct.name AS committee_name,
           m.name AS member_name, m.primary_email
    FROM committee_requests cr
    JOIN committees ct ON cr.committee_id = ct.id
    JOIN members m ON cr.member_id = m.id
    WHERE cr.status = "pending"
    ORDER BY cr.requested_on ASC
');
$pending = $pendingStmt->fetchAll();

// Recently decided requests
$recentStmt = $pdo->query('
    SELECT cr.id, cr.status, cr.requested_on, cr.decided_on,
           ct.name AS committee_name,
           m.name AS member_name
    FROM committee_requests cr
    JOIN committees ct ON cr.committee_id = ct.id
    JOIN members m ON cr.member_id = m.id
    WHERE cr.status IN ("approved", "denied")
    ORDER BY cr.decided_on DESC
    LIMIT 20
');
$recent = $recentStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin · Committee requests</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo cfp_url('assets/css/main.css'); ?>">
</head>
<body class="cfp-shell">
<header class="cfp-header">
    <div class="cfp-header-inner">
        <div class="cfp-logo"><a href="<?php echo cfp_url('index.php'); ?>">CopyForward Publishing</a></div>
        <nav class="cfp-nav">
            <a href="<?php echo cfp_url('index.php'); ?>">Home</a>
            <a href="<?php echo cfp_url('admin/members.php'); ?>">Admin</a>
        </nav>
    </div>
</header>
<main class="cfp-main">
    <div class="cfp-main-inner">
        <section class="cfp-panel">
            <h1 class="cfp-h1">Committee membership requests</h1>
            <p class="cfp-muted">
                Review and approve or deny member requests to join committees.
            </p>

            <?php if ($error): ?>
                <div class="cfp-alert cfp-alert-error" style="margin-top:0.75rem;"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="cfp-alert cfp-alert-success" style="margin-top:0.75rem;"><?php echo e($message); ?></div>
            <?php endif; ?>

            <h2 style="margin-top:1.25rem; font-size:0.95rem;">Pending requests</h2>
            <?php if (!$pending): ?>
                <p class="cfp-muted">No pending requests.</p>
            <?php else: ?>
                <table class="cfp-table">
                    <thead>
                    <tr>
                        <th>Committee</th>
                        <th>Member</th>
                        <th>Email</th>
                        <th>Requested on</th>
                        <th>Note</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pending as $r): ?>
                        <tr>
                            <td><?php echo e($r['committee_name']); ?></td>
                            <td><?php echo e($r['member_name']); ?></td>
                            <td><?php echo e($r['primary_email']); ?></td>
                            <td><?php echo e($r['requested_on']); ?></td>
                            <td><?php echo e($r['note'] ?? ''); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
                                    <button class="cfp-btn cfp-btn-primary" type="submit" name="action" value="approve"
                                            style="padding:0.25rem 0.6rem; font-size:0.8rem;">
                                        Approve
                                    </button>
                                    <button class="cfp-btn cfp-btn-outline" type="submit" name="action" value="deny"
                                            style="padding:0.25rem 0.6rem; font-size:0.8rem; margin-left:0.35rem;">
                                        Deny
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2 style="margin-top:1.5rem; font-size:0.95rem;">Recent decisions</h2>
            <?php if (!$recent): ?>
                <p class="cfp-muted">No recent approvals or denials.</p>
            <?php else: ?>
                <table class="cfp-table">
                    <thead>
                    <tr>
                        <th>Committee</th>
                        <th>Member</th>
                        <th>Status</th>
                        <th>Requested on</th>
                        <th>Decided on</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><?php echo e($r['committee_name']); ?></td>
                            <td><?php echo e($r['member_name']); ?></td>
                            <td><?php echo e($r['status']); ?></td>
                            <td><?php echo e($r['requested_on']); ?></td>
                            <td><?php echo e($r['decided_on']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>


