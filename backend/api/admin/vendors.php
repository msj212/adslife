<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
Auth::requireRole('admin');

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status'] ?? '';
    $plan   = $_GET['plan']   ?? '';
    $limit  = min((int)($_GET['limit'] ?? 30), 100);
    $offset = (int)($_GET['offset'] ?? 0);

    $where  = ['1=1'];
    $params = [];

    if ($search) {
        $where[]  = '(v.business_name LIKE ? OR u.email LIKE ? OR v.city LIKE ?)';
        $like     = "%$search%";
        $params   = array_merge($params, [$like, $like, $like]);
    }
    if ($status) { $where[] = 'v.status = ?'; $params[] = $status; }
    if ($plan)   { $where[] = 'v.subscription_plan = ?'; $params[] = $plan; }

    $whereSQL = implode(' AND ', $where);

    $rows = $db->prepare(
        "SELECT v.id, v.business_name, v.category, v.city, v.status,
                v.subscription_plan, v.plan_expires_at, v.total_followers,
                v.created_at, u.email, u.id AS user_id, u.is_active AS user_active,
                (SELECT COUNT(*) FROM offers WHERE vendor_id = v.id) AS total_offers,
                (SELECT COUNT(*) FROM offers WHERE vendor_id = v.id AND is_active = 1) AS active_offers,
                (SELECT COALESCE(SUM(views),0) FROM offers WHERE vendor_id = v.id) AS total_views,
                (SELECT COALESCE(SUM(clicks),0) FROM offers WHERE vendor_id = v.id) AS total_clicks
         FROM vendors v
         JOIN users u ON v.user_id = u.id
         WHERE $whereSQL
         ORDER BY v.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $params[] = $limit;
    $params[] = $offset;
    $rows->execute($params);

    $count = $db->prepare(
        "SELECT COUNT(*) FROM vendors v JOIN users u ON v.user_id = u.id WHERE $whereSQL"
    );
    $count->execute(array_slice($params, 0, -2));

    jsonSuccess(['total' => (int)$count->fetchColumn(), 'vendors' => $rows->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $vendorId = (int)($body['vendor_id'] ?? 0);
    $action   = $body['action'] ?? '';

    if (!$vendorId) { jsonError('vendor_id required', 400); }

    switch ($action) {
        case 'approve':
            $db->prepare('UPDATE vendors SET status = "approved" WHERE id = ?')->execute([$vendorId]);
            jsonSuccess(null, 'Vendor approved');

        case 'suspend':
            $db->prepare('UPDATE vendors SET status = "suspended" WHERE id = ?')->execute([$vendorId]);
            jsonSuccess(null, 'Vendor suspended');

        case 'reject':
            $db->prepare('UPDATE vendors SET status = "rejected" WHERE id = ?')->execute([$vendorId]);
            jsonSuccess(null, 'Vendor rejected');

        case 'update_plan':
            $plan = $body['plan'] ?? '';
            $validPlans = ['free','starter','growth','professional'];
            if (!in_array($plan, $validPlans)) { jsonError('Invalid plan', 400); }
            $days = $db->prepare('SELECT duration_days FROM subscription_plans WHERE slug = ?');
            $days->execute([$plan]);
            $d = $days->fetch();
            $db->prepare(
                'UPDATE vendors SET subscription_plan = ?, plan_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?'
            )->execute([$plan, $d ? $d['duration_days'] : 30, $vendorId]);
            jsonSuccess(null, "Plan updated to $plan");

        default:
            jsonError('Invalid action', 400);
    }
}

jsonError('Method not allowed', 405);
