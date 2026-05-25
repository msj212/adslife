<?php
// Called internally after a new offer is created — notifies users whose interests match
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
Auth::requireRole('vendor', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); }

$body     = json_decode(file_get_contents('php://input'), true);
$offerId  = (int)($body['offer_id'] ?? 0);
if (!$offerId) { jsonError('offer_id required', 400); }

$db = Database::getInstance();

$offer = $db->prepare(
    'SELECT o.title, o.category, o.discount_percent, v.business_name
     FROM offers o JOIN vendors v ON o.vendor_id = v.id WHERE o.id = ?'
);
$offer->execute([$offerId]);
$row = $offer->fetch();
if (!$row) { jsonError('Offer not found', 404); }

// Find users whose preferred_categories include this offer's category
$users = $db->prepare(
    'SELECT u.id FROM users u
     JOIN user_preferences p ON u.id = p.user_id
     WHERE JSON_CONTAINS(p.preferred_categories, JSON_QUOTE(?))
       AND u.is_active = 1'
);
$users->execute([$row['category']]);
$targets = $users->fetchAll(PDO::FETCH_COLUMN);

if (empty($targets)) { jsonSuccess(['notified' => 0]); }

$insert = $db->prepare(
    'INSERT INTO notifications (user_id, title, body, type, offer_id, is_read) VALUES (?,?,?,?,?,0)'
);
$title = "New {$row['category']} deal near you! 🎁";
$body  = "{$row['business_name']}: {$row['title']} — {$row['discount_percent']}% OFF";

foreach ($targets as $uid) {
    $insert->execute([$uid, $title, $body, 'offer_match', $offerId]);
}

// Send Firebase push notifications
require_once __DIR__ . '/../../services/PushService.php';
PushService::send($targets, $title, $body, [
    'type'     => 'offer_match',
    'offer_id' => (string)$offerId,
]);

jsonSuccess(['notified' => count($targets)]);
