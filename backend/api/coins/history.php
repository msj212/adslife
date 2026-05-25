<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../services/CoinsService.php';

applyCORS();
$user  = Auth::require();
$limit = min((int)($_GET['limit'] ?? 20), 100);
$uid   = (int)($_GET['user_id'] ?? $user['user_id']);

if ($uid !== $user['user_id'] && $user['role'] !== 'admin') jsonError('Forbidden', 403);

jsonSuccess(CoinsService::getHistory($uid, $limit));
