<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$auth = Auth::require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); }

$body     = json_decode(file_get_contents('php://input'), true);
$subject  = trim($body['subject']     ?? '');
$desc     = trim($body['description'] ?? '');
$category = trim($body['category']    ?? 'other');
$priority = trim($body['priority']    ?? 'medium');

if (!$subject || !$desc) { jsonError('Subject and description required', 400); }

$validCats = ['technical','billing','account','offer','other'];
$validPri  = ['low','medium','high','urgent'];
if (!in_array($category, $validCats)) $category = 'other';
if (!in_array($priority, $validPri))  $priority  = 'medium';

$db = Database::getInstance();

// Get vendor_id if user is a vendor
$vendor = $db->prepare('SELECT id FROM vendors WHERE user_id = ?');
$vendor->execute([$auth['user_id']]);
$vendor = $vendor->fetch();

$db->prepare(
    'INSERT INTO support_tickets (user_id, vendor_id, subject, description, category, priority)
     VALUES (?,?,?,?,?,?)'
)->execute([
    $auth['user_id'],
    $vendor ? $vendor['id'] : null,
    $subject, $desc, $category, $priority,
]);
$ticketId = $db->lastInsertId();

// Notify admins
$admins = $db->query('SELECT id FROM users WHERE role = "admin"')->fetchAll();
foreach ($admins as $admin) {
    $db->prepare(
        'INSERT INTO notifications (user_id, title, body, type, is_read) VALUES (?,?,?,?,0)'
    )->execute([
        $admin['id'],
        "New Support Ticket #$ticketId",
        "[$priority] $subject",
        'offer_match',
    ]);
}

jsonSuccess(['ticket_id' => $ticketId], 'Support ticket created successfully.');
