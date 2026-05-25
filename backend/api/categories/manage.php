<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$auth = Auth::require();
if ($auth['role'] !== 'admin') { jsonError('Forbidden', 403); }

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

// CREATE
if ($method === 'POST') {
    $name  = trim($input['name'] ?? '');
    $icon  = trim($input['icon'] ?? '🏷️');
    $order = (int)($input['sort_order'] ?? 0);
    if (!$name) { jsonError('Name is required'); }

    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
    $slug = trim($slug, '_');

    $stmt = $db->prepare(
        'INSERT INTO categories (name, slug, icon, sort_order) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$name, $slug, $icon, $order]);
    $id = (int)$db->lastInsertId();
    $row = $db->prepare('SELECT * FROM categories WHERE id=?');
    $row->execute([$id]);
    jsonSuccess(['category' => $row->fetch()], 'Created', 201);
}

// UPDATE
if ($method === 'PUT') {
    $id     = (int)($input['id'] ?? 0);
    $name   = trim($input['name']   ?? '');
    $icon   = trim($input['icon']   ?? '🏷️');
    $order  = (int)($input['sort_order'] ?? 0);
    $active = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;
    if (!$id || !$name) { jsonError('id and name are required'); }

    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
    $slug = trim($slug, '_');

    $db->prepare(
        'UPDATE categories SET name=?, slug=?, icon=?, sort_order=?, is_active=? WHERE id=?'
    )->execute([$name, $slug, $icon, $order, $active, $id]);

    $row = $db->prepare('SELECT * FROM categories WHERE id=?');
    $row->execute([$id]);
    jsonSuccess(['category' => $row->fetch()]);
}

// DELETE
if ($method === 'DELETE') {
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if (!$id) { jsonError('id is required'); }
    $db->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
    jsonSuccess(null, 'Deleted');
}

jsonError('Method not allowed', 405);
