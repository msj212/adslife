<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$user = Auth::require();
if (!in_array($user['role'], ['vendor', 'admin'])) {
    jsonError('Vendor access required', 403);
}

$db = Database::getInstance();

$vs = $db->prepare('SELECT id FROM vendors WHERE user_id = ?');
$vs->execute([$user['user_id']]);
$vendor = $vs->fetch(PDO::FETCH_ASSOC);
if (!$vendor) {
    jsonSuccess([]);
}

$stmt = $db->prepare(
    'SELECT id, title, description, category, discount_percent, original_price, offer_price,
            image_url, coupon_code, redeem_url, max_redemptions, current_redemptions,
            valid_from, valid_until, is_active, views, clicks, saves, created_at
     FROM offers WHERE vendor_id = ? ORDER BY created_at DESC'
);
$stmt->execute([$vendor['id']]);
jsonSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
