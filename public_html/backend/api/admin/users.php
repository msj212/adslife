<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
Auth::requireRole('admin');

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = trim($_GET['search'] ?? '');
    $role   = $_GET['role']   ?? '';
    $status = $_GET['status'] ?? '';
    $limit  = min((int)($_GET['limit'] ?? 30), 100);
    $offset = (int)($_GET['offset'] ?? 0);

    $where = ['1=1'];
    $params = [];

    if ($search) {
        $where[]  = '(u.name LIKE ? OR u.email LIKE ? OR u.city LIKE ?)';
        $like     = "%$search%";
        $params   = array_merge($params, [$like, $like, $like]);
    }
    if ($role && in_array($role, ['user','vendor','admin'])) {
        $where[]  = 'u.role = ?';
        $params[] = $role;
    }
    if ($status === 'active')   { $where[] = 'u.is_active = 1'; }
    if ($status === 'banned')   { $where[] = 'u.is_active = 0'; }

    $whereSQL = implode(' AND ', $where);

    $rows = $db->prepare(
        "SELECT u.id, u.name, u.email, u.role, u.city, u.coins,
                u.streak_days, u.is_active, u.created_at, u.last_login, u.login_count,
                (SELECT COUNT(*) FROM user_interactions WHERE user_id = u.id) AS interactions,
                (SELECT COUNT(*) FROM vendor_followers WHERE user_id = u.id) AS follows
         FROM users u
         WHERE $whereSQL
         ORDER BY u.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $params[] = $limit;
    $params[] = $offset;
    $rows->execute($params);

    $count = $db->prepare("SELECT COUNT(*) FROM users u WHERE $whereSQL");
    $count->execute(array_slice($params, 0, -2));

    jsonSuccess([
        'total' => (int)$count->fetchColumn(),
        'users' => $rows->fetchAll(),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $userId = (int)($body['user_id'] ?? 0);
    $action = $body['action'] ?? '';

    if (!$userId) { jsonError('user_id required', 400); }

    // Prevent admin from modifying themselves
    $self = Auth::require();
    if ((int)$self['user_id'] === $userId && $action !== 'update_role') {
        jsonError('Cannot modify your own account status', 400);
    }

    switch ($action) {
        case 'ban':
            $db->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([$userId]);
            jsonSuccess(null, 'User banned');

        case 'unban':
            $db->prepare('UPDATE users SET is_active = 1 WHERE id = ?')->execute([$userId]);
            jsonSuccess(null, 'User unbanned');

        case 'update_role':
            $newRole = $body['role'] ?? '';
            if (!in_array($newRole, ['user','vendor','admin'])) { jsonError('Invalid role', 400); }
            $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $userId]);
            jsonSuccess(null, "Role updated to $newRole");

        case 'delete':
            // Soft-delete: mark inactive; do not erase history
            $db->prepare('UPDATE users SET is_active = 0, email = CONCAT("deleted_", id, "_", email) WHERE id = ?')->execute([$userId]);
            jsonSuccess(null, 'User deleted');

        default:
            jsonError('Invalid action', 400);
    }
}

jsonError('Method not allowed', 405);
