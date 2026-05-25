<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
Auth::requireRole('admin');

$status = $_GET['status'] ?? '';
$db     = Database::getInstance();

$sql = 'SELECT s.*, v.business_name, v.logo_url AS vendor_logo, v.city
        FROM spotlight_requests s
        JOIN vendors v ON s.vendor_id = v.id';
$params = [];
if ($status) { $sql .= ' WHERE s.status = ?'; $params[] = $status; }
$sql .= ' ORDER BY s.created_at DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
jsonSuccess($stmt->fetchAll());
