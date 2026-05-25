<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
Auth::requireRole('admin');

$status = $_GET['status'] ?? '';
$type   = $_GET['type'] ?? '';
$db     = Database::getInstance();

$sql    = 'SELECT * FROM fraud_flags WHERE 1=1';
$params = [];

if ($status) { $sql .= ' AND status = ?'; $params[] = $status; }
if ($type)   { $sql .= ' AND entity_type = ?'; $params[] = $type; }

$sql .= ' ORDER BY confidence_score DESC, flagged_at DESC LIMIT 200';
$stmt = $db->prepare($sql);
$stmt->execute($params);

jsonSuccess($stmt->fetchAll());
