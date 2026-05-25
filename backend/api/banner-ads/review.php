<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$admin = Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); }

$body   = json_decode(file_get_contents('php://input'), true);
$reqId  = (int)($body['request_id'] ?? 0);
$action = trim($body['action']      ?? ''); // approved/rejected/live/expired
$note   = trim($body['admin_note']  ?? '');

if (!$reqId || !$action) { jsonError('request_id and action required', 400); }

$valid = ['approved','rejected','live','expired'];
if (!in_array($action, $valid)) { jsonError('Invalid action', 400); }

$db  = Database::getInstance();
$req = $db->prepare('SELECT b.*, v.user_id FROM banner_ad_requests b JOIN vendors v ON v.id = b.vendor_id WHERE b.id = ?');
$req->execute([$reqId]);
$req = $req->fetch();
if (!$req) { jsonError('Request not found', 404); }

$db->prepare(
    'UPDATE banner_ad_requests SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?'
)->execute([$action, $note, $admin['user_id'], $reqId]);

$label = $action === 'approved' ? 'approved' : ($action === 'rejected' ? 'rejected' : $action);
$db->prepare(
    'INSERT INTO notifications (user_id, title, body, type, is_read) VALUES (?,?,?,?,0)'
)->execute([
    $req['user_id'],
    'Banner Ad Request ' . ucfirst($label),
    "Your banner ad \"{$req['title']}\" has been $label." . ($note ? " Note: $note" : ''),
    'offer_match',
]);

jsonSuccess([], "Request $action.");
