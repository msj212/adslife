<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../middleware/CORS.php';
require_once __DIR__ . '/../config/database.php';

applyCORS();

try {
    $db = Database::getInstance();

    // Check which tables exist
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Check required tables
    $required = ['users', 'offers', 'coin_transactions', 'user_streaks', 'user_preferences', 'badges', 'user_badges'];
    $missing = array_diff($required, $tables);

    jsonSuccess([
        'db_connected' => true,
        'tables_found' => $tables,
        'missing_tables' => array_values($missing),
        'php_version' => PHP_VERSION,
        'app_url' => APP_URL,
        'frontend_url' => FRONTEND_URL,
    ]);
} catch (Throwable $e) {
    jsonError('DB Error: ' . $e->getMessage(), 500);
}
