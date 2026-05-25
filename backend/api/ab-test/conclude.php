<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$user = Auth::requireRole('vendor', 'admin');
$body = json_decode(file_get_contents('php://input'), true);

$testId        = (int)($body['test_id'] ?? 0);
$winnerOfferId = (int)($body['winner_offer_id'] ?? 0);

$db = Database::getInstance();
$db->prepare(
    'UPDATE ab_tests SET status="completed", winner_offer_id=?, ended_at=NOW() WHERE id=?'
)->execute([$winnerOfferId, $testId]);

// Boost winner's featured status
$db->prepare('UPDATE offers SET is_featured = 1 WHERE id = ?')->execute([$winnerOfferId]);

jsonSuccess(['concluded' => true, 'winner_offer_id' => $winnerOfferId]);
