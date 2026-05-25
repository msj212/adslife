<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$user = Auth::requireRole('vendor', 'admin');
$body = json_decode(file_get_contents('php://input'), true);

$vendorId = (int)($body['vendor_id'] ?? 0);
$offerA   = (int)($body['offer_id_a'] ?? 0);
$offerB   = (int)($body['offer_id_b'] ?? 0);
$name     = trim($body['name'] ?? 'A/B Test');

if (!$vendorId || !$offerA || !$offerB) jsonError('vendor_id, offer_id_a, offer_id_b required');
if ($offerA === $offerB) jsonError('Offers must be different');

$db   = Database::getInstance();
$stmt = $db->prepare(
    'INSERT INTO ab_tests (vendor_id, offer_id_a, offer_id_b, name) VALUES (?,?,?,?)'
);
$stmt->execute([$vendorId, $offerA, $offerB, $name]);

jsonSuccess(['test_id' => (int)$db->lastInsertId()], 'A/B test created', 201);
