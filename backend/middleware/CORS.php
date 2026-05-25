<?php
require_once __DIR__ . '/../config/config.php';

function applyCORS(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = [
        FRONTEND_URL,
        'https://adslife.in',
        'https://adslife.in',
        'http://localhost:5173',
        'http://localhost:3000',
    ];

    if (in_array($origin, $allowed, true)) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        header('Access-Control-Allow-Origin: ' . FRONTEND_URL);
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit(0);
    }
}

function jsonSuccess(mixed $data, string $message = 'OK', int $code = 200): never {
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data, 'message' => $message]);
    exit;
}

function jsonError(string $error, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $error, 'code' => $code]);
    exit;
}
