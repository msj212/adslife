<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();

$lat    = (float)($_GET['lat'] ?? 13.0827);
$lng    = (float)($_GET['lng'] ?? 80.2707);
$radius = min((float)($_GET['radius'] ?? 5), 50);
$limit  = min((int)($_GET['limit'] ?? 20), 100);

$db   = Database::getInstance();
$stmt = $db->prepare(
    'SELECT o.*, v.business_name, v.logo_url as vendor_logo, v.city as vendor_city,
            (6371 * acos(cos(radians(?)) * cos(radians(v.lat)) *
             cos(radians(v.lng) - radians(?)) + sin(radians(?)) * sin(radians(v.lat)))) AS distance
     FROM offers o
     JOIN vendors v ON o.vendor_id = v.id
     WHERE o.is_active = 1
       AND (o.valid_until IS NULL OR o.valid_until >= NOW())
       AND v.status = "approved"
       AND v.lat IS NOT NULL
     HAVING distance <= ?
     ORDER BY distance ASC
     LIMIT ?'
);
$stmt->execute([$lat, $lng, $lat, $radius, $limit]);

jsonSuccess($stmt->fetchAll());
