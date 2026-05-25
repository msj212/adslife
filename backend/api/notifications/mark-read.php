<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$auth = Auth::require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$body = json_decode(file_get_contents('php://input'), true);
$id   = isset($body['id']) ? (int)$body['id'] : null;  // null = mark all
$db   = Database::getInstance();

if ($id) {
    $db->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')
       ->execute([$id, $auth['user_id']]);
} else {
    $db->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')
       ->execute([$auth['user_id']]);
}

jsonSuccess(null, 'Marked as read');
