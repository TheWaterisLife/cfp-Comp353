<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';

cfp_require_role('admin');

$pdo = cfp_get_pdo();

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Top downloads (live)
$topDownloadsStmt = $pdo->prepare('
    SELECT i.id, i.title, COUNT(d.id) AS downloads
    FROM downloads d
    JOIN items i ON d.item_id = i.id
    WHERE YEAR(d.download_date) = :year
    GROUP BY i.id, i.title
    ORDER BY downloads DESC
    LIMIT 10
');
$topDownloadsStmt->execute(['year' => $year]);
$topDownloads = $topDownloadsStmt->fetchAll();

// Top authors by downloads (using daily_author_stats snapshot if available)
$topAuthorsStmt = $pdo->prepare('
    SELECT a.member_id, m.name, SUM(das.downloads_count) AS downloads
    FROM daily_author_stats das
    JOIN authors a ON das.author_id = a.member_id
    JOIN members m ON a.member_id = m.id
    WHERE YEAR(das.stat_date) = :year
    GROUP BY a.member_id, m.name
    ORDER BY downloads DESC
    LIMIT 10
');
$topAuthorsStmt->execute(['year' => $year]);
$topAuthors = $topAuthorsStmt->fetchAll();

// Annual accesses by month
$monthlyStmt = $pdo->prepare('
    SELECT MONTH(download_date) AS month, COUNT(*) AS downloads
    FROM downloads
    WHERE YEAR(download_date) = :year
    GROUP BY MONTH(download_date)
    ORDER BY month
');
$monthlyStmt->execute(['year' => $year]);
$monthly = $monthlyStmt->fetchAll();

// Annual donations by charity
$donationStmt = $pdo->prepare('
    SELECT c.name, SUM(d.amount) AS total_amount
    FROM donations d
    JOIN charities c ON d.charity_id = c.id
    WHERE YEAR(d.date) = :year
    GROUP BY c.name
    ORDER BY total_amount DESC
');
$donationStmt->execute(['year' => $year]);
$donationsByCharity = $donationStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin · Statistics</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="cfp-shell">
<header class="cfp-header">
    <div class="cfp-header-inner">
        <div class="cfp-logo"><a href="/index.php">CopyForward Publishing</a></div>
        <nav class="cfp-nav">
            <a href="/index.php">Home</a>
            <a href="/admin/members.php">Admin</a>
        </nav>
    </div>
</header>
<main class="cfp-main">
    <div class="cfp-main-inner">
        <section class="cfp-panel">
            <h1 class="cfp-h1">Statistics (<?php echo (int)$year; ?>)</h1>
            <form method="get" style="margin-top:0.5rem;">
                <label class="cfp-label">
                    Year
                    <input class="cfp-input" type="number" name="year" value="<?php echo (int)$year; ?>" min="2000" max="2100" style="max-width:120px;">
                </label>
                <button class="cfp-btn cfp-btn-primary" type="submit" style="margin-top:0.5rem;">Update</button>
            </form>

            <div class="cfp-grid cfp-grid-2" style="margin-top:1.5rem;">
                <div>
                    <h2 style="font-size:0.95rem;">Top downloaded items</h2>
                    <table class="cfp-table">
                        <thead><tr><th>Item</th><th>Downloads</th></tr></thead>
                        <tbody>
                        <?php foreach ($topDownloads as $row): ?>
                            <tr>
                                <td><?php echo e($row['title']); ?></td>
                                <td><?php echo (int)$row['downloads']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$topDownloads): ?>
                            <tr><td colspan="2" class="cfp-muted">No downloads recorded.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <p style="margin-top:0.5rem; font-size:0.8rem;">
                        <a href="/admin/export_stats.php?type=items&year=<?php echo (int)$year; ?>">Export CSV</a>
                    </p>
                </div>
                <div>
                    <h2 style="font-size:0.95rem;">Top authors by downloads</h2>
                    <table class="cfp-table">
                        <thead><tr><th>Author</th><th>Downloads</th></tr></thead>
                        <tbody>
                        <?php foreach ($topAuthors as $row): ?>
                            <tr>
                                <td><?php echo e($row['name']); ?></td>
                                <td><?php echo (int)$row['downloads']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$topAuthors): ?>
                            <tr><td colspan="2" class="cfp-muted">No author stats available.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <p style="margin-top:0.5rem; font-size:0.8rem;">
                        <a href="/admin/export_stats.php?type=authors&year=<?php echo (int)$year; ?>">Export CSV</a>
                    </p>
                </div>
            </div>

            <div class="cfp-grid cfp-grid-2" style="margin-top:1.5rem;">
                <div>
                    <h2 style="font-size:0.95rem;">Monthly accesses</h2>
                    <table class="cfp-table">
                        <thead><tr><th>Month</th><th>Downloads</th></tr></thead>
                        <tbody>
                        <?php foreach ($monthly as $row): ?>
                            <tr>
                                <td><?php echo (int)$row['month']; ?></td>
                                <td><?php echo (int)$row['downloads']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$monthly): ?>
                            <tr><td colspan="2" class="cfp-muted">No data for this year.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div>
                    <h2 style="font-size:0.95rem;">Donations by charity</h2>
                    <table class="cfp-table">
                        <thead><tr><th>Charity</th><th>Total amount</th></tr></thead>
                        <tbody>
                        <?php foreach ($donationsByCharity as $row): ?>
                            <tr>
                                <td><?php echo e($row['name']); ?></td>
                                <td><?php echo e($row['total_amount']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$donationsByCharity): ?>
                            <tr><td colspan="2" class="cfp-muted">No donations recorded.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <p style="margin-top:0.5rem; font-size:0.8rem;">
                        <a href="/admin/export_stats.php?type=donations&year=<?php echo (int)$year; ?>">Export CSV</a>
                    </p>
                </div>
            </div>
        </section>
    </div>
</main>
</body>
</html>


