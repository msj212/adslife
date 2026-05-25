<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
Auth::requireRole('admin');

$status = $_GET['status'] ?? '';
$db     = Database::getInstance();

$sql = 'SELECT va.*, u.name AS user_name, u.email AS user_email,
               sp.name AS plan_name, sp.price AS plan_price,
               p.status AS payment_status, p.paid_at
        FROM vendor_applications va
        JOIN users u ON u.id = va.user_id
        LEFT JOIN subscription_plans sp ON sp.id = va.plan_id
        LEFT JOIN payments p ON p.order_id = va.payment_order_id
        WHERE 1=1';

$params = [];
if ($status) {
    $sql    .= ' AND va.status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY va.created_at DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

jsonSuccess(array_map(fn($r) => [
    'id'             => (int)$r['id'],
    'user_id'        => (int)$r['user_id'],
    'user_name'      => $r['user_name'],
    'user_email'     => $r['user_email'],
    'business_name'  => $r['business_name'],
    'category'       => $r['category'],
    'description'    => $r['description'],
    'address'        => $r['address'],
    'city'           => $r['city'],
    'lat'            => $r['lat'] ? (float)$r['lat'] : null,
    'lng'            => $r['lng'] ? (float)$r['lng'] : null,
    'phone'          => $r['phone'],
    'website'        => $r['website'],
    'gst_number'     => $r['gst_number'],
    'logo_url'       => $r['logo_url'],
    'plan_name'      => $r['plan_name'],
    'plan_price'     => $r['plan_price'] ? (float)$r['plan_price'] : 0,
    'payment_status' => $r['payment_status'],
    'paid_at'        => $r['paid_at'],
    'status'         => $r['status'],
    'admin_note'     => $r['admin_note'],
    'vendor_id'      => $r['vendor_id'] ? (int)$r['vendor_id'] : null,
    'created_at'     => $r['created_at'],
], $rows));
