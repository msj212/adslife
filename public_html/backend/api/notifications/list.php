<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$auth = Auth::require();

$limit = min((int)($_GET['limit'] ?? 30), 100);
$db    = Database::getInstance();

$stmt = $db->prepare(
    'SELECT id, user_id, title, body, type, offer_id, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT ?'
);
$stmt->execute([$auth['user_id'], $limit]);
$rows = array_map(fn($r) => [
    'id'         => (int)$r['id'],
    'user_id'    => (int)$r['user_id'],
    'title'      => $r['title'],
    'body'       => $r['body'],
    'type'       => $r['type'],
    'offer_id'   => $r['offer_id'] !== null ? (int)$r['offer_id'] : null,
    'is_read'    => (int)$r['is_read'],
    'created_at' => $r['created_at'],
], $stmt->fetchAll());

$unread = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$unread->execute([$auth['user_id']]);
$unreadCount = (int)$unread->fetchColumn();

jsonSuccess(['notifications' => $rows, 'unread_count' => $unreadCount]);
