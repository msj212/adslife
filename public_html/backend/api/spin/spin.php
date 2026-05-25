<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/CoinsService.php';

applyCORS();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$user = Auth::require();
$body = json_decode(file_get_contents('php://input'), true);
$uid  = (int)($body['user_id'] ?? $user['user_id']);

if ($uid !== $user['user_id']) jsonError('Forbidden', 403);

$db = Database::getInstance();

// Check if already spun today
$spunToday = $db->prepare(
    'SELECT id, spun_at FROM user_spins WHERE user_id = ? AND DATE(spun_at) = CURDATE() LIMIT 1'
);
$spunToday->execute([$uid]);
$lastSpin = $spunToday->fetch();

if ($lastSpin) {
    $nextSpin = date('Y-m-d H:i:s', strtotime($lastSpin['spun_at']) + 86400);
    jsonError(json_encode(['can_spin' => false, 'next_spin_at' => $nextSpin]), 429);
}

// Weighted random prize selection
$prizes = $db->query('SELECT * FROM spin_wheel_prizes ORDER BY id')->fetchAll();
$rand   = mt_rand(1, 10000) / 10000;
$cumulative = 0.0;
$winner = $prizes[array_key_last($prizes)];

foreach ($prizes as $prize) {
    $cumulative += (float)$prize['probability'];
    if ($rand <= $cumulative) {
        $winner = $prize;
        break;
    }
}

// Record spin
$db->prepare('INSERT INTO user_spins (user_id, prize_id) VALUES (?, ?)')->execute([$uid, $winner['id']]);

// Award coins if applicable
$balance = CoinsService::getBalance($uid);
if ((int)$winner['coins_value'] > 0) {
    $db->beginTransaction();
    try {
        $db->prepare('UPDATE users SET coins = coins + ? WHERE id = ?')->execute([$winner['coins_value'], $uid]);
        $balance = CoinsService::getBalance($uid);
        $db->prepare(
            'INSERT INTO coin_transactions (user_id, action, coins_earned, coins_spent, balance_after, description) VALUES (?,?,?,0,?,?)'
        )->execute([$uid, 'spin_win', $winner['coins_value'], $balance, 'Won ' . $winner['label'] . ' from spin wheel']);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        jsonError('Failed to award coins');
    }
}

jsonSuccess([
    'prize_id'    => (int)$winner['id'],
    'label'       => $winner['label'],
    'coins_value' => (int)$winner['coins_value'],
    'coupon_code' => $winner['coupon_code'],
    'color_hex'   => $winner['color_hex'],
    'prize_index' => array_search($winner, $prizes),
    'new_balance' => $balance,
    'message'     => (int)$winner['coins_value'] > 0
        ? "You won {$winner['coins_value']} Coins! 🎉"
        : ($winner['coupon_code'] ? "You won a free coupon! Code: {$winner['coupon_code']}" : "Better luck next time!"),
]);
