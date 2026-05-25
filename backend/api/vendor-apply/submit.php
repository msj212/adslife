<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$auth = Auth::require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); }

$body        = json_decode(file_get_contents('php://input'), true);
$bizName     = trim($body['business_name'] ?? '');
$category    = trim($body['category']      ?? '');
$description = trim($body['description']   ?? '');
$address     = trim($body['address']       ?? '');
$city        = trim($body['city']          ?? '');
$phone       = trim($body['phone']         ?? '');
$website     = trim($body['website']       ?? '');
$gstNumber   = trim($body['gst_number']    ?? '');
$lat         = isset($body['lat'])     ? (float)$body['lat']     : null;
$lng         = isset($body['lng'])     ? (float)$body['lng']     : null;
$logoUrl     = trim($body['logo_url']      ?? '');
$planId      = (int)($body['plan_id']      ?? 0);
$orderId     = trim($body['order_id']      ?? '');

if (!$bizName || !$category) { jsonError('Business name and category are required', 400); }
if (!$planId)                 { jsonError('Plan selection required', 400); }

if ($gstNumber && !preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstNumber)) {
    jsonError('Invalid GST number format', 400);
}

$db = Database::getInstance();

// Check for existing application
$existing = $db->prepare('SELECT id, status FROM vendor_applications WHERE user_id = ?');
$existing->execute([$auth['user_id']]);
$existing = $existing->fetch();
if ($existing && in_array($existing['status'], ['pending_review', 'approved'])) {
    jsonError('You already have a pending or approved vendor application', 409);
}

// Verify payment for paid plans
$plan = $db->prepare('SELECT * FROM subscription_plans WHERE id = ?');
$plan->execute([$planId]);
$plan = $plan->fetch();
if (!$plan) { jsonError('Invalid plan', 400); }

if ((float)$plan['price'] > 0) {
    if (!$orderId) { jsonError('Payment order ID required for paid plans', 400); }
    $pay = $db->prepare('SELECT status FROM payments WHERE order_id = ? AND user_id = ?');
    $pay->execute([$orderId, $auth['user_id']]);
    $pay = $pay->fetch();
    if (!$pay || $pay['status'] !== 'paid') {
        jsonError('Payment not verified. Please complete payment first.', 402);
    }
}

$db->beginTransaction();
try {
    $stmt = $db->prepare(
        'INSERT INTO vendor_applications
           (user_id, business_name, category, description, address, city, lat, lng,
            phone, website, gst_number, logo_url, plan_id, payment_order_id, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $auth['user_id'], $bizName, $category, $description,
        $address, $city ?: null, $lat, $lng,
        $phone, $website, $gstNumber ?: null, $logoUrl ?: null,
        $planId, $orderId ?: null,
        'pending_review',
    ]);
    $appId = $db->lastInsertId();

    // Notify admin (all admin users) — DB + push
    $admins = $db->query('SELECT id FROM users WHERE role = "admin"')->fetchAll();
    $adminIds = [];
    foreach ($admins as $adm) {
        $db->prepare(
            'INSERT INTO notifications (user_id, title, body, type, is_read) VALUES (?,?,?,?,0)'
        )->execute([
            $adm['id'],
            'New Vendor Application',
            "\"$bizName\" has applied for vendor status. Review required.",
            'vendor_approved',
        ]);
        $adminIds[] = (int)$adm['id'];
    }

    // Notify user — DB
    $db->prepare(
        'INSERT INTO notifications (user_id, title, body, type, is_read) VALUES (?,?,?,?,0)'
    )->execute([
        $auth['user_id'],
        'Application Submitted!',
        "Your vendor application for \"$bizName\" is under review. We'll notify you once approved.",
        'vendor_approved',
    ]);

    $db->commit();

    // Push notifications (after commit so DB is consistent)
    require_once __DIR__ . '/../../services/PushService.php';
    if (!empty($adminIds)) {
        PushService::send(
            $adminIds,
            'New Vendor Application 🏪',
            "\"$bizName\" has applied for vendor status. Review required.",
            ['type' => 'vendor_approved']
        );
    }

    jsonSuccess(['application_id' => $appId], 'Application submitted! Pending admin review.');
} catch (\Throwable $e) {
    $db->rollBack();
    jsonError('Failed to submit application: ' . $e->getMessage(), 500);
}
