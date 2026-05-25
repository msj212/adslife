<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/Auth.php';

applyCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$body  = json_decode(file_get_contents('php://input'), true);
$name  = trim($body['name'] ?? '');
$email = trim($body['email'] ?? '');
$phone = trim($body['phone'] ?? '');
$pass  = $body['password'] ?? '';
$city  = trim($body['city'] ?? '');
$role  = in_array($body['role'] ?? '', ['user','vendor']) ? $body['role'] : 'user';

if (!$name || !$email || !$pass) jsonError('Name, email and password are required');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError('Invalid email address');
if (strlen($pass) < 6) jsonError('Password must be at least 6 characters');

$db = Database::getInstance();

$check = $db->prepare('SELECT id FROM users WHERE email = ?');
$check->execute([$email]);
if ($check->fetch()) jsonError('Email already registered', 409);

$hash = password_hash($pass, PASSWORD_BCRYPT);
$stmt = $db->prepare('INSERT INTO users (name, email, phone, password_hash, city, role) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->execute([$name, $email, $phone, $hash, $city, $role]);
$userId = (int)$db->lastInsertId();

// Initialize user preferences and streak
$db->prepare('INSERT IGNORE INTO user_preferences (user_id, preferred_categories, preferred_vendors) VALUES (?, ?, ?)')->execute([$userId, '[]', '[]']);
$db->prepare('INSERT IGNORE INTO user_streaks (user_id) VALUES (?)')->execute([$userId]);

require_once __DIR__ . '/../../services/CoinsService.php';
CoinsService::award($userId, 'daily_login');

$token = Auth::generateToken($userId, $role);

jsonSuccess([
    'user'  => ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => $role, 'coins' => 5],
    'token' => $token
], 'Registration successful', 201);
