<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$auth = Auth::requireRole('vendor', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$body    = json_decode(file_get_contents('php://input'), true);
$videoUrl = trim($body['video_url'] ?? '');
$title    = trim($body['title']     ?? '');
$tagline  = trim($body['tagline']   ?? '');

if (!$videoUrl || !$title) jsonError('video_url and title are required', 400);

$db = Database::getInstance();

// Get vendor_id for this user
$vs = $db->prepare('SELECT id FROM vendors WHERE user_id = ?');
$vs->execute([$auth['user_id']]);
$vendor = $vs->fetch();
if (!$vendor) jsonError('Vendor account not found', 404);

// Check for existing pending request
$existing = $db->prepare('SELECT id FROM spotlight_requests WHERE vendor_id = ? AND status = "pending"');
$existing->execute([$vendor['id']]);
if ($existing->fetch()) jsonError('You already have a pending spotlight request', 409);

$stmt = $db->prepare(
    'INSERT INTO spotlight_requests (vendor_id, video_url, title, tagline) VALUES (?,?,?,?)'
);
$stmt->execute([$vendor['id'], $videoUrl, $title, $tagline]);

jsonSuccess(['id' => $db->lastInsertId()], 'Spotlight request submitted successfully');
