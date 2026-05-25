<?php
// ONE-TIME SCRIPT — delete after use
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();

function safeDelete(PDO $db, string $sql, array $params = []): void {
    try { $db->prepare($sql)->execute($params); } catch (\Throwable $e) { /* table may not exist */ }
}

// Find all non-admin user IDs
$nonAdminIds = $db->query('SELECT id FROM users WHERE role != "admin"')
    ->fetchAll(PDO::FETCH_COLUMN);

if (empty($nonAdminIds)) {
    echo json_encode(['message' => 'No non-admin users found.']);
    exit;
}

$ph = implode(',', array_fill(0, count($nonAdminIds), '?'));

$db->beginTransaction();
try {
    // User-level dependent data
    safeDelete($db, "DELETE FROM notifications        WHERE user_id IN ($ph)", $nonAdminIds);
    safeDelete($db, "DELETE FROM user_fcm_tokens      WHERE user_id IN ($ph)", $nonAdminIds);
    safeDelete($db, "DELETE FROM user_badges          WHERE user_id IN ($ph)", $nonAdminIds);
    safeDelete($db, "DELETE FROM user_streaks         WHERE user_id IN ($ph)", $nonAdminIds);
    safeDelete($db, "DELETE FROM coin_transactions    WHERE user_id IN ($ph)", $nonAdminIds);
    safeDelete($db, "DELETE FROM vendor_follows       WHERE user_id IN ($ph)", $nonAdminIds);
    safeDelete($db, "DELETE FROM leaderboard          WHERE user_id IN ($ph)", $nonAdminIds);
    safeDelete($db, "DELETE FROM spin_logs            WHERE user_id IN ($ph)", $nonAdminIds);
    safeDelete($db, "DELETE FROM payments             WHERE user_id IN ($ph)", $nonAdminIds);
    safeDelete($db, "DELETE FROM support_tickets      WHERE user_id IN ($ph)", $nonAdminIds);
    safeDelete($db, "DELETE FROM vendor_applications  WHERE user_id IN ($ph)", $nonAdminIds);
    safeDelete($db, "DELETE FROM offer_interactions   WHERE user_id IN ($ph)", $nonAdminIds);
    safeDelete($db, "DELETE FROM saved_offers         WHERE user_id IN ($ph)", $nonAdminIds);

    // Get vendor IDs for non-admin users
    $stmt = $db->prepare("SELECT id FROM vendors WHERE user_id IN ($ph)");
    $stmt->execute($nonAdminIds);
    $vendorIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($vendorIds)) {
        $vph = implode(',', array_fill(0, count($vendorIds), '?'));
        safeDelete($db, "DELETE FROM offers             WHERE vendor_id IN ($vph)", $vendorIds);
        safeDelete($db, "DELETE FROM offer_interactions WHERE vendor_id IN ($vph)", $vendorIds);
        safeDelete($db, "DELETE FROM spotlight_requests WHERE vendor_id IN ($vph)", $vendorIds);
        safeDelete($db, "DELETE FROM banner_ad_requests WHERE vendor_id IN ($vph)", $vendorIds);
        safeDelete($db, "DELETE FROM vendor_follows     WHERE vendor_id IN ($vph)", $vendorIds);
        safeDelete($db, "DELETE FROM ab_tests           WHERE vendor_id IN ($vph)", $vendorIds);
        safeDelete($db, "DELETE FROM vendor_stats       WHERE vendor_id IN ($vph)", $vendorIds);
        safeDelete($db, "DELETE FROM vendor_stats_daily WHERE vendor_id IN ($vph)", $vendorIds);
        safeDelete($db, "DELETE FROM vendors            WHERE id        IN ($vph)", $vendorIds);
    }

    // Finally delete the non-admin users
    $db->prepare("DELETE FROM users WHERE role != 'admin'")->execute();

    $db->commit();

    echo json_encode([
        'success'         => true,
        'users_deleted'   => count($nonAdminIds),
        'vendors_deleted' => count($vendorIds),
        'message'         => 'Done. All non-admin users and vendors deleted.',
    ]);
} catch (\Throwable $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
