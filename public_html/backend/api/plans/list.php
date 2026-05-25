<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();

$db   = Database::getInstance();
$rows = $db->query('SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price ASC')->fetchAll();

jsonSuccess(array_map(fn($r) => [
    'id'           => (int)$r['id'],
    'name'         => $r['name'],
    'slug'         => $r['slug'],
    'price'        => (float)$r['price'],
    'duration_days'=> (int)$r['duration_days'],
    'max_offers'   => (int)$r['max_offers'],
    'features'     => json_decode($r['features'] ?? '[]', true),
], $rows));
