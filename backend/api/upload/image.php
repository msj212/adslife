<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';

applyCORS();
Auth::require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$file = $_FILES['file'] ?? null;
if (!$file) {
    jsonError('No file uploaded', 400);
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    jsonError('Upload error (code ' . $file['error'] . ')', 400);
}

$allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowed, true)) {
    jsonError('Only JPEG, PNG, WebP, GIF allowed', 415);
}

$ext = match($mimeType) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    default      => 'jpg',
};

$folder   = __DIR__ . '/../../uploads/vendors/';
$filename = uniqid('v_', true) . '.' . $ext;
$dest     = $folder . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    jsonError('Failed to save file', 500);
}

require_once __DIR__ . '/../../config/config.php';
$baseUrl = defined('APP_URL') ? APP_URL : 'https://adslife.in';
jsonSuccess(['url' => "{$baseUrl}/backend/uploads/vendors/{$filename}"], 'Uploaded');
