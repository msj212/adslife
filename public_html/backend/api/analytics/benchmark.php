<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$user     = Auth::requireRole('vendor', 'admin');
$vendorId = (int)($_GET['vendor_id'] ?? 0);

$db = Database::getInstance();

// Vendor stats
$vStmt = $db->prepare(
    'SELECT AVG(vds.clicks / NULLIF(vds.impressions, 0)) * 100 as avg_ctr,
            COUNT(DISTINCT o.id) as offers_count
     FROM vendor_daily_stats vds
     JOIN offers o ON vds.vendor_id = o.vendor_id
     WHERE vds.vendor_id = ? AND vds.stat_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)'
);
$vStmt->execute([$vendorId]);
$vStats = $vStmt->fetch();

$discStmt = $db->prepare('SELECT AVG(discount_percent) as avg_discount FROM offers WHERE vendor_id = ? AND is_active = 1');
$discStmt->execute([$vendorId]);
$vDiscount = $discStmt->fetch();

// Get vendor category
$catStmt = $db->prepare('SELECT category FROM vendors WHERE id = ?');
$catStmt->execute([$vendorId]);
$vendor   = $catStmt->fetch();
$category = $vendor['category'] ?? '';

// Category averages (all vendors in same category)
$catStmt2 = $db->prepare(
    'SELECT AVG(vds.clicks / NULLIF(vds.impressions, 0)) * 100 as avg_ctr,
            COUNT(DISTINCT vds.vendor_id) as vendor_count
     FROM vendor_daily_stats vds
     JOIN vendors v ON vds.vendor_id = v.id
     WHERE v.category = ? AND v.status = "approved"
       AND vds.stat_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)'
);
$catStmt2->execute([$category]);
$catStats = $catStmt2->fetch();

$catDiscStmt = $db->prepare(
    'SELECT AVG(o.discount_percent) as avg_discount, COUNT(o.id) / COUNT(DISTINCT v.id) as offers_per_month
     FROM offers o JOIN vendors v ON o.vendor_id = v.id
     WHERE v.category = ? AND o.is_active = 1
       AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
);
$catDiscStmt->execute([$category]);
$catDisc = $catDiscStmt->fetch();

$vendorCtr     = round((float)($vStats['avg_ctr'] ?? 0), 2);
$catCtr        = round((float)($catStats['avg_ctr'] ?? 0), 2);
$vendorDisc    = round((float)($vDiscount['avg_discount'] ?? 0), 1);
$catDiscAvg    = round((float)($catDisc['avg_discount'] ?? 0), 1);
$vendorOffers  = (int)($vStats['offers_count'] ?? 0);
$catOffers     = round((float)($catDisc['offers_per_month'] ?? 0), 1);

// Percentile (rough: vendor / category comparison)
$ctrPct      = $catCtr > 0 ? min(99, (int)($vendorCtr / $catCtr * 50)) : 50;
$discPct     = $catDiscAvg > 0 ? min(99, (int)($vendorDisc / $catDiscAvg * 50)) : 50;
$activityPct = $catOffers > 0 ? min(99, (int)($vendorOffers / $catOffers * 50)) : 50;

jsonSuccess([
    'vendor'       => ['avg_ctr' => $vendorCtr, 'avg_discount' => $vendorDisc, 'offers_per_month' => $vendorOffers],
    'category_avg' => ['avg_ctr' => $catCtr,    'avg_discount' => $catDiscAvg,'offers_per_month' => $catOffers],
    'percentile'   => ['ctr' => $ctrPct,        'discount' => $discPct,       'activity' => $activityPct],
    'category'     => $category,
]);
