<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

$id = (int)($_GET['id'] ?? 0);
if (!$id) jsonError('Offer ID required', 400);

$db = Database::getInstance();

$stmt = $db->prepare(
    'SELECT
        o.id, o.vendor_id, o.title, o.description, o.category,
        o.discount_percent, o.original_price, o.offer_price,
        o.image_url, o.banner_url, o.video_url, o.coupon_code,
        o.max_redemptions, o.current_redemptions,
        o.valid_from, o.valid_until, o.is_featured, o.is_active,
        o.views, o.clicks, o.saves, o.created_at,
        v.business_name, v.logo_url AS vendor_logo,
        v.city AS vendor_city, v.address AS vendor_address,
        v.lat AS vendor_lat, v.lng AS vendor_lng,
        v.phone AS vendor_phone, v.email AS vendor_email,
        v.website AS vendor_website, v.category AS vendor_category,
        v.description AS vendor_description
     FROM offers o
     JOIN vendors v ON o.vendor_id = v.id
     WHERE o.id = ? AND o.is_active = 1'
);
$stmt->execute([$id]);
$offer = $stmt->fetch();

if (!$offer) jsonError('Offer not found', 404);

// Increment view count
$db->prepare('UPDATE offers SET views = views + 1 WHERE id = ?')->execute([$id]);

// Record interaction if authenticated
$auth = Auth::optional();
if ($auth) {
    try {
        $db->prepare(
            'INSERT INTO user_interactions (user_id, offer_id, action, category) VALUES (?,?,?,?)'
        )->execute([$auth['user_id'], $id, 'view', $offer['category']]);
    } catch (\Throwable $e) {}
}

// Map snake_case to camelCase-friendly keys for frontend
jsonSuccess([
    'id'                  => (int)$offer['id'],
    'vendorId'            => (int)$offer['vendor_id'],
    'title'               => $offer['title'],
    'description'         => $offer['description'],
    'category'            => $offer['category'],
    'discountPercent'     => (float)$offer['discount_percent'],
    'originalPrice'       => (float)$offer['original_price'],
    'offerPrice'          => (float)$offer['offer_price'],
    'imageUrl'            => $offer['image_url'],
    'bannerUrl'           => $offer['banner_url'],
    'videoUrl'            => $offer['video_url'],
    'couponCode'          => $offer['coupon_code'],
    'redeemUrl'           => $offer['redeem_url'],
    'maxRedemptions'      => (int)$offer['max_redemptions'],
    'currentRedemptions'  => (int)$offer['current_redemptions'],
    'validFrom'           => $offer['valid_from'],
    'validUntil'          => $offer['valid_until'],
    'isFeatured'          => (bool)$offer['is_featured'],
    'isActive'            => (bool)$offer['is_active'],
    'views'               => (int)$offer['views'],
    'clicks'              => (int)$offer['clicks'],
    'saves'               => (int)$offer['saves'],
    'createdAt'           => $offer['created_at'],
    'businessName'        => $offer['business_name'],
    'vendorLogo'          => $offer['vendor_logo'],
    'vendorCity'          => $offer['vendor_city'],
    'vendorAddress'       => $offer['vendor_address'],
    'vendorLat'           => $offer['vendor_lat'] ? (float)$offer['vendor_lat'] : null,
    'vendorLng'           => $offer['vendor_lng'] ? (float)$offer['vendor_lng'] : null,
    'vendorPhone'         => $offer['vendor_phone'],
    'vendorEmail'         => $offer['vendor_email'],
    'vendorWebsite'       => $offer['vendor_website'],
    'vendorCategory'      => $offer['vendor_category'],
    'vendorDescription'   => $offer['vendor_description'],
]);
