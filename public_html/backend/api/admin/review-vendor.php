<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$admin = Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); }

$body      = json_decode(file_get_contents('php://input'), true);
$appId     = (int)($body['application_id'] ?? 0);
$action    = trim($body['action']          ?? ''); // 'approve' or 'reject'
$adminNote = trim($body['admin_note']      ?? '');

if (!$appId || !in_array($action, ['approve', 'reject'])) {
    jsonError('application_id and action (approve/reject) required', 400);
}

$db  = Database::getInstance();
$app = $db->prepare('SELECT * FROM vendor_applications WHERE id = ?');
$app->execute([$appId]);
$app = $app->fetch();
if (!$app) { jsonError('Application not found', 404); }
if ($app['status'] !== 'pending_review') { jsonError('Application already reviewed', 409); }

require_once __DIR__ . '/../../services/PushService.php';

$db->beginTransaction();
try {
    if ($action === 'approve') {
        // Create vendor record
        $stmt = $db->prepare(
            'INSERT INTO vendors
               (user_id, business_name, category, description, address, city, lat, lng,
                phone, website, gst_number, logo_url, status, subscription_plan)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $planSlug = 'free';
        if ($app['plan_id']) {
            $pRow = $db->prepare('SELECT slug FROM subscription_plans WHERE id = ?');
            $pRow->execute([$app['plan_id']]);
            $pRow = $pRow->fetch();
            if ($pRow) $planSlug = $pRow['slug'];
        }
        $stmt->execute([
            $app['user_id'], $app['business_name'], $app['category'], $app['description'],
            $app['address'], $app['city'], $app['lat'], $app['lng'],
            $app['phone'], $app['website'], $app['gst_number'], $app['logo_url'],
            'approved', $planSlug,
        ]);
        $vendorId = $db->lastInsertId();

        $db->prepare('UPDATE users SET role = "vendor" WHERE id = ?')->execute([$app['user_id']]);
        $db->prepare(
            'UPDATE vendor_applications SET status = "approved", admin_note = ?, reviewed_by = ?, reviewed_at = NOW(), vendor_id = ? WHERE id = ?'
        )->execute([$adminNote, $admin['user_id'], $vendorId, $appId]);

        $db->prepare(
            'INSERT INTO notifications (user_id, title, body, type, is_read) VALUES (?,?,?,?,0)'
        )->execute([
            $app['user_id'],
            '🎉 Vendor Application Approved!',
            "Congratulations! Your shop \"{$app['business_name']}\" is now live on AdsLife.",
            'vendor_approved',
        ]);

        $token = Auth::generateToken($app['user_id'], 'vendor');
        $db->commit();

        PushService::send(
            (int)$app['user_id'],
            '🎉 Vendor Application Approved!',
            "Congratulations! Your shop \"{$app['business_name']}\" is now live on AdsLife.",
            ['type' => 'vendor_approved']
        );

        jsonSuccess(['vendor_id' => $vendorId, 'new_token' => $token], 'Vendor approved successfully.');
    } else {
        $db->prepare(
            'UPDATE vendor_applications SET status = "rejected", admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?'
        )->execute([$adminNote, $admin['user_id'], $appId]);

        $db->prepare(
            'INSERT INTO notifications (user_id, title, body, type, is_read) VALUES (?,?,?,?,0)'
        )->execute([
            $app['user_id'],
            'Vendor Application Update',
            "Your application for \"{$app['business_name']}\" was not approved." .
            ($adminNote ? " Reason: $adminNote" : ''),
            'vendor_approved',
        ]);

        $db->commit();

        PushService::send(
            (int)$app['user_id'],
            'Vendor Application Update',
            "Your application for \"{$app['business_name']}\" was not approved." .
            ($adminNote ? " Reason: $adminNote" : ''),
            ['type' => 'vendor_approved']
        );

        jsonSuccess([], 'Application rejected.');
    }
} catch (\Throwable $e) {
    $db->rollBack();
    jsonError('Review failed: ' . $e->getMessage(), 500);
}
