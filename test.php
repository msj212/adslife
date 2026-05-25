<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

echo json_encode([
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
    'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'unknown',
    'backend_exists' => file_exists(__DIR__ . '/backend/index.php'),
    'backend_config_exists' => file_exists(__DIR__ . '/backend/config/config.php'),
    'google_php_exists' => file_exists(__DIR__ . '/backend/api/auth/google.php'),
    'status' => 'PHP is working'
]);
