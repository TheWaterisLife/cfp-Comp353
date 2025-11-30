<?php
// Author: Samy Belmihoub (40251504)

/**
 * Database configuration for CFP.
 */

const DB_HOST = 'lvc353.encs.concordia.ca';
const DB_NAME = 'lvc353_2';
const DB_USER = 'lvc353_2';
const DB_PASS = 'itchywhale23';

/**
 * Returns a shared PDO instance for the CFP application.
 *
 * @return PDO
 */
function cfp_get_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_NAME
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        // In production you may want to log this instead of displaying details.
        http_response_code(500);
        echo 'Database connection failed. Please check configuration in includes/db.php.';
        exit;
    }

    return $pdo;
}


