<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
Auth::requireRole('vendor', 'admin');

$vendorId = (int)($_GET['vendor_id'] ?? 0);
$goal     = in_array($_GET['goal'] ?? '', ['reach','conversions','awareness']) ? $_GET['goal'] : 'reach';
$category = $_GET['category'] ?? '';

$db = Database::getInstance();

// Category average CTR (from past 30 days)
$ctrStmt = $db->prepare(
    'SELECT AVG(vds.clicks / NULLIF(vds.impressions, 0)) * 100 as avg_ctr,
            AVG(vds.impressions) as avg_daily_imp
     FROM vendor_daily_stats vds
     JOIN vendors v ON vds.vendor_id = v.id
     WHERE v.category = ? AND vds.stat_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)'
);
$ctrStmt->execute([$category ?: 'food']);
$catData = $ctrStmt->fetch();

$avgCtr     = max(1.0, (float)($catData['avg_ctr'] ?? 3.5));
$avgDailyImp= max(50, (float)($catData['avg_daily_imp'] ?? 100));

// Best posting hours (city/category)
$hourStmt = $db->prepare(
    'SELECT HOUR(oi.created_at) as hr, COUNT(*) as count
     FROM offer_impressions oi JOIN offers o ON oi.offer_id = o.id
     JOIN vendors v ON o.vendor_id = v.id
     WHERE v.category = ?
     GROUP BY HOUR(oi.created_at) ORDER BY count DESC LIMIT 3'
);
$hourStmt->execute([$category ?: 'food']);
$bestHours = array_column($hourStmt->fetchAll(), 'hr');
if (empty($bestHours)) $bestHours = [18, 12, 20];

// Budget suggestions per goal
$suggestions = match ($goal) {
    'reach'       => ['min' => 0, 'max' => 0, 'duration' => 14, 'est_impressions' => (int)($avgDailyImp * 14)],
    'conversions' => ['min' => 0, 'max' => 0, 'duration' => 7,  'est_impressions' => (int)($avgDailyImp * 7)],
    'awareness'   => ['min' => 0, 'max' => 0, 'duration' => 30, 'est_impressions' => (int)($avgDailyImp * 30)],
    default       => ['min' => 0, 'max' => 0, 'duration' => 14, 'est_impressions' => (int)($avgDailyImp * 14)],
};

// Projected daily data for chart
$chartData = [];
for ($d = 1; $d <= $suggestions['duration']; $d++) {
    $chartData[] = [
        'day'         => $d,
        'impressions' => (int)($avgDailyImp * (0.8 + $d / $suggestions['duration'] * 0.4)),
        'clicks'      => (int)($avgDailyImp * $avgCtr / 100 * (0.8 + $d / $suggestions['duration'] * 0.4)),
    ];
}

jsonSuccess([
    'goal'             => $goal,
    'category'         => $category,
    'avg_ctr'          => round($avgCtr, 2),
    'recommended_duration_days' => $suggestions['duration'],
    'est_total_impressions'     => $suggestions['est_impressions'],
    'est_total_clicks'          => (int)($suggestions['est_impressions'] * $avgCtr / 100),
    'best_posting_hours'        => $bestHours,
    'chart_data'                => $chartData,
    'tips'                      => getTips($goal, $avgCtr, $category),
]);

function getTips(string $goal, float $ctr, string $category): array {
    $tips = ["Post during peak hours for maximum visibility"];
    if ($ctr < 3) $tips[] = "Add a strong discount (>25%) to improve CTR";
    if ($goal === 'conversions') $tips[] = "Use a clear coupon code to track redemptions";
    if ($goal === 'awareness') $tips[] = "Use eye-catching banner images for brand recall";
    $tips[] = "Vendors in '$category' with 2+ offers/week get 40% more engagement";
    return $tips;
}
