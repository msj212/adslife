<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../services/FraudDetector.php';

applyCORS();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

Auth::requireRole('admin', 'vendor');
$body    = json_decode(file_get_contents('php://input'), true);
$offerId = (int)($body['offer_id'] ?? 0);
if (!$offerId) jsonError('offer_id required');

jsonSuccess(FraudDetector::checkOffer($offerId));
