<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$auth = Auth::requireRole('vendor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); }

$body      = json_decode(file_get_contents('php://input'), true);
$title     = trim($body['title']       ?? '');
$desc      = trim($body['description'] ?? '');
$imageUrl  = trim($body['image_url']   ?? '');
$targetUrl = trim($body['target_url']  ?? '');
$budget    = (float)($body['budget']   ?? 0);
$startDate = trim($body['start_date']  ?? '');
$endDate   = trim($body['end_date']    ?? '');
$placement = trim($body['placement']   ?? 'feed_top');

if (!$title) { jsonError('Title required', 400); }

$db     = Database::getInstance();
$vendor = $db->prepare('SELECT id FROM vendors WHERE user_id = ?');
$vendor->execute([$auth['user_id']]);
$vendor = $vendor->fetch();
if (!$vendor) { jsonError('Vendor account not found', 404); }

$db->prepare(
    'INSERT INTO banner_ad_requests (vendor_id, title, description, image_url, target_url, budget, start_date, end_date, placement)
     VALUES (?,?,?,?,?,?,?,?,?)'
)->execute([
    $vendor['id'], $title, $desc, $imageUrl, $targetUrl,
    $budget,
    $startDate ?: null,
    $endDate   ?: null,
    $placement,
]);
$reqId = $db->lastInsertId();

$admins = $db->query('SELECT id FROM users WHERE role = "admin"')->fetchAll();
foreach ($admins as $admin) {
    $db->prepare(
        'INSERT INTO notifications (user_id, title, body, type, is_read) VALUES (?,?,?,?,0)'
    )->execute([$admin['id'], 'New Banner Ad Request', "\"$title\" submitted for review.", 'offer_match']);
}

jsonSuccess(['request_id' => $reqId], 'Banner ad request submitted.');
