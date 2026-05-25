<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../services/CoinsService.php';

applyCORS();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$user   = Auth::require();
$body   = json_decode(file_get_contents('php://input'), true);
$amount = (int)($body['amount'] ?? 0);
$reason = $body['reason'] ?? 'Spent';
$uid    = (int)($body['user_id'] ?? $user['user_id']);

if ($uid !== $user['user_id'] && $user['role'] !== 'admin') jsonError('Forbidden', 403);
if ($amount <= 0) jsonError('Invalid amount');

try {
    $result = CoinsService::spend($uid, $amount, $reason);
    jsonSuccess($result);
} catch (RuntimeException $e) {
    jsonError($e->getMessage());
}
