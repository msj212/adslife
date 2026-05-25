<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$body      = json_decode(file_get_contents('php://input'), true);
$id        = (int)($body['id']         ?? 0);
$action    = $body['action']            ?? '';  // approve | reject
$adminNote = trim($body['admin_note']   ?? '');
$durationDays = max(1, (int)($body['duration_days'] ?? 30));

if (!$id || !in_array($action, ['approve', 'reject'], true)) {
    jsonError('id and action (approve|reject) required', 400);
}

$db = Database::getInstance();

if ($action === 'approve') {
    // Enforce max 5 active spotlights
    $count = $db->query(
        'SELECT COUNT(*) FROM spotlight_requests WHERE status = "approved" AND (expires_at IS NULL OR expires_at > NOW())'
    )->fetchColumn();
    if ((int)$count >= 5) jsonError('Maximum 5 active spotlights reached. Reject one before approving another.', 409);

    $stmt = $db->prepare(
        'UPDATE spotlight_requests SET status="approved", admin_note=?, approved_at=NOW(),
         expires_at=DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id=?'
    );
    $stmt->execute([$adminNote, $durationDays, $id]);

    // Notify the vendor's owner
    $vr = $db->prepare(
        'SELECT u.id AS user_id, v.business_name FROM spotlight_requests s
         JOIN vendors v ON s.vendor_id = v.id
         JOIN users u ON v.user_id = u.id WHERE s.id = ?'
    );
    $vr->execute([$id]);
    $info = $vr->fetch();
    if ($info) {
        $db->prepare(
            'INSERT INTO notifications (user_id, title, body, type, is_read) VALUES (?,?,?,?,0)'
        )->execute([
            $info['user_id'],
            '🌟 Your Spotlight was Approved!',
            "Your spotlight for {$info['business_name']} is now live on the home feed for {$durationDays} days.",
            'spotlight_approved',
        ]);
    }
} else {
    $stmt = $db->prepare(
        'UPDATE spotlight_requests SET status="rejected", admin_note=? WHERE id=?'
    );
    $stmt->execute([$adminNote, $id]);
}

jsonSuccess(null, $action === 'approve' ? 'Spotlight approved' : 'Spotlight rejected');
