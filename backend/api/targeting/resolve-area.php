<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';

applyCORS();

$area = trim($_GET['area'] ?? '');
if (!$area) jsonError('area parameter required');

$db = Database::getInstance();

// Check cache
$cached = $db->prepare('SELECT lat, lng, display_name FROM geocode_cache WHERE query_text = ? AND expires_at > NOW()');
$cached->execute([$area]);
$row = $cached->fetch();
if ($row) {
    jsonSuccess($row);
}

// Call Nominatim
$url     = NOMINATIM_API . '/search?' . http_build_query(['q' => $area, 'format' => 'json', 'limit' => 1]);
$context = stream_context_create(['http' => [
    'timeout' => 5,
    'header'  => "User-Agent: AdsLife/1.0 (contact@adslife.in)\r\n",
]]);
$response = @file_get_contents($url, false, $context);
$data     = $response ? json_decode($response, true) : null;

if (!$data || empty($data[0])) jsonError('Area not found', 404);

$result = [
    'lat'          => (float)$data[0]['lat'],
    'lng'          => (float)$data[0]['lon'],
    'display_name' => $data[0]['display_name'],
];

// Cache for 30 days
$db->prepare(
    'INSERT INTO geocode_cache (query_text, lat, lng, display_name, expires_at) VALUES (?,?,?,?,DATE_ADD(NOW(), INTERVAL 30 DAY))
     ON DUPLICATE KEY UPDATE lat=VALUES(lat), lng=VALUES(lng), display_name=VALUES(display_name), expires_at=VALUES(expires_at)'
)->execute([$area, $result['lat'], $result['lng'], $result['display_name']]);

jsonSuccess($result);
