<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/CoinsService.php';

applyCORS();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$user  = Auth::require();
$body  = json_decode(file_get_contents('php://input'), true);
$uid   = (int)$user['user_id'];
$offerId = (int)($body['offer_id'] ?? 0);
$action  = $body['action'] ?? '';

$validActions = ['view', 'click', 'save', 'redeem', 'share', 'skip'];
if (!in_array($action, $validActions, true)) jsonError('Invalid action');
if (!$offerId) jsonError('offer_id required');

$db = Database::getInstance();

// Fetch offer category
$offerStmt = $db->prepare('SELECT category FROM offers WHERE id = ?');
$offerStmt->execute([$offerId]);
$offer = $offerStmt->fetch();
if (!$offer) jsonError('Offer not found', 404);

// Record interaction
$db->prepare(
    'INSERT INTO user_interactions (user_id, offer_id, action, category) VALUES (?,?,?,?)'
)->execute([$uid, $offerId, $action, $offer['category']]);

// Update offer counters
$colMap = ['view' => 'views', 'click' => 'clicks', 'save' => 'saves'];
if (isset($colMap[$action])) {
    $col = $colMap[$action];
    $db->prepare("UPDATE offers SET $col = $col + 1 WHERE id = ?")->execute([$offerId]);
}

// Record save in dedicated table
if ($action === 'save') {
    $db->prepare(
        'INSERT IGNORE INTO saved_offers (user_id, offer_id) VALUES (?, ?)'
    )->execute([$uid, $offerId]);
}

// Award coins
$coinsResult = null;
if ($action === 'save') {
    // Check if first save ever
    $saveCount = $db->prepare('SELECT COUNT(*) FROM user_interactions WHERE user_id = ? AND action = "save"');
    $saveCount->execute([$uid]);
    $coinsResult = (int)$saveCount->fetchColumn() === 1
        ? CoinsService::award($uid, 'first_save')
        : CoinsService::award($uid, 'save_offer');
} elseif ($action === 'redeem') {
    $coinsResult = CoinsService::award($uid, 'redeem_offer');
    $db->prepare('UPDATE offers SET current_redemptions = current_redemptions + 1 WHERE id = ?')->execute([$offerId]);
}

// Update user preferences (weighted moving average)
updatePreferences($db, $uid, $offer['category'], $offerId, $action);

// Signal real-time stats update to vendor dashboard
$vendorStmt = $db->prepare('SELECT vendor_id FROM offers WHERE id = ?');
$vendorStmt->execute([$offerId]);
$vendorRow = $vendorStmt->fetch();
if ($vendorRow) {
    require_once __DIR__ . '/../../services/FirestoreService.php';
    FirestoreService::interactionLogged((int)$vendorRow['vendor_id']);
}

jsonSuccess(['recorded' => true, 'coins' => $coinsResult]);

function updatePreferences(PDO $db, int $uid, string $category, int $offerId, string $action): void {
    $pref = $db->prepare('SELECT * FROM user_preferences WHERE user_id = ?');
    $pref->execute([$uid]);
    $row = $pref->fetch();

    $categories = $row ? (json_decode($row['preferred_categories'], true) ?: []) : [];
    $vendors    = $row ? (json_decode($row['preferred_vendors'], true) ?: []) : [];

    // Positive signals boost weight; negative (skip) reduce it
    $weight = match ($action) {
        'save', 'redeem' => 2,
        'click'          => 1,
        'skip'           => -1,
        default          => 0,
    };

    if ($weight > 0 && $category && !in_array($category, $categories, true)) {
        $categories[] = $category;
        $categories   = array_slice($categories, -10); // keep last 10
    } elseif ($weight < 0 && ($key = array_search($category, $categories)) !== false) {
        unset($categories[$key]);
        $categories = array_values($categories);
    }

    if ($row) {
        $db->prepare(
            'UPDATE user_preferences SET preferred_categories=?, updated_at=NOW() WHERE user_id=?'
        )->execute([json_encode($categories), $uid]);
    } else {
        $db->prepare(
            'INSERT INTO user_preferences (user_id, preferred_categories, preferred_vendors) VALUES (?,?,?)'
        )->execute([$uid, json_encode($categories), json_encode($vendors)]);
    }
}
