<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$auth = Auth::require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); }

$body    = json_decode(file_get_contents('php://input'), true);
$planId  = (int)($body['plan_id']  ?? 0);
$purpose = trim($body['purpose']   ?? 'vendor_plan');

if (!$planId) { jsonError('plan_id required', 400); }

$db   = Database::getInstance();
$plan = $db->prepare('SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1');
$plan->execute([$planId]);
$plan = $plan->fetch();
if (!$plan) { jsonError('Plan not found', 404); }

// Free plans don't need payment
if ((float)$plan['price'] === 0.0) {
    jsonSuccess(['free' => true, 'plan' => $plan['slug']]);
}

$user = $db->prepare('SELECT name, email, phone FROM users WHERE id = ?');
$user->execute([$auth['user_id']]);
$user = $user->fetch();

$orderId = 'AL_' . strtoupper(bin2hex(random_bytes(8)));

// Call Cashfree API
$payload = [
    'order_id'        => $orderId,
    'order_amount'    => (float)$plan['price'],
    'order_currency'  => 'INR',
    'customer_details'=> [
        'customer_id'    => 'USR_' . $auth['user_id'],
        'customer_name'  => $user['name'],
        'customer_email' => $user['email'],
        'customer_phone' => $user['phone'] ?: '9999999999',
    ],
    'order_meta' => [
        'return_url'   => FRONTEND_URL . '/vendor/select-plan?order_id=' . $orderId,
        'notify_url'   => APP_URL . '/api/payment/webhook.php',
    ],
];

$ch = curl_init(CASHFREE_BASE_URL . '/orders');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-version: 2025-01-01',
        'x-client-id: '     . CASHFREE_APP_ID,
        'x-client-secret: ' . CASHFREE_SECRET_KEY,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
]);
$cfResponse = json_decode(curl_exec($ch), true);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || empty($cfResponse['payment_session_id'])) {
    jsonError('Payment gateway error: ' . ($cfResponse['message'] ?? 'Unknown'), 502);
}

// Store payment record
$db->prepare(
    'INSERT INTO payments (user_id, order_id, payment_session_id, amount, purpose, reference_id, reference_type)
     VALUES (?,?,?,?,?,?,?)'
)->execute([
    $auth['user_id'],
    $orderId,
    $cfResponse['payment_session_id'],
    $plan['price'],
    $purpose,
    $plan['id'],
    'vendor_plan',
]);

jsonSuccess([
    'order_id'          => $orderId,
    'payment_session_id'=> $cfResponse['payment_session_id'],
    'amount'            => (float)$plan['price'],
    'plan'              => $plan['slug'],
]);
