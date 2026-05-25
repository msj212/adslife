<?php
require_once __DIR__ . '/../config/database.php';

class FraudDetector {
    private const RULES = [
        'duplicate_business_name'   => 25,
        'suspicious_discount'       => 20,
        'no_website_no_gst'         => 15,
        'bulk_offer_creation'       => 20,
        'copied_description'        => 25,
        'invalid_phone_pattern'     => 15,
        'missing_location_data'     => 10,
        'newly_registered_bulk_post'=> 20,
    ];

    public static function checkVendor(int $vendorId): array {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT v.*, u.created_at as user_created_at FROM vendors v JOIN users u ON v.user_id = u.id WHERE v.id = ?');
        $stmt->execute([$vendorId]);
        $vendor = $stmt->fetch();
        if (!$vendor) return ['score' => 0, 'flags' => [], 'action' => 'none'];

        $score  = 0;
        $flags  = [];

        // Duplicate business name (Levenshtein < 3)
        $others = $db->prepare('SELECT id, business_name FROM vendors WHERE id != ? AND status != "rejected"');
        $others->execute([$vendorId]);
        foreach ($others->fetchAll() as $v) {
            if (levenshtein(strtolower($vendor['business_name']), strtolower($v['business_name'])) < 3) {
                $score += self::RULES['duplicate_business_name'];
                $flags[] = 'duplicate_business_name';
                break;
            }
        }

        // No website AND no GST
        if (empty($vendor['website']) && empty($vendor['gst_number'])) {
            $score += self::RULES['no_website_no_gst'];
            $flags[] = 'no_website_no_gst';
        }

        // Bulk offer creation: >10 offers in <1 hour
        $bulkStmt = $db->prepare(
            'SELECT COUNT(*) FROM offers WHERE vendor_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
        $bulkStmt->execute([$vendorId]);
        if ((int)$bulkStmt->fetchColumn() > 10) {
            $score += self::RULES['bulk_offer_creation'];
            $flags[] = 'bulk_offer_creation';
        }

        // Invalid phone pattern (all same digit, test numbers)
        $phone = preg_replace('/\D/', '', $vendor['phone'] ?? '');
        if ($phone && (preg_match('/^(\d)\1{9,}$/', $phone) || in_array($phone, ['1234567890', '9876543210', '0000000000']))) {
            $score += self::RULES['invalid_phone_pattern'];
            $flags[] = 'invalid_phone_pattern';
        }

        // Missing lat/lng
        if (empty($vendor['lat']) || empty($vendor['lng'])) {
            $score += self::RULES['missing_location_data'];
            $flags[] = 'missing_location_data';
        }

        // Newly registered + >5 offers
        $userAge    = (time() - strtotime($vendor['user_created_at'])) / 3600;
        $offerCount = $db->prepare('SELECT COUNT(*) FROM offers WHERE vendor_id = ?');
        $offerCount->execute([$vendorId]);
        if ($userAge < 24 && (int)$offerCount->fetchColumn() > 5) {
            $score += self::RULES['newly_registered_bulk_post'];
            $flags[] = 'newly_registered_bulk_post';
        }

        return self::buildResult($score, $flags, 'vendor', $vendorId, $db);
    }

    public static function checkOffer(int $offerId): array {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM offers WHERE id = ?');
        $stmt->execute([$offerId]);
        $offer = $stmt->fetch();
        if (!$offer) return ['score' => 0, 'flags' => [], 'action' => 'none'];

        $score = 0;
        $flags = [];

        // Suspicious discount > 80%
        if ((float)$offer['discount_percent'] > 80) {
            $score += self::RULES['suspicious_discount'];
            $flags[] = 'suspicious_discount';
        }

        // Copied description (MD5 hash match)
        if (!empty($offer['description'])) {
            $hash     = md5($offer['description']);
            $dupStmt  = $db->prepare('SELECT id FROM offers WHERE MD5(description) = ? AND id != ? LIMIT 1');
            $dupStmt->execute([$hash, $offerId]);
            if ($dupStmt->fetch()) {
                $score += self::RULES['copied_description'];
                $flags[] = 'copied_description';
            }
        }

        return self::buildResult($score, $flags, 'offer', $offerId, $db);
    }

    private static function buildResult(int $score, array $flags, string $type, int $entityId, PDO $db): array {
        $action = 'none';
        if ($score >= 85) $action = 'auto_reject';
        elseif ($score >= 60) $action = 'flag_review';

        if ($action !== 'none') {
            $db->prepare(
                'INSERT INTO fraud_flags (entity_type, entity_id, flag_reason, confidence_score) VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE flag_reason=VALUES(flag_reason), confidence_score=VALUES(confidence_score)'
            )->execute([$type, $entityId, implode(', ', $flags), $score]);
        }

        return [
            'score'      => $score,
            'flags'      => $flags,
            'action'     => $action,
            'max_score'  => 100,
            'risk_level' => $score >= 85 ? 'high' : ($score >= 60 ? 'medium' : 'low'),
        ];
    }
}
