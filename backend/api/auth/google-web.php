<?php
$clientId    = '369528600881-b6qkj8v1mgnukqrp0gjjhl0ecotqcrv5.apps.googleusercontent.com';
$callbackUri = 'https://adslife.stss.in/backend/api/auth/google-callback.php';

// App passes its own deep-link URI (exp:// in Expo Go, com.adslife.app:// in prod)
$appUri = $_GET['app_uri'] ?? 'com.adslife.app://auth';

// Whitelist allowed schemes to prevent open redirect
$allowed = ['com.adslife.app://', 'exp://'];
$safe    = false;
foreach ($allowed as $prefix) {
    if (str_starts_with($appUri, $prefix)) { $safe = true; break; }
}
if (!$safe) { http_response_code(400); echo 'Invalid app_uri'; exit; }

// Encode app_uri in state so callback knows where to redirect
$state = base64_encode(json_encode(['app_uri' => $appUri, 'n' => bin2hex(random_bytes(8))]));

$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => $clientId,
    'redirect_uri'  => $callbackUri,
    'response_type' => 'token',
    'scope'         => 'openid profile email',
    'prompt'        => 'select_account',
    'state'         => $state,
]);

header('Location: ' . $url);
exit;
