<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();

set_exception_handler(function(Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
    exit;
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$body = json_decode(file_get_contents('php://input'), true);
$email    = trim($body['email'] ?? '');
$password = $body['password'] ?? '';

if (!$email || !$password) jsonError('Email and password required');

$db   = Database::getInstance();
$stmt = $db->prepare('SELECT id, name, email, password_hash, role, coins, streak_days, city, lat, lng, avatar_url FROM users WHERE email = ? AND is_active = 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    jsonError('Invalid credentials', 401);
}

// Update login streak and count
$db->prepare('UPDATE users SET last_login = CURDATE(), login_count = login_count + 1 WHERE id = ?')->execute([$user['id']]);

// Award daily login coins via streak service
require_once __DIR__ . '/../../services/StreakService.php';
require_once __DIR__ . '/../../services/CoinsService.php';
StreakService::update($user['id']);
CoinsService::award($user['id'], 'daily_login');

$token = Auth::generateToken($user['id'], $user['role']);
unset($user['password_hash']);

jsonSuccess(['user' => $user, 'token' => $token], 'Login successful');
