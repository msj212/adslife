<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$user   = Auth::requireRole('vendor', 'admin');
$testId = (int)($_GET['test_id'] ?? 0);

$db   = Database::getInstance();
$stmt = $db->prepare('SELECT * FROM ab_tests WHERE id = ?');
$stmt->execute([$testId]);
$test = $stmt->fetch();
if (!$test) jsonError('Test not found', 404);

function getOfferStats(PDO $db, int $offerId): array {
    $s = $db->prepare('SELECT views, clicks, saves FROM offers WHERE id = ?');
    $s->execute([$offerId]);
    $o = $s->fetch();
    $imp = max(1, (int)($o['views'] ?? 0));
    $clk = (int)($o['clicks'] ?? 0);
    return [
        'id'          => $offerId,
        'impressions' => $imp,
        'clicks'      => $clk,
        'saves'       => (int)($o['saves'] ?? 0),
        'ctr'         => round($clk / $imp * 100, 2),
    ];
}

$a = getOfferStats($db, (int)$test['offer_id_a']);
$b = getOfferStats($db, (int)$test['offer_id_b']);

// Load offer titles
$titleA = $db->prepare('SELECT title FROM offers WHERE id = ?');
$titleA->execute([$test['offer_id_a']]);
$a['title'] = $titleA->fetchColumn();

$titleB = $db->prepare('SELECT title FROM offers WHERE id = ?');
$titleB->execute([$test['offer_id_b']]);
$b['title'] = $titleB->fetchColumn();

// Chi-square confidence
$confidence = chiSquareConfidence($a['ctr'], $a['impressions'], $b['ctr'], $b['impressions']);
$winner     = null;
if ($confidence >= 85) {
    $winner = $a['ctr'] >= $b['ctr'] ? 'A' : 'B';
}

jsonSuccess([
    'offer_a'    => $a,
    'offer_b'    => $b,
    'winner'     => $winner,
    'confidence' => $confidence,
    'status'     => $test['status'],
    'started_at' => $test['started_at'],
]);

function chiSquareConfidence(float $ctrA, int $nA, float $ctrB, int $nB): float {
    if ($nA < 10 || $nB < 10) return 0.0;
    $pA = $ctrA / 100;
    $pB = $ctrB / 100;
    $pPool = (($pA * $nA) + ($pB * $nB)) / ($nA + $nB);
    if ($pPool <= 0 || $pPool >= 1) return 0.0;
    $se   = sqrt($pPool * (1 - $pPool) * (1 / $nA + 1 / $nB));
    if ($se <= 0) return 0.0;
    $z    = abs($pA - $pB) / $se;
    // Approximate two-tailed p-value to confidence %
    $conf = min(99.9, (1 - exp(-0.717 * $z - 0.416 * $z * $z)) * 100);
    return round($conf, 1);
}
