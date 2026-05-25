<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();
// Fix column default
$db->exec("ALTER TABLE notifications MODIFY is_read TINYINT(1) NOT NULL DEFAULT 0");
echo "Column default fixed.\n";
// Create site_settings table
$db->exec("
  CREATE TABLE IF NOT EXISTS site_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )
");
// Seed defaults
$db->exec("
  INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES
    ('site_name', 'AdsLife'),
    ('site_tagline', 'Discover · Earn · Win'),
    ('site_logo_url', ''),
    ('seo_title', 'AdsLife - Discover Local Offers & Deals'),
    ('seo_description', 'Find the best local deals, earn coins, and win rewards on AdsLife.'),
    ('seo_keywords', 'offers, deals, discounts, local, coins, rewards'),
    ('contact_email', ''),
    ('contact_phone', '')
");
echo "site_settings table ready.\n";
