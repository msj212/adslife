<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
Auth::requireRole('vendor', 'admin');

$vendorId = (int)($_GET['vendor_id'] ?? 0);
$limit    = min((int)($_GET['limit'] ?? 20), 100);
$offset   = (int)($_GET['offset'] ?? 0);
if (!$vendorId) jsonError('vendor_id required', 400);

$db = Database::getInstance();

$rows = $db->prepare(
    'SELECT u.id, u.name, u.avatar_url, u.city, vf.created_at AS followed_at
     FROM vendor_followers vf
     JOIN users u ON vf.user_id = u.id
     WHERE vf.vendor_id = ?
     ORDER BY vf.created_at DESC
     LIMIT ? OFFSET ?'
);
$rows->execute([$vendorId, $limit, $offset]);

$total = $db->prepare('SELECT COUNT(*) FROM vendor_followers WHERE vendor_id = ?');
$total->execute([$vendorId]);

// Followers gained this month vs last month
$thisMonth = $db->prepare(
    'SELECT COUNT(*) FROM vendor_followers WHERE vendor_id = ? AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())'
);
$thisMonth->execute([$vendorId]);

$lastMonth = $db->prepare(
    'SELECT COUNT(*) FROM vendor_followers WHERE vendor_id = ? AND MONTH(created_at)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH)) AND YEAR(created_at)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH))'
);
$lastMonth->execute([$vendorId]);

$thisM = (int)$thisMonth->fetchColumn();
$lastM = (int)$lastMonth->fetchColumn();
$growth = $lastM > 0 ? round(($thisM - $lastM) / $lastM * 100, 1) : ($thisM > 0 ? 100 : 0);

jsonSuccess([
    'total'      => (int)$total->fetchColumn(),
    'this_month' => $thisM,
    'last_month' => $lastM,
    'growth_pct' => $growth,
    'followers'  => $rows->fetchAll(),
]);
