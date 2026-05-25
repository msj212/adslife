<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/CoinsService.php';

applyCORS();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$user     = Auth::require();
$body     = json_decode(file_get_contents('php://input'), true);
$offerId  = (int)($body['offer_id'] ?? 0);
$platform = $body['platform'] ?? 'web';
$uid      = (int)($body['user_id'] ?? $user['user_id']);

if (!$offerId) jsonError('offer_id required');

$db = Database::getInstance();

// Track interaction
$db->prepare('INSERT IGNORE INTO user_interactions (user_id, offer_id, action) VALUES (?,?,"share")')->execute([$uid, $offerId]);

// Award coins for first share of this offer
$alreadyShared = $db->prepare('SELECT id FROM offer_shares WHERE user_id = ? AND offer_id = ?');
$alreadyShared->execute([$uid, $offerId]);

$coinsResult = null;
if (!$alreadyShared->fetch()) {
    $db->prepare('INSERT IGNORE INTO offer_shares (offer_id, user_id, platform) VALUES (?,?,?)')->execute([$offerId, $uid, $platform]);
    $coinsResult = CoinsService::award($uid, 'share_offer');
}

jsonSuccess(['tracked' => true, 'coins' => $coinsResult]);
