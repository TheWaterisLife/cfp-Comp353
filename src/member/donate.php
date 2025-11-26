<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

cfp_require_role(['member', 'author', 'moderator', 'admin']);

$pdo = cfp_get_pdo();
$user = cfp_current_user();

$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$error = '';
$message = '';

// Fetch item + charities for form
$item = null;
if ($itemId > 0) {
    $stmt = $pdo->prepare('SELECT id, title FROM items WHERE id = :id');
    $stmt->execute(['id' => $itemId]);
    $item = $stmt->fetch();
}

$charities = $pdo->query('SELECT id, name FROM charities ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $charityId = isset($_POST['charity_id']) ? (int)$_POST['charity_id'] : 0;
    $amount = (float)($_POST['amount'] ?? 0);
    $percentCharityInput = (float)($_POST['percent_charity'] ?? 60);

    if ($itemId <= 0 || $charityId <= 0 || $amount <= 0) {
        $error = 'Item, charity, and a positive amount are required.';
    } else {
        // Enforce minimum 60% to charity
        $percentCharity = max(60.0, min(100.0, $percentCharityInput));
        $percentCfp = 20.0;
        $percentAuthor = 100.0 - $percentCharity - $percentCfp;

        if ($percentAuthor < 0) {
            // If charity% + cfp% exceeds 100, reduce CFP share.
            $percentAuthor = 0.0;
            $percentCfp = 100.0 - $percentCharity;
        }

        $stmt = $pdo->prepare('
            INSERT INTO donations (
                member_id, item_id, amount,
                percent_charity, percent_cfp, percent_author,
                charity_id, date
            )
            VALUES (
                :member_id, :item_id, :amount,
                :p_charity, :p_cfp, :p_author,
                :charity_id, NOW()
            )
        ');
        $stmt->execute([
            'member_id' => $user['id'],
            'item_id'   => $itemId,
            'amount'    => $amount,
            'p_charity' => $percentCharity,
            'p_cfp'     => $percentCfp,
            'p_author'  => $percentAuthor,
            'charity_id'=> $charityId,
        ]);

        $message = sprintf(
            'Donation recorded. Charity: %.1f%%, CFP: %.1f%%, Author: %.1f%%.',
            $percentCharity,
            $percentCfp,
            $percentAuthor
        );
    }

    // AJAX response
    if (($_POST['ajax'] ?? '') === '1' || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
        header('Content-Type: application/json');
        if ($error) {
            echo json_encode(['success' => false, 'error' => $error]);
        } else {
            echo json_encode(['success' => true, 'message' => $message]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member · Donate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background:#020617; color:#e5e7eb; }
        main { max-width:700px; margin:2rem auto; padding:1.5rem; background:#020617; border-radius:0.75rem; border:1px solid rgba(148,163,184,.4); }
        h1 { margin-top:0; font-size:1.4rem; }
        label { display:block; margin-top:0.75rem; font-size:0.85rem; color:#9ca3af; }
        input, select { width:100%; padding:0.45rem 0.6rem; border-radius:0.4rem; border:1px solid rgba(148,163,184,.4); background:#020617; color:#e5e7eb; }
        button { margin-top:1rem; padding:0.6rem 1.1rem; border-radius:999px; border:none; background:#22c55e; color:#022c22; cursor:pointer; font-weight:600; }
        .error { margin-top:0.75rem; color:#fecaca; font-size:0.9rem; }
        .message { margin-top:0.75rem; color:#bbf7d0; font-size:0.9rem; }
        a { color:#7dd3fc; text-decoration:none; }
        .hint { margin-top:0.75rem; font-size:0.8rem; color:#9ca3af; }
    </style>
</head>
<body>
<main>
    <h1>Donate</h1>
    <?php if ($item): ?>
        <p style="font-size:0.9rem;">You are donating in support of: <strong><?php echo e($item['title']); ?></strong></p>
    <?php endif; ?>

    <?php if ($error): ?><div class="error"><?php echo e($error); ?></div><?php endif; ?>
    <?php if ($message): ?><div class="message"><?php echo e($message); ?></div><?php endif; ?>

    <form method="post">
        <input type="hidden" name="item_id" value="<?php echo $item ? (int)$item['id'] : (int)$itemId; ?>">

        <label>
            Amount (e.g. 10.00)
            <input type="number" step="0.01" min="0.01" name="amount" required>
        </label>

        <label>
            Charity
            <select name="charity_id" required>
                <option value="">Select a charity…</option>
                <?php foreach ($charities as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Percent to charity (min 60%)
            <input type="number" step="1" min="60" max="100" name="percent_charity" value="60">
        </label>

        <button type="submit">Submit donation</button>
    </form>

    <p class="hint">
        Splits will be computed automatically to ensure at least 60% goes to charity.
    </p>

    <p style="margin-top:1rem; font-size:0.85rem;">
        <a href="<?php echo cfp_url('index.php'); ?>">← Back to home</a>
    </p>
</main>
</body>
</html>


