<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
Auth::requireRole('admin');

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = $_GET['status'] ?? '';
    $limit  = min((int)($_GET['limit'] ?? 30), 100);
    $offset = (int)($_GET['offset'] ?? 0);

    $where  = ['1=1'];
    $params = [];
    if ($status) { $where[] = 'sr.status = ?'; $params[] = $status; }

    $whereSQL = implode(' AND ', $where);

    $rows = $db->prepare(
        "SELECT sr.id, sr.title, sr.tagline, sr.video_url, sr.status,
                sr.created_at, sr.reviewed_at, sr.review_note,
                v.business_name, v.city, v.subscription_plan,
                u.email AS vendor_email,
                o.title AS offer_title, o.discount_percent
         FROM spotlight_requests sr
         JOIN vendors v ON sr.vendor_id = v.id
         JOIN users u ON v.user_id = u.id
         LEFT JOIN offers o ON sr.offer_id = o.id
         WHERE $whereSQL
         ORDER BY sr.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $params[] = $limit;
    $params[] = $offset;
    $rows->execute($params);

    $count = $db->prepare(
        "SELECT COUNT(*) FROM spotlight_requests sr
         JOIN vendors v ON sr.vendor_id = v.id
         JOIN users u ON v.user_id = u.id
         WHERE $whereSQL"
    );
    $count->execute(array_slice($params, 0, -2));

    jsonSuccess(['total' => (int)$count->fetchColumn(), 'requests' => $rows->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $requestId = (int)($body['request_id'] ?? 0);
    $action    = $body['action'] ?? '';
    $note      = trim($body['note'] ?? '');

    if (!$requestId) { jsonError('request_id required', 400); }

    $validActions = ['approve', 'reject'];
    if (!in_array($action, $validActions)) { jsonError('action must be approve or reject', 400); }

    $newStatus = $action === 'approve' ? 'approved' : 'rejected';
    $db->prepare(
        'UPDATE spotlight_requests SET status = ?, review_note = ?, reviewed_at = NOW() WHERE id = ?'
    )->execute([$newStatus, $note, $requestId]);

    // Notify vendor
    $sr = $db->prepare(
        'SELECT v.user_id, sr.title FROM spotlight_requests sr JOIN vendors v ON sr.vendor_id = v.id WHERE sr.id = ?'
    );
    $sr->execute([$requestId]);
    $req = $sr->fetch();
    if ($req) {
        $msg = $action === 'approve'
            ? "Your spotlight request \"{$req['title']}\" has been approved!"
            : "Your spotlight request \"{$req['title']}\" was not approved." . ($note ? " Note: $note" : '');
        $db->prepare(
            'INSERT INTO notifications (user_id, title, body, type, is_read) VALUES (?,?,?,?,0)'
        )->execute([$req['user_id'], 'Spotlight ' . ucfirst($newStatus), $msg, 'offer_match']);
    }

    jsonSuccess(null, "Spotlight request $newStatus");
}

jsonError('Method not allowed', 405);
