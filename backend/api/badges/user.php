<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../services/BadgeService.php';

applyCORS();
$user = Auth::require();
$uid  = (int)($_GET['user_id'] ?? $user['user_id']);

jsonSuccess(BadgeService::getUserBadges($uid));
