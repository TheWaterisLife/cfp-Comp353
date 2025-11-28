<?php

/**
 * CFP database connection helper (PDO).
 *
 * Configure the constants below to match your local environment.
 * Example values (see INSTALL.md):
 *
 *   DB_HOST = 'localhost';
 *   DB_NAME = 'cfp';
 *   DB_USER = 'cfp_user';
 *   DB_PASS = 'CHANGE_ME';
 */

const DB_HOST = 'localhost';
const DB_NAME = 'cfp';
const DB_USER = 'root';   // TODO: change in your local setup
const DB_PASS = 'root';  // TODO: change in your local setup

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
        echo 'Database connection failed. Please check configuration in src/includes/db.php.';
        exit;
    }

    return $pdo;
}


