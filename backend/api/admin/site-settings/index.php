<?php
require_once __DIR__ . '/../../../middleware/CORS.php';
require_once __DIR__ . '/../../../middleware/Auth.php';
require_once __DIR__ . '/../../../config/database.php';

applyCORS();
$user = Auth::require();
if ($user['role'] !== 'admin') jsonError('Admin access required', 403);

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query('SELECT setting_key, setting_value FROM site_settings');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];
    foreach ($rows as $r) $result[$r['setting_key']] = $r['setting_value'];
    jsonSuccess($result);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $allowed = ['site_name','site_tagline','site_logo_url','seo_title','seo_description','seo_keywords','contact_email','contact_phone'];
    $stmt = $db->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (?,?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach ($allowed as $key) {
        if (array_key_exists($key, $body)) {
            $stmt->execute([$key, $body[$key]]);
        }
    }
    jsonSuccess(null, 'Settings saved');
}

jsonError('Method not allowed', 405);
