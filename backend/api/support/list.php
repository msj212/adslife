<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$auth = Auth::require();

$db  = Database::getInstance();
$isAdmin = $auth['role'] === 'admin';

if ($isAdmin) {
    $status = $_GET['status'] ?? '';
    $sql    = 'SELECT st.*, u.name AS user_name, u.email AS user_email
               FROM support_tickets st
               JOIN users u ON u.id = st.user_id';
    $params = [];
    if ($status) {
        $sql    .= ' WHERE st.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY st.created_at DESC';
} else {
    $sql    = 'SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC';
    $params = [$auth['user_id']];
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
jsonSuccess($stmt->fetchAll());
