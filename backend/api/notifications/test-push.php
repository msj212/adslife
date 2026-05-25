<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';

applyCORS();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); }

$auth = Auth::require();

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$title  = trim($input['title'] ?? 'AdsLife Test 🔔');
$msg    = trim($input['body']  ?? 'Push notification is working!');

$db = Database::getInstance();

// Support targeting a specific user_id or broadcast to all
if (!empty($input['user_id'])) {
    $targetIds = [(int)$input['user_id']];
} elseif (!empty($input['broadcast'])) {
    $rows = $db->query('SELECT DISTINCT user_id FROM user_fcm_tokens')->fetchAll(PDO::FETCH_COLUMN);
    $targetIds = array_map('intval', $rows);
} else {
    $targetIds = [$auth['user_id']];
}

// Personalise title per user when sending to a single known user
if (count($targetIds) === 1) {
    $u = $db->prepare('SELECT name FROM users WHERE id = ?');
    $u->execute([$targetIds[0]]);
    $name = $u->fetchColumn();
    if ($name && strpos($title, '{{name}}') !== false) {
        $title = str_replace('{{name}}', explode(' ', trim($name))[0], $title);
    }
}

$tokenStmt = $db->prepare(
    'SELECT token FROM user_fcm_tokens WHERE user_id IN (' .
    implode(',', array_fill(0, count($targetIds), '?')) . ')'
);
$tokenStmt->execute($targetIds);
$tokens = $tokenStmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($tokens)) {
    jsonError('No FCM tokens found. Open the app and allow notifications first.', 404);
}

$serviceAccountPath = __DIR__ . '/../../config/firebase-service-account.json';

if (file_exists($serviceAccountPath)) {
    require_once __DIR__ . '/../../services/PushService.php';
    $sent = PushService::send($targetIds, $title, $msg, ['type' => 'test']);
    jsonSuccess(['method' => 'v1', 'tokens' => count($tokens), 'sent' => $sent]);
}

$envKey = getenv('FCM_SERVER_KEY');
if (defined('FCM_SERVER_KEY')) {
    $serverKey = FCM_SERVER_KEY;
} else {
    $serverKey = $envKey ?: '';
}

// ── Legacy API (server key) ──────────────────────────────────
if (!$serverKey) {
    jsonError('No FCM server key found. Add FCM_SERVER_KEY to config or place firebase-service-account.json in backend/config/.', 500);
}

$sent  = 0;
$stale = [];

foreach ($tokens as $token) {
    $payload = json_encode([
        'to'           => $token,
        'notification' => ['title' => $title, 'body' => $msg, 'icon' => '/favicon.svg'],
        'data'         => ['type' => 'test'],
    ]);

    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Authorization: key=$serverKey\r\nContent-Type: application/json\r\n",
        'content'       => $payload,
        'timeout'       => 8,
        'ignore_errors' => true,
    ]]);
    $resp   = @file_get_contents($fcmLegacyUrl, false, $ctx);
    $result = json_decode($resp, true);

    if (($result['success'] ?? 0) > 0) {
        $sent++;
    } elseif (!empty($result['results'][0]['error'])) {
        $err = $result['results'][0]['error'];
        $results[] = $err;
        if (in_array($err, ['NotRegistered', 'InvalidRegistration'])) {
            $stale[] = $token;
        }
    }
}

// Clean stale tokens
if (!empty($stale)) {
    $pl = implode(',', array_fill(0, count($stale), '?'));
    $db->prepare("DELETE FROM user_fcm_tokens WHERE token IN ($pl)")->execute($stale);
}

jsonSuccess([
    'method'  => 'legacy',
    'tokens'  => count($tokens),
    'sent'    => $sent,
    'errors'  => $results,
]);
