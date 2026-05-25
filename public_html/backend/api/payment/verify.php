<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$auth    = Auth::require();
$orderId = trim($_GET['order_id'] ?? '');
if (!$orderId) { jsonError('order_id required', 400); }

$db  = Database::getInstance();
$pay = $db->prepare('SELECT * FROM payments WHERE order_id = ? AND user_id = ?');
$pay->execute([$orderId, $auth['user_id']]);
$pay = $pay->fetch();
if (!$pay) { jsonError('Payment not found', 404); }

if ($pay['status'] === 'paid') {
    jsonSuccess(['status' => 'paid', 'order_id' => $orderId]);
}

// Verify with Cashfree
$ch = curl_init(CASHFREE_BASE_URL . '/orders/' . $orderId . '/payments');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'x-api-version: 2025-01-01',
        'x-client-id: '     . CASHFREE_APP_ID,
        'x-client-secret: ' . CASHFREE_SECRET_KEY,
    ],
]);
$cfResponse = json_decode(curl_exec($ch), true);
curl_close($ch);

$paid = false;
$cfPaymentId = null;
if (is_array($cfResponse)) {
    foreach ($cfResponse as $p) {
        if (($p['payment_status'] ?? '') === 'SUCCESS') {
            $paid        = true;
            $cfPaymentId = $p['cf_payment_id'] ?? null;
            break;
        }
    }
}

if ($paid) {
    $db->prepare(
        'UPDATE payments SET status = "paid", cashfree_payment_id = ?, paid_at = NOW() WHERE order_id = ?'
    )->execute([$cfPaymentId, $orderId]);

    // Update vendor subscription if payment is for a plan
    if ($pay['reference_type'] === 'vendor_plan' && $pay['reference_id']) {
        $planRow = $db->prepare('SELECT slug, duration_days FROM subscription_plans WHERE id = ?');
        $planRow->execute([$pay['reference_id']]);
        $planRow = $planRow->fetch();
        if ($planRow) {
            $db->prepare(
                'UPDATE vendors SET subscription_plan = ?, plan_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY)
                 WHERE user_id = ?'
            )->execute([$planRow['slug'], $planRow['duration_days'], $pay['user_id']]);
        }
    }

    jsonSuccess(['status' => 'paid', 'order_id' => $orderId]);
} else {
    jsonSuccess(['status' => $cfResponse[0]['payment_status'] ?? 'pending', 'order_id' => $orderId]);
}
