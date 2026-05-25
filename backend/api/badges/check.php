<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../services/BadgeService.php';

applyCORS();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$user = Auth::require();
$body = json_decode(file_get_contents('php://input'), true);
$uid  = (int)($body['user_id'] ?? $user['user_id']);

jsonSuccess(['new_badges' => BadgeService::checkAndAward($uid)]);
