<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();

$db   = Database::getInstance();
$stmt = $db->query('SELECT * FROM badges ORDER BY id');
jsonSuccess($stmt->fetchAll());
