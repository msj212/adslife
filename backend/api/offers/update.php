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

$body = json_decode(file_get_contents('php://input'), true) ?? [];

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

// Ensure offer belongs to this vendor
$check = $db->prepare('SELECT id FROM offers WHERE id = ? AND vendor_id = ?');
$check->execute([$offer_id, $vendor['id']]);
if (!$check->fetch()) {
    jsonError('Offer not found', 404);
}

$title       = trim($body['title'] ?? '');
$description = trim($body['description'] ?? '');
$category    = trim($body['category'] ?? 'general');
$image_url   = trim($body['image_url']  ?? '');
$coupon_code = trim($body['coupon_code'] ?? '');
$redeem_url  = trim($body['redeem_url'] ?? '');
$discount    = isset($body['discount_percent']) ? (float)$body['discount_percent'] : null;
$orig_price  = isset($body['original_price'])   ? (float)$body['original_price']  : null;
$offer_price = isset($body['offer_price'])       ? (float)$body['offer_price']     : null;
$max_red     = isset($body['max_redemptions'])   ? (int)$body['max_redemptions']   : 0;
$valid_from  = !empty($body['valid_from'])  ? $body['valid_from']  . ' 00:00:00' : null;
$valid_until = !empty($body['valid_until']) ? $body['valid_until'] . ' 23:59:59' : null;
$is_active   = isset($body['is_active'])    ? (int)(bool)$body['is_active'] : 1;

if (!$title) {
    jsonError('Title is required', 400);
}

$stmt = $db->prepare(
    'UPDATE offers SET title=?, description=?, category=?, discount_percent=?,
        original_price=?, offer_price=?, image_url=?, coupon_code=?, redeem_url=?,
        max_redemptions=?, valid_from=?, valid_until=?, is_active=?
     WHERE id=? AND vendor_id=?'
);
$stmt->execute([
    $title, $description, $category, $discount,
    $orig_price, $offer_price, $image_url, $coupon_code, $redeem_url ?: null,
    $max_red, $valid_from, $valid_until, $is_active,
    $offer_id, $vendor['id'],
]);

require_once __DIR__ . '/../../services/FirestoreService.php';
FirestoreService::offerChanged($vendor['id']);
jsonSuccess(['id' => $offer_id], 'Offer updated');
