<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$user = Auth::require();
if (!in_array($user['role'], ['vendor', 'admin'])) {
    jsonError('Vendor access required', 403);
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$offer_id = (int)($body['id'] ?? 0);
if (!$offer_id) {
    jsonError('Offer ID required', 400);
}

$db = Database::getInstance();

$vs = $db->prepare('SELECT id FROM vendors WHERE user_id = ?');
$vs->execute([$user['user_id']]);
$vendor = $vs->fetch(PDO::FETCH_ASSOC);
if (!$vendor) {
    jsonError('Vendor profile not found', 404);
}

$check = $db->prepare('SELECT id FROM offers WHERE id = ? AND vendor_id = ?');
$check->execute([$offer_id, $vendor['id']]);
if (!$check->fetch()) {
    jsonError('Offer not found', 404);
}

$db->prepare('DELETE FROM offers WHERE id = ? AND vendor_id = ?')
   ->execute([$offer_id, $vendor['id']]);

require_once __DIR__ . '/../../services/FirestoreService.php';
FirestoreService::offerChanged($vendor['id']);
jsonSuccess(null, 'Offer deleted');
