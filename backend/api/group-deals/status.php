<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();

$dealId = (int)($_GET['deal_id'] ?? 0);
if (!$dealId) jsonError('deal_id required');

$db   = Database::getInstance();
$stmt = $db->prepare(
    'SELECT gd.*, o.title, o.image_url, o.discount_percent FROM group_deals gd
     JOIN offers o ON gd.offer_id = o.id
     WHERE gd.id = ?'
);
$stmt->execute([$dealId]);
$deal = $stmt->fetch();
if (!$deal) jsonError('Deal not found', 404);

$pStmt = $db->prepare(
    'SELECT u.id, u.name, u.avatar_url FROM group_deal_participants gdp
     JOIN users u ON gdp.user_id = u.id WHERE gdp.group_deal_id = ?'
);
$pStmt->execute([$dealId]);
$deal['participants'] = $pStmt->fetchAll();
$deal['remaining']    = max(0, (int)$deal['min_participants'] - (int)$deal['current_participants']);

jsonSuccess($deal);
