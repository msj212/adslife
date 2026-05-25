<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/CoinsService.php';

applyCORS();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('Method not allowed', 405); }

$body        = json_decode(file_get_contents('php://input'), true);
$accessToken = trim($body['id_token'] ?? '');   // implicit flow sends access_token here
$userinfo    = $body['userinfo'] ?? null;        // pre-fetched userinfo from frontend

if (!$accessToken) { jsonError('Google token required', 400); }

// If frontend didn't pass userinfo, fetch it ourselves
if (!$userinfo || empty($userinfo['sub'])) {
    $ctx = stream_context_create(['http' => [
        'header' => "Authorization: Bearer $accessToken\r\n",
        'timeout' => 5,
    ]]);
    $response = @file_get_contents('https://www.googleapis.com/oauth2/v3/userinfo', false, $ctx);
    if (!$response) { jsonError('Failed to verify Google token', 502); }
    $userinfo = json_decode($response, true);
}

if (empty($userinfo['sub']) || empty($userinfo['email'])) { jsonError('Invalid Google account data', 401); }

$googleId = $userinfo['sub'];
$email    = $userinfo['email'];
$name     = $userinfo['name']    ?? explode('@', $email)[0];
$avatar   = $userinfo['picture'] ?? null;

$db = Database::getInstance();

// Find existing user by google_id or email
$stmt = $db->prepare(
    'SELECT id, name, email, role, coins, streak_days, city, lat, lng, avatar_url, google_id
     FROM users WHERE google_id = ? OR (email = ? AND is_active = 1) LIMIT 1'
);
$stmt->execute([$googleId, $email]);
$user  = $stmt->fetch();
$isNew = false;

if ($user) {
    // Link google_id if not already set
    if (empty($user['google_id'])) {
        $db->prepare('UPDATE users SET google_id = ?, avatar_url = COALESCE(NULLIF(avatar_url,""), ?) WHERE id = ?')
           ->execute([$googleId, $avatar, $user['id']]);
    }
    $db->prepare('UPDATE users SET last_login = CURDATE(), login_count = login_count + 1 WHERE id = ?')
       ->execute([$user['id']]);

    require_once __DIR__ . '/../../services/StreakService.php';
    StreakService::update($user['id']);
    CoinsService::award($user['id'], 'daily_login');
} else {
    $isNew = true;
    $db->prepare(
        'INSERT INTO users (name, email, google_id, avatar_url, role, coins, is_active, created_at)
         VALUES (?, ?, ?, ?, "user", 5, 1, NOW())'
    )->execute([$name, $email, $googleId, $avatar]);

    $newId = (int)$db->lastInsertId();
    CoinsService::award($newId, 'signup');

    $stmt2 = $db->prepare(
        'SELECT id, name, email, role, coins, streak_days, city, lat, lng, avatar_url FROM users WHERE id = ?'
    );
    $stmt2->execute([$newId]);
    $user = $stmt2->fetch();
}

$token = Auth::generateToken($user['id'], $user['role']);
unset($user['google_id']);

jsonSuccess(['user' => $user, 'token' => $token, 'is_new' => $isNew],
           $isNew ? 'Account created' : 'Login successful');
