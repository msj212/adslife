<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// Verify Cashfree webhook signature
$rawBody   = file_get_contents('php://input');
$timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE']  ?? '';

if ($timestamp && $signature && CASHFREE_WEBHOOK_SECRET) {
    $expected = base64_encode(hash_hmac('sha256', $timestamp . $rawBody, CASHFREE_WEBHOOK_SECRET, true));
    if (!hash_equals($expected, $signature)) {
        http_response_code(401);
        exit('Invalid signature');
    }
}

$event = json_decode($rawBody, true);
if (!$event) { http_response_code(400); exit('Bad payload'); }

$type = $event['type'] ?? '';
if ($type !== 'PAYMENT_SUCCESS_WEBHOOK') {
    http_response_code(200);
    exit('ok');
}

$data     = $event['data'] ?? [];
$orderId  = $data['order']['order_id']    ?? '';
$cfPid    = $data['payment']['cf_payment_id'] ?? null;
$status   = $data['payment']['payment_status'] ?? '';

if ($status !== 'SUCCESS' || !$orderId) {
    http_response_code(200);
    exit('ok');
}

$db  = Database::getInstance();
$pay = $db->prepare('SELECT * FROM payments WHERE order_id = ?');
$pay->execute([$orderId]);
$pay = $pay->fetch(PDO::FETCH_ASSOC);

if (!$pay || $pay['status'] === 'paid') {
    http_response_code(200);
    exit('ok');
}

// Mark paid
$db->prepare(
    'UPDATE payments SET status = "paid", cashfree_payment_id = ?, paid_at = NOW() WHERE order_id = ?'
)->execute([$cfPid, $orderId]);

// Activate vendor plan
if ($pay['reference_type'] === 'vendor_plan' && $pay['reference_id']) {
    $planRow = $db->prepare('SELECT slug, duration_days FROM subscription_plans WHERE id = ?');
    $planRow->execute([$pay['reference_id']]);
    $planRow = $planRow->fetch(PDO::FETCH_ASSOC);
    if ($planRow) {
        $db->prepare(
            'UPDATE vendors SET subscription_plan = ?, plan_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY)
             WHERE user_id = ?'
        )->execute([$planRow['slug'], $planRow['duration_days'], $pay['user_id']]);

        // Notify vendor
        require_once __DIR__ . '/../../services/PushService.php';
        PushService::send(
            (int)$pay['user_id'],
            'Plan Activated!',
            'Your ' . ucfirst($planRow['slug']) . ' plan is now live.',
            ['type' => 'plan_activated']
        );
    }
}

http_response_code(200);
echo 'ok';
