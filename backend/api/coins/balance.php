<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../services/CoinsService.php';

applyCORS();
$user = Auth::require();

$targetId = (int)($_GET['user_id'] ?? $user['user_id']);
if ($targetId !== $user['user_id'] && $user['role'] !== 'admin') {
    jsonError('Forbidden', 403);
}

jsonSuccess(['balance' => CoinsService::getBalance($targetId)]);
