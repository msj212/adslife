<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

$user = Auth::requireRole('vendor', 'admin');
$db   = Database::getInstance();

// Vendor profile + plan
$vStmt = $db->prepare(
    'SELECT v.*, sp.name AS plan_name, sp.slug AS plan_slug,
            sp.max_offers, sp.duration_days, sp.price AS plan_price,
            sp.features AS plan_features
     FROM vendors v
     LEFT JOIN subscription_plans sp ON sp.slug = v.subscription_plan
     WHERE v.user_id = ?'
);
$vStmt->execute([$user['user_id']]);
$vendor = $vStmt->fetch();
if (!$vendor) jsonError('Vendor profile not found', 404);
$vendorId = (int)$vendor['id'];

// ── Offers summary ───────────────────────────────────────────
$oStmt = $db->prepare(
    'SELECT
        COUNT(*)                                          AS total_offers,
        SUM(is_active = 1)                               AS active_offers,
        SUM(is_active = 0)                               AS inactive_offers,
        SUM(valid_until IS NOT NULL AND valid_until < NOW() AND is_active = 1) AS expired_offers,
        COALESCE(SUM(views),  0)                         AS total_views,
        COALESCE(SUM(clicks), 0)                         AS total_clicks,
        COALESCE(SUM(saves),  0)                         AS total_saves,
        COALESCE(SUM(current_redemptions), 0)            AS total_redemptions
     FROM offers WHERE vendor_id = ?'
);
$oStmt->execute([$vendorId]);
$offerSummary = $oStmt->fetch();

// ── Recent offers (last 5) ───────────────────────────────────
$recStmt = $db->prepare(
    'SELECT id, title, category, discount_percent, views, clicks, saves,
            is_active, valid_until, current_redemptions, max_redemptions
     FROM offers WHERE vendor_id = ?
     ORDER BY created_at DESC LIMIT 5'
);
$recStmt->execute([$vendorId]);
$recentOffers = $recStmt->fetchAll();

// ── Current period stats (last 30 days from user_interactions) ──
$curStmt = $db->prepare(
    'SELECT
        COALESCE(SUM(action = "view"),   0) AS imp,
        COALESCE(SUM(action = "click"),  0) AS clk,
        COALESCE(SUM(action = "save"),   0) AS sv
     FROM user_interactions ui
     JOIN offers o ON ui.offer_id = o.id
     WHERE o.vendor_id = ? AND ui.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
);
$curStmt->execute([$vendorId]);
$cur = $curStmt->fetch();

// ── Previous period stats (31-60 days ago) ───────────────────
$prevStmt = $db->prepare(
    'SELECT
        COALESCE(SUM(action = "view"),   0) AS imp,
        COALESCE(SUM(action = "click"),  0) AS clk,
        COALESCE(SUM(action = "save"),   0) AS sv
     FROM user_interactions ui
     JOIN offers o ON ui.offer_id = o.id
     WHERE o.vendor_id = ?
       AND ui.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
       AND ui.created_at <  DATE_SUB(NOW(), INTERVAL 30 DAY)'
);
$prevStmt->execute([$vendorId]);
$prev = $prevStmt->fetch();

// Trend helper: % change vs previous period
function pctChange(int $cur, int $prev): string {
    if ($prev === 0) return $cur > 0 ? '+100%' : '0%';
    $change = round(($cur - $prev) / $prev * 100, 1);
    return ($change >= 0 ? '+' : '') . $change . '%';
}

$curImp = (int)$cur['imp'];  $prevImp = (int)$prev['imp'];
$curClk = (int)$cur['clk'];  $prevClk = (int)$prev['clk'];
$curSv  = (int)$cur['sv'];   $prevSv  = (int)$prev['sv'];
$curEng  = $curImp > 0 ? round(($curClk + $curSv) / $curImp * 100, 2) : 0;
$prevEng = $prevImp > 0 ? round(($prevClk + $prevSv) / $prevImp * 100, 2) : 0;

// ── Peak hours (last 30 days) ────────────────────────────────
$hourStmt = $db->prepare(
    'SELECT HOUR(oi.created_at) AS hr, COUNT(*) AS count
     FROM offer_impressions oi
     JOIN offers o ON oi.offer_id = o.id
     WHERE o.vendor_id = ? AND oi.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY HOUR(oi.created_at)'
);
$hourStmt->execute([$vendorId]);
$peakHours = array_fill(0, 24, 0);
foreach ($hourStmt->fetchAll() as $r) $peakHours[(int)$r['hr']] = (int)$r['count'];

// ── Daily trend (last 14 days from user_interactions) ────────
$dailyStmt = $db->prepare(
    'SELECT DATE(ui.created_at) AS stat_date,
            SUM(action = "view")  AS impressions,
            SUM(action = "click") AS clicks,
            SUM(action = "save")  AS saves
     FROM user_interactions ui
     JOIN offers o ON ui.offer_id = o.id
     WHERE o.vendor_id = ? AND ui.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
     GROUP BY DATE(ui.created_at)
     ORDER BY stat_date ASC'
);
$dailyStmt->execute([$vendorId]);
$dailyTrend = $dailyStmt->fetchAll();

jsonSuccess([
    // vendor info
    'vendor' => [
        'id'              => $vendorId,
        'business_name'   => $vendor['business_name'],
        'city'            => $vendor['city'],
        'status'          => $vendor['status'],
        'logo_url'        => $vendor['logo_url'],
        'total_followers' => (int)$vendor['total_followers'],
        'subscription_plan' => $vendor['subscription_plan'],
        'plan_name'       => $vendor['plan_name'] ?? $vendor['subscription_plan'],
        'plan_max_offers' => (int)$vendor['max_offers'],
    ],
    // KPI stats
    'stats' => [
        'impressions'     => $curImp,
        'clicks'          => $curClk,
        'saves'           => $curSv,
        'engagement_rate' => $curEng,
        'impressions_trend' => pctChange($curImp, $prevImp),
        'clicks_trend'      => pctChange($curClk, $prevClk),
        'saves_trend'       => pctChange($curSv, $prevSv),
        'engagement_trend'  => pctChange((int)round($curEng), (int)round($prevEng)),
    ],
    // offers
    'offers' => [
        'total'        => (int)$offerSummary['total_offers'],
        'active'       => (int)$offerSummary['active_offers'],
        'inactive'     => (int)$offerSummary['inactive_offers'],
        'expired'      => (int)$offerSummary['expired_offers'],
        'total_views'  => (int)$offerSummary['total_views'],
        'total_clicks' => (int)$offerSummary['total_clicks'],
        'total_saves'  => (int)$offerSummary['total_saves'],
        'total_redemptions' => (int)$offerSummary['total_redemptions'],
    ],
    'recent_offers' => $recentOffers,
    'peak_hours'    => $peakHours,
    'daily_trend'   => $dailyTrend,
]);
