<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$user    = Auth::requireRole('vendor', 'admin');
$offerId = (int)($_GET['offer_id'] ?? 0);
$days    = min((int)($_GET['days'] ?? 30), 365);

$db = Database::getInstance();

$stmt = $db->prepare('SELECT * FROM offers WHERE id = ?');
$stmt->execute([$offerId]);
$offer = $stmt->fetch();
if (!$offer) jsonError('Offer not found', 404);

// Current period
$curr = $db->prepare(
    'SELECT SUM(impressions) as imp, SUM(clicks) as clk, SUM(saves) as sv, SUM(redemptions) as red
     FROM vendor_daily_stats
     WHERE vendor_id = ? AND stat_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)'
);
$curr->execute([$offer['vendor_id'], $days]);
$c = $curr->fetch();

// Previous period (same duration before)
$prev = $db->prepare(
    'SELECT SUM(impressions) as imp, SUM(clicks) as clk, SUM(saves) as sv, SUM(redemptions) as red
     FROM vendor_daily_stats
     WHERE vendor_id = ? AND stat_date BETWEEN DATE_SUB(CURDATE(), INTERVAL ? DAY) AND DATE_SUB(CURDATE(), INTERVAL ? DAY)'
);
$prev->execute([$offer['vendor_id'], $days * 2, $days]);
$p = $prev->fetch();

$impressions = (int)($c['imp'] ?? 0);
$clicks      = (int)($c['clk'] ?? 0);
$saves       = (int)($c['sv']  ?? 0);
$redemptions = (int)($c['red'] ?? 0);

$ctr             = $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0;
$conversionRate  = $clicks > 0 ? round($redemptions / $clicks * 100, 2) : 0;
$estimatedRevenue= $redemptions * (float)($offer['offer_price'] ?? 0);

// ROI score: weighted composite
$roiScore = min(100, (int)(
    ($ctr / 10 * 30) +
    ($conversionRate / 20 * 30) +
    ($saves / max(1, $impressions) * 100 * 20) +
    ($redemptions > 0 ? 20 : 0)
));

// Trend helpers
$trend = fn($curr, $prev) => $prev > 0 ? round(($curr - $prev) / $prev * 100, 1) : 0;

jsonSuccess([
    'impressions'      => $impressions,
    'clicks'           => $clicks,
    'saves'            => $saves,
    'redemptions'      => $redemptions,
    'ctr'              => $ctr,
    'conversion_rate'  => $conversionRate,
    'estimated_revenue'=> round($estimatedRevenue, 2),
    'cost_per_click'   => 0,
    'roi_score'        => $roiScore,
    'trends'           => [
        'impressions' => $trend($impressions, (int)($p['imp'] ?? 0)),
        'clicks'      => $trend($clicks,      (int)($p['clk'] ?? 0)),
        'saves'       => $trend($saves,       (int)($p['sv']  ?? 0)),
        'redemptions' => $trend($redemptions, (int)($p['red'] ?? 0)),
    ],
]);
