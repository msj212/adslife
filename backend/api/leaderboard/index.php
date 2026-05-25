<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();

$city   = $_GET['city'] ?? '';
$period = in_array($_GET['period'] ?? '', ['weekly','monthly','alltime']) ? $_GET['period'] : 'monthly';
$limit  = min((int)($_GET['limit'] ?? 50), 100);

$db = Database::getInstance();

// Build query with optional city filter
$sql = 'SELECT l.*, u.name, u.avatar_url, u.city as user_city
        FROM leaderboard l
        JOIN users u ON l.user_id = u.id
        WHERE l.period = ?';
$params = [$period];

if ($city) {
    $sql .= ' AND l.city = ?';
    $params[] = $city;
}

$sql .= ' ORDER BY l.score DESC LIMIT ?';
$params[] = $limit;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Add rank positions
foreach ($rows as $i => &$row) {
    $row['rank'] = $i + 1;
}

jsonSuccess($rows);
