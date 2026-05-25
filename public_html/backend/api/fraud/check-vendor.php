<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../services/FraudDetector.php';

applyCORS();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

Auth::requireRole('admin', 'vendor');
$body     = json_decode(file_get_contents('php://input'), true);
$vendorId = (int)($body['vendor_id'] ?? 0);
if (!$vendorId) jsonError('vendor_id required');

jsonSuccess(FraudDetector::checkVendor($vendorId));
