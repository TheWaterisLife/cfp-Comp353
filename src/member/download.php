<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

cfp_require_role(['member', 'author', 'moderator', 'admin']);

$pdo = cfp_get_pdo();
$user = cfp_current_user();
$error = '';
$message = '';

$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

if ($itemId <= 0) {
    $error = 'No item specified.';
} else {
    // Check that item is approved (or blacklisted logic will be added later)
    $approvedStatusStmt = $pdo->prepare('SELECT id FROM item_statuses WHERE code = :code');
    $approvedStatusStmt->execute(['code' => 'approved']);
    $approvedStatusId = $approvedStatusStmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT id, title, file_path, status_id FROM items WHERE id = :id');
    $stmt->execute(['id' => $itemId]);
    $item = $stmt->fetch();

    if (!$item || (int)$item['status_id'] !== (int)$approvedStatusId) {
        $error = 'Item is not available for download.';
    } else {
        // Determine if user is a donor within last year
        $donorStmt = $pdo->prepare('
            SELECT COUNT(*) FROM donations
            WHERE member_id = :mid AND date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
        ');
        $donorStmt->execute(['mid' => $user['id']]);
        $isDonor = $donorStmt->fetchColumn() > 0;

        $limitDays = $isDonor ? 1 : 7;

        // Count downloads in the relevant window
        $dlStmt = $pdo->prepare('
            SELECT COUNT(*) FROM downloads
            WHERE member_id = :mid
              AND download_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ');
        $dlStmt->execute([
            'mid'  => $user['id'],
            'days' => $limitDays,
        ]);
        $recentCount = (int)$dlStmt->fetchColumn();

        if ($recentCount >= 1) {
            $error = $isDonor
                ? 'Daily download limit reached (1 per day for donors).'
                : 'Weekly download limit reached (1 item per 7 days).';
        } else {
            // Record the download, including simulated country code for statistics.
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;

            // Simple placeholder mapping from IP to country; in a real system you would
            // use GeoIP or another geolocation service. For the project we keep this
            // deterministic but simple.
            $country = 'ZZ'; // unknown / default
            if ($ip) {
                if (strpos($ip, '203.0.113.') === 0) {
                    $country = 'US';
                } elseif (strpos($ip, '198.51.100.') === 0) {
                    $country = 'CA';
                } elseif ($ip === '192.0.2.5') {
                    $country = 'GB';
                } elseif ($ip === '192.0.2.6') {
                    $country = 'DE';
                }
            }

            $ins = $pdo->prepare('
                INSERT INTO downloads (member_id, item_id, download_date, ip_address, country_code)
                VALUES (:mid, :item_id, NOW(), :ip, :country)
            ');
            $ins->execute([
                'mid'     => $user['id'],
                'item_id' => $itemId,
                'ip'      => $ip,
                'country' => $country,
            ]);

            // In a production environment, you would now stream the file to the user.
            // For assignment/demo purposes we just show a message and the file path.
            $message = 'Download permitted. (In a full deployment, the file ' . e($item['file_path']) . ' would be streamed.)';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member · Download</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background:#020617; color:#e5e7eb; }
        main { max-width:700px; margin:2rem auto; padding:1.5rem; background:#020617; border-radius:0.75rem; border:1px solid rgba(148,163,184,.4); }
        h1 { margin-top:0; font-size:1.4rem; }
        .error { color:#fecaca; font-size:0.9rem; margin-top:0.5rem; }
        .message { color:#bbf7d0; font-size:0.9rem; margin-top:0.5rem; }
        a { color:#7dd3fc; text-decoration:none; }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    </style>
</head>
<body>
<main>
    <h1>Download item</h1>
    <?php if ($error): ?>
        <div class="error"><?php echo e($error); ?></div>
    <?php elseif ($message): ?>
        <div class="message"><?php echo e($message); ?></div>
    <?php endif; ?>
    <p style="margin-top:1rem; font-size:0.85rem;">
        <a href="/index.php">← Back to home</a>
    </p>
</main>
</body>
</html>


