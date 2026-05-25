<?php
require_once __DIR__ . '/../config/database.php';

class CoinsService {
    private const RULES = [
        'daily_login'     => 5,
        'first_save'      => 10,
        'save_offer'      => 2,
        'redeem_offer'    => 15,
        'write_review'    => 20,
        'referral_signup' => 50,
        'streak_7_days'   => 100,
        'streak_30_days'  => 500,
        'share_offer'     => 5,
    ];

    private const DESCRIPTIONS = [
        'daily_login'     => 'Daily login bonus',
        'first_save'      => 'First offer saved — welcome bonus!',
        'save_offer'      => 'Saved an offer',
        'redeem_offer'    => 'Redeemed an offer',
        'write_review'    => 'Wrote a review',
        'referral_signup' => 'Friend signed up with your referral',
        'streak_7_days'   => '7-day streak bonus!',
        'streak_30_days'  => '30-day streak bonus!',
        'share_offer'     => 'Shared an offer',
    ];

    public static function award(int $userId, string $action): array {
        $coins = self::RULES[$action] ?? 0;
        if ($coins <= 0) return ['coins_earned' => 0, 'balance' => self::getBalance($userId)];

        // daily_login: once per day
        if ($action === 'daily_login') {
            if (!self::canAwardToday($userId, 'daily_login')) {
                return ['coins_earned' => 0, 'balance' => self::getBalance($userId)];
            }
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE users SET coins = coins + ? WHERE id = ?')->execute([$coins, $userId]);
            $balance = self::getBalance($userId);
            $db->prepare(
                'INSERT INTO coin_transactions (user_id, action, coins_earned, coins_spent, balance_after, description) VALUES (?,?,?,0,?,?)'
            )->execute([$userId, $action, $coins, $balance, self::DESCRIPTIONS[$action] ?? $action]);
            $db->commit();

            // Check badges asynchronously (no await needed)
            self::checkBadges($userId);

            return ['coins_earned' => $coins, 'balance' => $balance];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function spend(int $userId, int $amount, string $reason): array {
        $balance = self::getBalance($userId);
        if ($balance < $amount) throw new RuntimeException('Insufficient coins');

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE users SET coins = coins - ? WHERE id = ?')->execute([$amount, $userId]);
            $newBalance = $balance - $amount;
            $db->prepare(
                'INSERT INTO coin_transactions (user_id, action, coins_earned, coins_spent, balance_after, description) VALUES (?,?,0,?,?,?)'
            )->execute([$userId, 'spend', $amount, $newBalance, $reason]);
            $db->commit();
            return ['coins_spent' => $amount, 'balance' => $newBalance];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function getBalance(int $userId): int {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT coins FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        return (int)($stmt->fetchColumn() ?? 0);
    }

    public static function getHistory(int $userId, int $limit = 20): array {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM coin_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    private static function canAwardToday(int $userId, string $action): bool {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT id FROM coin_transactions WHERE user_id = ? AND action = ? AND DATE(created_at) = CURDATE() LIMIT 1'
        );
        $stmt->execute([$userId, $action]);
        return !$stmt->fetch();
    }

    private static function checkBadges(int $userId): void {
        try {
            require_once __DIR__ . '/BadgeService.php';
            BadgeService::checkAndAward($userId);
        } catch (Throwable) {}
    }
}
