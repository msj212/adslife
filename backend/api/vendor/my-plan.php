<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$auth = Auth::require();

$db = Database::getInstance();

$vendor = $db->prepare(
    'SELECT v.*, sp.name AS plan_name, sp.price AS plan_price,
            sp.max_offers, sp.duration_days, sp.features
     FROM vendors v
     LEFT JOIN subscription_plans sp ON sp.slug = v.subscription_plan
     WHERE v.user_id = ?'
);
$vendor->execute([$auth['user_id']]);
$vendor = $vendor->fetch();

if (!$vendor) { jsonError('Vendor not found', 404); }

jsonSuccess([
    'vendor_id'      => (int)$vendor['id'],
    'business_name'  => $vendor['business_name'],
    'status'         => $vendor['status'],
    'plan'           => $vendor['subscription_plan'],
    'plan_name'      => $vendor['plan_name'] ?? $vendor['subscription_plan'],
    'plan_price'     => $vendor['plan_price'] ? (float)$vendor['plan_price'] : 0,
    'max_offers'     => (int)($vendor['max_offers'] ?? 5),
    'features'       => json_decode($vendor['features'] ?? '[]', true),
]);
