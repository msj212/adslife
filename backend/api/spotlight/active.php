<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();

$db   = Database::getInstance();
$stmt = $db->prepare(
    'SELECT s.id, s.vendor_id, s.video_url, s.title, s.tagline, s.approved_at,
            v.business_name, v.logo_url AS vendor_logo, v.city, v.category,
            v.lat AS vendor_lat, v.lng AS vendor_lng
     FROM spotlight_requests s
     JOIN vendors v ON s.vendor_id = v.id
     WHERE s.status = "approved"
       AND (s.expires_at IS NULL OR s.expires_at > NOW())
     ORDER BY s.approved_at DESC
     LIMIT 5'
);
$stmt->execute();
jsonSuccess($stmt->fetchAll());
