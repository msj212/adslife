<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();

// Admin-only or CLI call
if (php_sapi_name() !== 'cli') {
    Auth::requireRole('admin');
}

$db     = Database::getInstance();
$period = $_GET['period'] ?? 'monthly';

// Compute scores from interaction data
$sql = "
    SELECT
        ui.user_id,
        u.city,
        SUM(CASE WHEN ui.action = 'save'   THEN 1 ELSE 0 END) AS total_saves,
        SUM(CASE WHEN ui.action = 'redeem' THEN 3 ELSE 0 END) AS redemption_score,
        0 AS review_score,
        COALESCE(us.current_streak, 0) * 2 AS streak_score
    FROM user_interactions ui
    JOIN users u ON ui.user_id = u.id
    LEFT JOIN user_streaks us ON ui.user_id = us.user_id
    WHERE (? = 'monthly' AND ui.created_at >= DATE_FORMAT(NOW(),'%Y-%m-01'))
       OR (? = 'weekly'  AND YEARWEEK(ui.created_at) = YEARWEEK(NOW()))
       OR ? = 'alltime'
    GROUP BY ui.user_id, u.city, us.current_streak
";

$stmt = $db->prepare($sql);
$stmt->execute([$period, $period, $period]);
$rows = $stmt->fetchAll();

// Truncate current period entries
$db->prepare('DELETE FROM leaderboard WHERE period = ?')->execute([$period]);

// Insert new scores
$insert = $db->prepare(
    'INSERT INTO leaderboard (user_id, city, total_saves, total_redemptions, total_reviews, score, rank_position, period)
     VALUES (?, ?, ?, ?, 0, ?, 0, ?)'
);

usort($rows, fn($a, $b) =>
    ($b['total_saves'] + $b['redemption_score'] + $b['streak_score'])
    <=> ($a['total_saves'] + $a['redemption_score'] + $a['streak_score'])
);

foreach ($rows as $i => $row) {
    $score = $row['total_saves'] + $row['redemption_score'] + $row['review_score'] + $row['streak_score'];
    $insert->execute([$row['user_id'], $row['city'], $row['total_saves'], intdiv($row['redemption_score'], 3), $score, $period]);
}

jsonSuccess(['rebuilt' => count($rows), 'period' => $period]);
