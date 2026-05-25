<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$admin = Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); }

$body     = json_decode(file_get_contents('php://input'), true);
$ticketId = (int)($body['ticket_id']   ?? 0);
$reply    = trim($body['reply']        ?? '');
$status   = trim($body['status']       ?? 'in_progress');

if (!$ticketId || !$reply) { jsonError('ticket_id and reply required', 400); }

$validStatus = ['open','in_progress','resolved','closed'];
if (!in_array($status, $validStatus)) $status = 'in_progress';

$db     = Database::getInstance();
$ticket = $db->prepare('SELECT * FROM support_tickets WHERE id = ?');
$ticket->execute([$ticketId]);
$ticket = $ticket->fetch();
if (!$ticket) { jsonError('Ticket not found', 404); }

$db->prepare(
    'UPDATE support_tickets SET admin_reply = ?, replied_by = ?, replied_at = NOW(), status = ? WHERE id = ?'
)->execute([$reply, $admin['user_id'], $status, $ticketId]);

$db->prepare(
    'INSERT INTO notifications (user_id, title, body, type, is_read) VALUES (?,?,?,?,0)'
)->execute([
    $ticket['user_id'],
    "Support Ticket #{$ticketId} Updated",
    "Your ticket \"{$ticket['subject']}\" has been " . ($status === 'resolved' ? 'resolved.' : 'updated.'),
    'offer_match',
]);

jsonSuccess([], 'Reply sent successfully.');
