<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$user = Auth::require();
$uid  = (int)$user['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

$db   = Database::getInstance();
$stmt = $db->prepare(
    'SELECT
        v.id, v.business_name, v.category, v.logo_url,
        v.city, v.address, v.phone, v.website, v.description,
        v.total_followers, v.status,
        vf.created_at AS followed_at,
        (SELECT COUNT(*) FROM offers WHERE vendor_id = v.id AND is_active = 1) AS active_offers
     FROM vendor_followers vf
     JOIN vendors v ON vf.vendor_id = v.id
     WHERE vf.user_id = ?
     ORDER BY vf.created_at DESC'
);
$stmt->execute([$uid]);
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

jsonSuccess(['vendors' => $vendors, 'total' => count($vendors)]);
