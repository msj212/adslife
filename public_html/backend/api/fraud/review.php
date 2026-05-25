<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

Auth::requireRole('admin');
$body   = json_decode(file_get_contents('php://input'), true);
$flagId = (int)($body['flag_id'] ?? 0);
$action = in_array($body['action'] ?? '', ['dismiss', 'action', 'reviewed']) ? $body['action'] : null;

if (!$flagId || !$action) jsonError('flag_id and action required');

$db = Database::getInstance();
$db->prepare('UPDATE fraud_flags SET status = ? WHERE id = ?')->execute([$action === 'dismiss' ? 'dismissed' : 'actioned', $flagId]);

jsonSuccess(['updated' => true]);
