<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); }

$auth  = Auth::require();
$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$token = trim($body['token'] ?? '');
$info  = substr(trim($body['device_info'] ?? ''), 0, 200);

if (!$token) { jsonError('FCM token required', 400); }

$db = Database::getInstance();

// Upsert — update user_id if token already exists (device re-login)
$db->prepare(
    'INSERT INTO user_fcm_tokens (user_id, token, device_info)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), device_info = VALUES(device_info), updated_at = NOW()'
)->execute([$auth['user_id'], $token, $info]);

jsonSuccess(null, 'Token saved');
