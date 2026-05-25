<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();

$city  = $_GET['city'] ?? '';
$db    = Database::getInstance();
$sql   = 'SELECT gd.*, o.title, o.image_url, o.discount_percent, o.offer_price, o.original_price,
                 v.business_name, v.logo_url as vendor_logo, v.city as vendor_city
          FROM group_deals gd
          JOIN offers o ON gd.offer_id = o.id
          JOIN vendors v ON gd.vendor_id = v.id
          WHERE gd.deal_status = "collecting"
            AND gd.expires_at > NOW()';
$params = [];

if ($city) {
    $sql .= ' AND v.city = ?';
    $params[] = $city;
}

$sql .= ' ORDER BY gd.expires_at ASC LIMIT 50';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$deals = $stmt->fetchAll();

foreach ($deals as &$deal) {
    $deal['progress_percent'] = $deal['min_participants'] > 0
        ? round($deal['current_participants'] / $deal['min_participants'] * 100)
        : 0;
    $deal['remaining'] = max(0, (int)$deal['min_participants'] - (int)$deal['current_participants']);

    // Load top participants' avatars
    $pStmt = $db->prepare(
        'SELECT u.id, u.name, u.avatar_url FROM group_deal_participants gdp
         JOIN users u ON gdp.user_id = u.id WHERE gdp.group_deal_id = ? LIMIT 5'
    );
    $pStmt->execute([$deal['id']]);
    $deal['participants_preview'] = $pStmt->fetchAll();
}

jsonSuccess($deals);
