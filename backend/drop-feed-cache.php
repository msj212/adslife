<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();
$db->exec('DROP TABLE IF EXISTS feed_cache');
echo 'feed_cache table dropped.';
