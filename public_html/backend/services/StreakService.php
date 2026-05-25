<?php
require_once __DIR__ . '/../config/database.php';

class StreakService {
    private const MILESTONES = [3, 7, 14, 30, 60, 100];
    private const MILESTONE_COINS = [
        3   => 20,
        7   => 100,
        14  => 200,
        30  => 500,
        60  => 1000,
        100 => 2500,
    ];

    public static function update(int $userId): array {
        $db = Database::getInstance();

        // Ensure streak row exists
        $db->prepare('INSERT IGNORE INTO user_streaks (user_id) VALUES (?)')->execute([$userId]);

        $stmt = $db->prepare('SELECT * FROM user_streaks WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $streak = $stmt->fetch();

        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $lastDate  = $streak['last_active_date'];

        $newStreak = (int)$streak['current_streak'];
        $longest   = (int)$streak['longest_streak'];

        if ($lastDate === $today) {
            // Already logged today — no change
            return self::buildResponse($streak);
        } elseif ($lastDate === $yesterday) {
            $newStreak++;
        } else {
            $newStreak = 1;
        }

        $longest = max($longest, $newStreak);

        $db->prepare(
            'UPDATE user_streaks SET current_streak=?, longest_streak=?, last_active_date=? WHERE user_id=?'
        )->execute([$newStreak, $longest, $today, $userId]);

        $db->prepare('UPDATE users SET streak_days=? WHERE id=?')->execute([$newStreak, $userId]);

        // Award milestone coins
        $bonusCoins = 0;
        if (in_array($newStreak, self::MILESTONES, true)) {
            $action = $newStreak >= 30 ? 'streak_30_days' : 'streak_7_days';
            require_once __DIR__ . '/CoinsService.php';
            $result     = CoinsService::award($userId, $action);
            $bonusCoins = $result['coins_earned'];
        }

        return [
            'current_streak'  => $newStreak,
            'longest_streak'  => $longest,
            'last_active_date'=> $today,
            'bonus_coins'     => $bonusCoins,
            'next_milestone'  => self::nextMilestone($newStreak),
        ];
    }

    public static function getStatus(int $userId): array {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM user_streaks WHERE user_id = ?');
        $stmt->execute([$userId]);
        $streak = $stmt->fetch();
        if (!$streak) return ['current_streak' => 0, 'longest_streak' => 0, 'next_milestone' => 3];
        return self::buildResponse($streak);
    }

    private static function buildResponse(array $streak): array {
        $current = (int)$streak['current_streak'];
        return [
            'current_streak'   => $current,
            'longest_streak'   => (int)$streak['longest_streak'],
            'last_active_date' => $streak['last_active_date'],
            'next_milestone'   => self::nextMilestone($current),
            'days_until_next'  => self::nextMilestone($current) - $current,
        ];
    }

    private static function nextMilestone(int $current): int {
        foreach (self::MILESTONES as $m) {
            if ($m > $current) return $m;
        }
        return 100;
    }
}
