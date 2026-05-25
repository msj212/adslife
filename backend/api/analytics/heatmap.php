<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$user     = Auth::requireRole('vendor', 'admin');
$vendorId = (int)($_GET['vendor_id'] ?? 0);
$offerId  = (int)($_GET['offer_id'] ?? 0);
$days     = min((int)($_GET['days'] ?? 30), 365);

$db  = Database::getInstance();
$sql = 'SELECT ROUND(user_lat, 2) as lat, ROUND(user_lng, 2) as lng, COUNT(*) as weight
        FROM offer_impressions oi
        JOIN offers o ON oi.offer_id = o.id
        WHERE o.vendor_id = ?
          AND oi.user_lat IS NOT NULL
          AND oi.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
$params = [$vendorId, $days];

if ($offerId) {
    $sql .= ' AND oi.offer_id = ?';
    $params[] = $offerId;
}

$sql .= ' GROUP BY ROUND(user_lat, 2), ROUND(user_lng, 2) ORDER BY weight DESC LIMIT 500';

$stmt = $db->prepare($sql);
$stmt->execute($params);

jsonSuccess($stmt->fetchAll());
