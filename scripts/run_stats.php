<?php
// Author: Samy Belmihoub (40251504)

// CLI script to aggregate daily statistics into summary tables.
//
// Usage:
//   php scripts/run_stats.php           # aggregates for today
//   php scripts/run_stats.php 2025-01-01

require_once __DIR__ . '/../src/includes/db.php';

if (php_sapi_name() !== 'cli') {
    echo "This script is intended to be run from the command line.\n";
    exit(1);
}

$date = $argv[1] ?? date('Y-m-d');

echo "Running stats aggregation for {$date}\n";

$pdo = cfp_get_pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Aggregate per item: downloads_count and donations_sum
$itemStmt = $pdo->prepare('
    SELECT i.id AS item_id,
           COUNT(DISTINCT d.id) AS downloads_count,
           COALESCE(SUM(dn.amount), 0) AS donations_sum
    FROM items i
    LEFT JOIN downloads d
        ON d.item_id = i.id
       AND DATE(d.download_date) = :date
    LEFT JOIN donations dn
        ON dn.item_id = i.id
       AND DATE(dn.date) = :date
    GROUP BY i.id
');
$itemStmt->execute(['date' => $date]);
$itemRows = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

$pdo->beginTransaction();

$insertItem = $pdo->prepare('
    INSERT INTO daily_item_stats (stat_date, item_id, downloads_count, donations_sum)
    VALUES (:stat_date, :item_id, :downloads_count, :donations_sum)
    ON DUPLICATE KEY UPDATE
        downloads_count = VALUES(downloads_count),
        donations_sum   = VALUES(donations_sum)
');

foreach ($itemRows as $row) {
    $insertItem->execute([
        'stat_date'       => $date,
        'item_id'         => $row['item_id'],
        'downloads_count' => $row['downloads_count'],
        'donations_sum'   => $row['donations_sum'],
    ]);
}

// Aggregate per author
$authorStmt = $pdo->prepare('
    SELECT a.member_id AS author_id,
           COUNT(DISTINCT d.id) AS downloads_count,
           COALESCE(SUM(dn.amount), 0) AS donations_sum
    FROM authors a
    LEFT JOIN items i
        ON i.author_id = a.member_id
    LEFT JOIN downloads d
        ON d.item_id = i.id
       AND DATE(d.download_date) = :date
    LEFT JOIN donations dn
        ON dn.item_id = i.id
       AND DATE(dn.date) = :date
    GROUP BY a.member_id
');
$authorStmt->execute(['date' => $date]);
$authorRows = $authorStmt->fetchAll(PDO::FETCH_ASSOC);

$insertAuthor = $pdo->prepare('
    INSERT INTO daily_author_stats (stat_date, author_id, downloads_count, donations_sum)
    VALUES (:stat_date, :author_id, :downloads_count, :donations_sum)
    ON DUPLICATE KEY UPDATE
        downloads_count = VALUES(downloads_count),
        donations_sum   = VALUES(donations_sum)
');

foreach ($authorRows as $row) {
    $insertAuthor->execute([
        'stat_date'       => $date,
        'author_id'       => $row['author_id'],
        'downloads_count' => $row['downloads_count'],
        'donations_sum'   => $row['donations_sum'],
    ]);
}

$pdo->commit();

echo "Statistics aggregation complete.\n";


