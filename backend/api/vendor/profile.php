<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();

$user = Auth::require();
if (!in_array($user['role'], ['vendor', 'admin'])) {
    jsonError('Vendor access required', 403);
}

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare(
        'SELECT v.*, sp.name AS plan_name, sp.slug AS plan_slug, sp.price AS plan_price,
                sp.duration_days, sp.max_offers, sp.features
         FROM vendors v
         LEFT JOIN subscription_plans sp ON sp.slug = v.subscription_plan
         WHERE v.user_id = ?'
    );
    $stmt->execute([$user['user_id']]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$vendor) {
        jsonError('Vendor profile not found', 404);
    }
    if ($vendor['features']) {
        $vendor['features'] = json_decode($vendor['features'], true);
    }
    jsonSuccess($vendor);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $business_name = trim($body['business_name'] ?? '');
    $category      = trim($body['category']      ?? '');
    $description   = trim($body['description']   ?? '');
    $address       = trim($body['address']        ?? '');
    $city          = trim($body['city']           ?? '');
    $phone         = trim($body['phone']          ?? '');
    $website       = trim($body['website']        ?? '');
    $gst_number    = strtoupper(trim($body['gst_number'] ?? ''));
    $logo_url      = trim($body['logo_url']       ?? '');
    $lat           = isset($body['lat']) && $body['lat'] !== '' ? (float)$body['lat'] : null;
    $lng           = isset($body['lng']) && $body['lng'] !== '' ? (float)$body['lng'] : null;

    if (!$business_name) {
        jsonError('Business name is required', 400);
    }

    $stmt = $db->prepare(
        'UPDATE vendors SET business_name=?, category=?, description=?, address=?,
         city=?, phone=?, website=?, gst_number=?, logo_url=?, lat=?, lng=?
         WHERE user_id=?'
    );
    $stmt->execute([
        $business_name, $category, $description, $address,
        $city, $phone, $website, $gst_number, $logo_url, $lat, $lng,
        $user['user_id'],
    ]);

    jsonSuccess(null, 'Profile updated');
}

jsonError('Method not allowed', 405);
