<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$user = Auth::require();
$uid  = (int)($_GET['user_id'] ?? $user['user_id']);
$db   = Database::getInstance();

$lastSpin = $db->prepare(
    'SELECT us.spun_at, swp.label, swp.coins_value, swp.coupon_code, swp.color_hex
     FROM user_spins us
     JOIN spin_wheel_prizes swp ON us.prize_id = swp.id
     WHERE us.user_id = ?
     ORDER BY us.spun_at DESC LIMIT 1'
);
$lastSpin->execute([$uid]);
$spin = $lastSpin->fetch();

$canSpin    = true;
$nextSpinAt = null;

if ($spin) {
    $spunDate = date('Y-m-d', strtotime($spin['spun_at']));
    if ($spunDate === date('Y-m-d')) {
        $canSpin    = false;
        $nextSpinAt = date('Y-m-d H:i:s', strtotime($spin['spun_at']) + 86400);
    }
}

$prizes = $db->query('SELECT * FROM spin_wheel_prizes ORDER BY id')->fetchAll();

jsonSuccess([
    'can_spin'    => $canSpin,
    'next_spin_at'=> $nextSpinAt,
    'last_prize'  => $spin ?: null,
    'prizes'      => $prizes,
]);
