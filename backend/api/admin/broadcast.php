<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); }
Auth::requireRole('admin');

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$title   = trim($body['title']   ?? '');
$message = trim($body['message'] ?? '');
$target  = $body['target'] ?? 'all'; // 'all' | 'user' | 'vendor' | 'admin'

if (!$title || !$message) { jsonError('title and message required', 400); }

$validTargets = ['all', 'user', 'vendor', 'admin'];
if (!in_array($target, $validTargets)) { jsonError('Invalid target', 400); }

$db = Database::getInstance();

if ($target === 'all') {
    $users = $db->query('SELECT id FROM users WHERE is_active = 1')->fetchAll();
} else {
    $stmt = $db->prepare('SELECT id FROM users WHERE role = ? AND is_active = 1');
    $stmt->execute([$target]);
    $users = $stmt->fetchAll();
}

if (empty($users)) { jsonError('No users found for this target', 404); }

$insert = $db->prepare(
    'INSERT INTO notifications (user_id, title, body, type, is_read) VALUES (?,?,?,?,0)'
);

$db->beginTransaction();
try {
    foreach ($users as $u) {
        $insert->execute([(int)$u['id'], $title, $message, 'admin_broadcast']);
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    jsonError('Failed to send broadcast', 500);
}

jsonSuccess(['sent_to' => count($users)], "Broadcast sent to " . count($users) . " users");
