<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();

$db   = Database::getInstance();
$only = $_GET['active_only'] ?? '1';

$sql  = $only === '0'
    ? 'SELECT id, name, slug, icon, sort_order, is_active FROM categories ORDER BY sort_order, name'
    : 'SELECT id, name, slug, icon, sort_order, is_active FROM categories WHERE is_active=1 ORDER BY sort_order, name';

$rows = $db->query($sql)->fetchAll();
jsonSuccess(['categories' => $rows]);
