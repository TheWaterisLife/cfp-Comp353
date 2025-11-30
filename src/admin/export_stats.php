<?php
// Author: Sem Axil Rais (40113324)

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';

cfp_require_role('admin');

$pdo = cfp_get_pdo();

$type = $_GET['type'] ?? '';
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="cfp_' . $type . '_' . $year . '.csv"');

$out = fopen('php://output', 'w');

if ($type === 'items') {
    fputcsv($out, ['Item ID', 'Title', 'Downloads (' . $year . ')']);
    $stmt = $pdo->prepare('
        SELECT i.id, i.title, COUNT(d.id) AS downloads
        FROM downloads d
        JOIN items i ON d.item_id = i.id
        WHERE YEAR(d.download_date) = :year
        GROUP BY i.id, i.title
        ORDER BY downloads DESC
    ');
    $stmt->execute(['year' => $year]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [$row['id'], $row['title'], $row['downloads']]);
    }
} elseif ($type === 'authors') {
    fputcsv($out, ['Author ID', 'Name', 'Downloads (' . $year . ')']);
    $stmt = $pdo->prepare('
        SELECT a.member_id, m.name, SUM(das.downloads_count) AS downloads
        FROM daily_author_stats das
        JOIN authors a ON das.author_id = a.member_id
        JOIN members m ON a.member_id = m.id
        WHERE YEAR(das.stat_date) = :year
        GROUP BY a.member_id, m.name
        ORDER BY downloads DESC
    ');
    $stmt->execute(['year' => $year]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [$row['member_id'], $row['name'], $row['downloads']]);
    }
} elseif ($type === 'donations') {
    fputcsv($out, ['Charity', 'Total amount (' . $year . ')']);
    $stmt = $pdo->prepare('
        SELECT c.name, SUM(d.amount) AS total_amount
        FROM donations d
        JOIN charities c ON d.charity_id = c.id
        WHERE YEAR(d.date) = :year
        GROUP BY c.name
        ORDER BY total_amount DESC
    ');
    $stmt->execute(['year' => $year]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [$row['name'], $row['total_amount']]);
    }
} else {
    fputcsv($out, ['Error', 'Unknown export type']);
}

fclose($out);
exit;


