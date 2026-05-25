<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$auth    = Auth::require();
$db      = Database::getInstance();
$isAdmin = $auth['role'] === 'admin';

if ($isAdmin) {
    $sql    = 'SELECT b.*, v.business_name FROM banner_ad_requests b JOIN vendors v ON v.id = b.vendor_id ORDER BY b.created_at DESC';
    $params = [];
} else {
    $vendor = $db->prepare('SELECT id FROM vendors WHERE user_id = ?');
    $vendor->execute([$auth['user_id']]);
    $vendor = $vendor->fetch();
    if (!$vendor) { jsonSuccess([]); }
    $sql    = 'SELECT * FROM banner_ad_requests WHERE vendor_id = ? ORDER BY created_at DESC';
    $params = [$vendor['id']];
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
jsonSuccess($stmt->fetchAll());
