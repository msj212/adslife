<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$user        = Auth::require();
$body        = json_decode(file_get_contents('php://input'), true);
$groupDealId = (int)($body['group_deal_id'] ?? 0);
$uid         = (int)($body['user_id'] ?? $user['user_id']);

if (!$groupDealId) jsonError('group_deal_id required');

$db   = Database::getInstance();
$deal = $db->prepare('SELECT * FROM group_deals WHERE id = ? AND deal_status = "collecting" AND expires_at > NOW() FOR UPDATE');
$deal->execute([$groupDealId]);
$row = $deal->fetch();
if (!$row) jsonError('Deal not available or expired', 404);

$db->beginTransaction();
try {
    $db->prepare('INSERT IGNORE INTO group_deal_participants (group_deal_id, user_id) VALUES (?,?)')->execute([$groupDealId, $uid]);

    $count = (int)$db->prepare('SELECT COUNT(*) FROM group_deal_participants WHERE group_deal_id = ?')->execute([$groupDealId]) ?
        (int)(function() use ($db, $groupDealId) {
            $s = $db->prepare('SELECT COUNT(*) FROM group_deal_participants WHERE group_deal_id = ?');
            $s->execute([$groupDealId]);
            return $s->fetchColumn();
        })() : 1;

    // Recount properly
    $cStmt = $db->prepare('SELECT COUNT(*) FROM group_deal_participants WHERE group_deal_id = ?');
    $cStmt->execute([$groupDealId]);
    $count = (int)$cStmt->fetchColumn();

    $db->prepare('UPDATE group_deals SET current_participants = ? WHERE id = ?')->execute([$count, $groupDealId]);

    $activated = false;
    if ($count >= (int)$row['min_participants']) {
        $db->prepare('UPDATE group_deals SET deal_status = "activated" WHERE id = ?')->execute([$groupDealId]);
        $activated = true;

        // Notify all participants
        $partStmt = $db->prepare('SELECT user_id FROM group_deal_participants WHERE group_deal_id = ?');
        $partStmt->execute([$groupDealId]);
        $notifyInsert = $db->prepare(
            'INSERT INTO notifications (user_id, title, body, type, offer_id, is_read) VALUES (?,?,?,?,?,0)'
        );
        foreach ($partStmt->fetchAll() as $p) {
            $notifyInsert->execute([
                $p['user_id'],
                'Group Deal Activated! 🎉',
                'The group deal you joined is now active! Grab your discount.',
                'group_deal',
                $row['offer_id'],
            ]);
        }
    }

    $db->commit();
    jsonSuccess([
        'joined'              => true,
        'current_participants'=> $count,
        'deal_activated'      => $activated,
        'remaining'           => max(0, (int)$row['min_participants'] - $count),
    ]);
} catch (Throwable $e) {
    $db->rollBack();
    jsonError('Failed to join deal');
}
