<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$user = Auth::require();
$uid  = (int)$user['user_id'];

// Handle unsave (DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $body    = json_decode(file_get_contents('php://input'), true);
    $offerId = (int)($body['offer_id'] ?? 0);
    if (!$offerId) { jsonError('offer_id required'); }
    $db = Database::getInstance();
    $db->prepare('DELETE FROM saved_offers WHERE user_id = ? AND offer_id = ?')->execute([$uid, $offerId]);
    jsonSuccess(['unsaved' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') { jsonError('Method not allowed', 405); }

$limit  = min((int)($_GET['limit']  ?? 30), 100);
$offset = (int)($_GET['offset'] ?? 0);

$db = Database::getInstance();

$stmt = $db->prepare(
    'SELECT
        o.id, o.title, o.description, o.category,
        o.discount_percent, o.original_price, o.offer_price,
        o.image_url, o.banner_url, o.coupon_code,
        o.valid_from, o.valid_until, o.is_active,
        o.views, o.clicks, o.saves,
        v.business_name, v.logo_url AS vendor_logo,
        v.city AS vendor_city, v.category AS vendor_category,
        so.saved_at
     FROM saved_offers so
     JOIN offers o ON so.offer_id = o.id
     JOIN vendors v ON o.vendor_id = v.id
     WHERE so.user_id = ?
     ORDER BY so.saved_at DESC
     LIMIT ? OFFSET ?'
);
$stmt->execute([$uid, $limit, $offset]);
$offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $db->prepare('SELECT COUNT(*) FROM saved_offers WHERE user_id = ?');
$countStmt->execute([$uid]);
$total = (int)$countStmt->fetchColumn();

jsonSuccess(['offers' => $offers, 'total' => $total, 'limit' => $limit, 'offset' => $offset]);
