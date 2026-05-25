<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();

$city  = $_GET['city'] ?? 'Chennai';
$limit = min((int)($_GET['limit'] ?? 20), 50);

$db   = Database::getInstance();
$stmt = $db->prepare(
    'SELECT o.*, v.business_name, v.logo_url as vendor_logo, v.city as vendor_city,
            v.lat as vlat, v.lng as vlng, v.address as vendor_address,
            v.phone as vendor_phone, v.website as vendor_website,
            (o.views + o.clicks * 3 + o.saves * 5) as trend_score
     FROM offers o
     JOIN vendors v ON o.vendor_id = v.id
     WHERE o.is_active = 1
       AND (o.valid_until IS NULL OR o.valid_until >= NOW())
       AND v.status = "approved"
       AND (v.city = ? OR ? = "")
     ORDER BY trend_score DESC, o.created_at DESC
     LIMIT ?'
);
$stmt->execute([$city, $city, $limit]);

jsonSuccess($stmt->fetchAll());
