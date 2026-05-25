<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/middleware/CORS.php';
applyCORS();

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'Endpoint not found']);
