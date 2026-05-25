<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

Auth::requireRole('vendor', 'admin');
$body = json_decode(file_get_contents('php://input'), true);

$vendorId   = (int)($body['vendor_id'] ?? 0);
$offerId    = (int)($body['offer_id'] ?? 0);
$targetType = in_array($body['target_type'] ?? '', ['pincode','radius','area']) ? $body['target_type'] : 'radius';
$pincode    = $body['pincode'] ?? null;
$radiusKm   = (int)($body['radius_km'] ?? 5);
$areaName   = $body['area_name'] ?? null;
$centerLat  = (float)($body['center_lat'] ?? 0);
$centerLng  = (float)($body['center_lng'] ?? 0);

if (!$vendorId || !$offerId) jsonError('vendor_id and offer_id required');

$db = Database::getInstance();
$db->prepare(
    'INSERT INTO offer_targeting (vendor_id, offer_id, target_type, pincode, radius_km, area_name, center_lat, center_lng)
     VALUES (?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE target_type=VALUES(target_type), pincode=VALUES(pincode),
     radius_km=VALUES(radius_km), area_name=VALUES(area_name), center_lat=VALUES(center_lat), center_lng=VALUES(center_lng)'
)->execute([$vendorId, $offerId, $targetType, $pincode, $radiusKm, $areaName, $centerLat ?: null, $centerLng ?: null]);

jsonSuccess(['saved' => true]);
