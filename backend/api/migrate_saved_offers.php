<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();

$db->exec("
    CREATE TABLE IF NOT EXISTS saved_offers (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id    INT UNSIGNED NOT NULL,
        offer_id   INT UNSIGNED NOT NULL,
        saved_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_offer (user_id, offer_id),
        KEY idx_user_saved (user_id, saved_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

echo json_encode(['success' => true, 'message' => 'saved_offers table created']);
