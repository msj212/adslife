<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$user = Auth::require();
$uid  = (int)$user['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') { jsonError('Method not allowed', 405); }

$db = Database::getInstance();
$stmt = $db->prepare('SELECT offer_id FROM saved_offers WHERE user_id = ?');
$stmt->execute([$uid]);
$ids = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'offer_id'));

jsonSuccess(['ids' => $ids]);
