<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$auth = Auth::requireRole('user');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); }

$body        = json_decode(file_get_contents('php://input'), true);
$bizName     = trim($body['business_name'] ?? '');
$category    = trim($body['category']      ?? '');
$description = trim($body['description']   ?? '');
$address     = trim($body['address']       ?? '');
$phone       = trim($body['phone']         ?? '');
$website     = trim($body['website']       ?? '');
$gstNumber   = trim($body['gst_number']    ?? '');
$lat         = isset($body['lat']) ? (float)$body['lat'] : null;
$lng         = isset($body['lng']) ? (float)$body['lng'] : null;
$logoUrl     = trim($body['logo_url']      ?? '');
$city        = trim($body['city']          ?? '');

if (!$bizName || !$category) { jsonError('Business name and category are required', 400); }

// GST format: 2-digit state code + 5-char PAN + 4 digits + check chars
if ($gstNumber && !preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstNumber)) {
    jsonError('Invalid GST number format (e.g. 22AAAAA0000A1Z5)', 400);
}

$db = Database::getInstance();

$existing = $db->prepare('SELECT id FROM vendors WHERE user_id = ?');
$existing->execute([$auth['user_id']]);
if ($existing->fetch()) { jsonError('You already have a vendor account', 409); }

$db->beginTransaction();
try {
    $stmt = $db->prepare(
        'INSERT INTO vendors
           (user_id, business_name, category, description, address, city,
            lat, lng, phone, website, gst_number, logo_url, status, subscription_plan)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $auth['user_id'], $bizName, $category, $description,
        $address, $city ?: null,
        $lat, $lng,
        $phone, $website, $gstNumber ?: null,
        $logoUrl ?: null,
        'pending', 'free',
    ]);
    $vendorId = $db->lastInsertId();

    $db->prepare('UPDATE users SET role = "vendor" WHERE id = ?')->execute([$auth['user_id']]);

    $db->prepare(
        'INSERT INTO notifications (user_id, title, body, type, is_read) VALUES (?,?,?,?,0)'
    )->execute([
        $auth['user_id'],
        '🎉 Vendor application submitted!',
        "Your shop \"{$bizName}\" is under review. We'll notify you once approved.",
        'vendor_approved',
    ]);

    $db->commit();

    $token = Auth::generateToken($auth['user_id'], 'vendor');
    jsonSuccess(['vendor_id' => $vendorId, 'token' => $token], 'Vendor account created! Pending admin approval.');

} catch (\Throwable $e) {
    $db->rollBack();
    jsonError('Failed to create vendor account: ' . $e->getMessage(), 500);
}
