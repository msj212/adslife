<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
Auth::requireRole('admin');

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// List all plans (including inactive)
if ($method === 'GET') {
    $rows = $db->query('SELECT * FROM subscription_plans ORDER BY price ASC')->fetchAll();
    jsonSuccess(array_map(fn($r) => [
        'id'            => (int)$r['id'],
        'name'          => $r['name'],
        'slug'          => $r['slug'],
        'price'         => (float)$r['price'],
        'duration_days' => (int)$r['duration_days'],
        'max_offers'    => (int)$r['max_offers'],
        'features'      => json_decode($r['features'] ?? '[]', true),
        'is_active'     => (int)$r['is_active'],
    ], $rows));
}

// Create
if ($method === 'POST') {
    $name     = trim($body['name']     ?? '');
    $slug     = trim($body['slug']     ?? '');
    $price    = (float)($body['price'] ?? 0);
    $days     = (int)($body['duration_days'] ?? 30);
    $maxOffers= (int)($body['max_offers']    ?? 5);
    $features = $body['features'] ?? [];
    $active   = isset($body['is_active']) ? (int)$body['is_active'] : 1;

    if (!$name || !$slug) { jsonError('Name and slug required', 400); }
    if (!preg_match('/^[a-z0-9_-]+$/', $slug)) { jsonError('Slug must be lowercase letters, numbers, hyphens only', 400); }

    $dup = $db->prepare('SELECT id FROM subscription_plans WHERE slug = ?');
    $dup->execute([$slug]);
    if ($dup->fetch()) { jsonError('Slug already exists', 409); }

    $db->prepare(
        'INSERT INTO subscription_plans (name, slug, price, duration_days, max_offers, features, is_active)
         VALUES (?,?,?,?,?,?,?)'
    )->execute([$name, $slug, $price, $days, $maxOffers, json_encode($features), $active]);

    jsonSuccess(['id' => (int)$db->lastInsertId()], 'Plan created');
}

// Update
if ($method === 'PUT') {
    $id       = (int)($body['id'] ?? 0);
    $name     = trim($body['name']     ?? '');
    $price    = (float)($body['price'] ?? 0);
    $days     = (int)($body['duration_days'] ?? 30);
    $maxOffers= (int)($body['max_offers']    ?? 5);
    $features = $body['features'] ?? [];
    $active   = (int)($body['is_active'] ?? 1);

    if (!$id || !$name) { jsonError('id and name required', 400); }

    $db->prepare(
        'UPDATE subscription_plans SET name=?, price=?, duration_days=?, max_offers=?, features=?, is_active=? WHERE id=?'
    )->execute([$name, $price, $days, $maxOffers, json_encode($features), $active, $id]);

    jsonSuccess([], 'Plan updated');
}

// Delete
if ($method === 'DELETE') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { jsonError('id required', 400); }

    // Prevent deleting a plan in use
    $inUse = $db->prepare('SELECT COUNT(*) FROM vendor_applications WHERE plan_id = ?');
    $inUse->execute([$id]);
    if ((int)$inUse->fetchColumn() > 0) {
        jsonError('Cannot delete — plan is used by vendor applications', 409);
    }

    $db->prepare('DELETE FROM subscription_plans WHERE id = ?')->execute([$id]);
    jsonSuccess([], 'Plan deleted');
}

jsonError('Method not allowed', 405);
