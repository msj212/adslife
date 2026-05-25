<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();
$db->exec("
  CREATE TABLE IF NOT EXISTS payments (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    user_id             INT NOT NULL,
    order_id            VARCHAR(64) NOT NULL UNIQUE,
    payment_session_id  VARCHAR(128),
    cashfree_payment_id VARCHAR(64),
    amount              DECIMAL(10,2) NOT NULL,
    status              ENUM('pending','paid','failed') DEFAULT 'pending',
    purpose             VARCHAR(50) DEFAULT 'vendor_plan',
    reference_id        INT,
    reference_type      VARCHAR(50),
    paid_at             DATETIME,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )
");
echo "payments table ready.";
