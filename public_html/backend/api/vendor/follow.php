<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$user = Auth::require();
$uid  = (int)$user['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if current user follows a vendor
    $vendorId = (int)($_GET['vendor_id'] ?? 0);
    if (!$vendorId) jsonError('vendor_id required', 400);

    $db   = Database::getInstance();
    $stmt = $db->prepare('SELECT id FROM vendor_followers WHERE vendor_id = ? AND user_id = ?');
    $stmt->execute([$vendorId, $uid]);
    $count = $db->prepare('SELECT COUNT(*) FROM vendor_followers WHERE vendor_id = ?');
    $count->execute([$vendorId]);

    jsonSuccess([
        'following'        => (bool)$stmt->fetch(),
        'followers_count'  => (int)$count->fetchColumn(),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $vendorId = (int)($body['vendor_id'] ?? 0);
    $action   = $body['action'] ?? 'toggle'; // 'follow' | 'unfollow' | 'toggle'

    if (!$vendorId) jsonError('vendor_id required', 400);

    $db = Database::getInstance();

    // Prevent vendor from following themselves
    $self = $db->prepare('SELECT id FROM vendors WHERE id = ? AND user_id = ?');
    $self->execute([$vendorId, $uid]);
    if ($self->fetch()) jsonError('Cannot follow your own shop', 400);

    $existing = $db->prepare('SELECT id FROM vendor_followers WHERE vendor_id = ? AND user_id = ?');
    $existing->execute([$vendorId, $uid]);
    $isFollowing = (bool)$existing->fetch();

    if ($action === 'follow' || ($action === 'toggle' && !$isFollowing)) {
        $db->prepare('INSERT IGNORE INTO vendor_followers (vendor_id, user_id) VALUES (?,?)')->execute([$vendorId, $uid]);
        // Update denormalized count on vendors table
        $db->prepare('UPDATE vendors SET total_followers = (SELECT COUNT(*) FROM vendor_followers WHERE vendor_id = ?) WHERE id = ?')
           ->execute([$vendorId, $vendorId]);
        $count = $db->prepare('SELECT total_followers FROM vendors WHERE id = ?');
        $count->execute([$vendorId]);
        jsonSuccess(['following' => true, 'followers_count' => (int)$count->fetchColumn()], 'Subscribed');
    } else {
        $db->prepare('DELETE FROM vendor_followers WHERE vendor_id = ? AND user_id = ?')->execute([$vendorId, $uid]);
        $db->prepare('UPDATE vendors SET total_followers = GREATEST(0, total_followers - 1) WHERE id = ?')->execute([$vendorId]);
        $count = $db->prepare('SELECT total_followers FROM vendors WHERE id = ?');
        $count->execute([$vendorId]);
        jsonSuccess(['following' => false, 'followers_count' => (int)$count->fetchColumn()], 'Unsubscribed');
    }
}

jsonError('Method not allowed', 405);
