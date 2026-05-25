<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
Auth::requireRole('admin');

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search   = trim($_GET['search']   ?? '');
    $category = $_GET['category']      ?? '';
    $status   = $_GET['status']        ?? '';
    $limit    = min((int)($_GET['limit'] ?? 30), 100);
    $offset   = (int)($_GET['offset']  ?? 0);

    $where  = ['1=1'];
    $params = [];

    if ($search) {
        $where[]  = '(o.title LIKE ? OR o.coupon_code LIKE ? OR v.business_name LIKE ?)';
        $like     = "%$search%";
        $params   = array_merge($params, [$like, $like, $like]);
    }
    if ($category) { $where[] = 'o.category = ?'; $params[] = $category; }
    if ($status === 'active')   { $where[] = 'o.is_active = 1'; }
    if ($status === 'inactive') { $where[] = 'o.is_active = 0'; }
    if ($status === 'expired')  { $where[] = 'o.valid_until < NOW()'; }

    $whereSQL = implode(' AND ', $where);

    $rows = $db->prepare(
        "SELECT o.id, o.title, o.category, o.discount_percent, o.original_price, o.offer_price,
                o.is_active, o.views, o.clicks, o.saves, o.current_redemptions, o.max_redemptions,
                o.valid_from, o.valid_until, o.created_at,
                v.business_name, v.id AS vendor_id, u.email AS vendor_email
         FROM offers o
         JOIN vendors v ON o.vendor_id = v.id
         JOIN users u ON v.user_id = u.id
         WHERE $whereSQL
         ORDER BY o.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $params[] = $limit;
    $params[] = $offset;
    $rows->execute($params);

    $count = $db->prepare(
        "SELECT COUNT(*) FROM offers o
         JOIN vendors v ON o.vendor_id = v.id
         JOIN users u ON v.user_id = u.id
         WHERE $whereSQL"
    );
    $count->execute(array_slice($params, 0, -2));

    jsonSuccess(['total' => (int)$count->fetchColumn(), 'offers' => $rows->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $offerId = (int)($body['offer_id'] ?? 0);
    $action  = $body['action'] ?? '';

    if (!$offerId) { jsonError('offer_id required', 400); }

    switch ($action) {
        case 'activate':
            $db->prepare('UPDATE offers SET is_active = 1 WHERE id = ?')->execute([$offerId]);
            jsonSuccess(null, 'Offer activated');

        case 'deactivate':
            $db->prepare('UPDATE offers SET is_active = 0 WHERE id = ?')->execute([$offerId]);
            jsonSuccess(null, 'Offer deactivated');

        case 'delete':
            $db->prepare('DELETE FROM offers WHERE id = ?')->execute([$offerId]);
            jsonSuccess(null, 'Offer deleted');

        case 'feature':
            $featured = (int)($body['featured'] ?? 1);
            $db->prepare('UPDATE offers SET is_featured = ? WHERE id = ?')->execute([$featured, $offerId]);
            jsonSuccess(null, $featured ? 'Offer featured' : 'Offer unfeatured');

        default:
            jsonError('Invalid action', 400);
    }
}

jsonError('Method not allowed', 405);
