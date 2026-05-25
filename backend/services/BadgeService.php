<?php
require_once __DIR__ . '/../config/database.php';

class BadgeService {
    public static function checkAndAward(int $userId): array {
        $db      = Database::getInstance();
        $stats   = self::getUserStats($userId);
        $all     = $db->query('SELECT * FROM badges')->fetchAll();
        $earned  = $db->prepare('SELECT badge_id FROM user_badges WHERE user_id = ?');
        $earned->execute([$userId]);
        $earnedIds = array_column($earned->fetchAll(), 'badge_id');

        $newBadges = [];
        foreach ($all as $badge) {
            if (in_array($badge['id'], $earnedIds, true)) continue;
            if (self::qualifies($badge, $stats, $userId)) {
                $db->prepare('INSERT IGNORE INTO user_badges (user_id, badge_id) VALUES (?, ?)')->execute([$userId, $badge['id']]);
                $newBadges[] = $badge;
            }
        }
        return $newBadges;
    }

    public static function getUserBadges(int $userId): array {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT b.*, ub.earned_at, IF(ub.id IS NOT NULL, 1, 0) as earned
             FROM badges b
             LEFT JOIN user_badges ub ON b.id = ub.badge_id AND ub.user_id = ?
             ORDER BY earned DESC, b.id ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    private static function getUserStats(int $userId): array {
        $db = Database::getInstance();

        $saves = $db->prepare('SELECT COUNT(*) FROM user_interactions WHERE user_id = ? AND action = "save"');
        $saves->execute([$userId]);

        $redemptions = $db->prepare('SELECT COUNT(*) FROM user_interactions WHERE user_id = ? AND action = "redeem"');
        $redemptions->execute([$userId]);

        $coins = $db->prepare('SELECT coins FROM users WHERE id = ?');
        $coins->execute([$userId]);

        $streak = $db->prepare('SELECT current_streak FROM user_streaks WHERE user_id = ?');
        $streak->execute([$userId]);

        return [
            'saves'       => (int)$saves->fetchColumn(),
            'redemptions' => (int)$redemptions->fetchColumn(),
            'coins'       => (int)$coins->fetchColumn(),
            'streak'      => (int)($streak->fetchColumn() ?? 0),
            'reviews'     => 0, // extend later
        ];
    }

    private static function qualifies(array $badge, array $stats, int $userId): bool {
        return match ($badge['condition_type']) {
            'saves'       => $stats['saves']       >= $badge['condition_value'],
            'redemptions' => $stats['redemptions'] >= $badge['condition_value'],
            'coins'       => $stats['coins']        >= $badge['condition_value'],
            'streak'      => $stats['streak']       >= $badge['condition_value'],
            'reviews'     => $stats['reviews']      >= $badge['condition_value'],
            'user_id_lte' => $userId                <= $badge['condition_value'],
            default       => false,
        };
    }
}
