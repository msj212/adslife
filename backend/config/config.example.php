<?php
// Copy this file to config.php and fill in your values
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'your_db_name');
define('DB_USER', getenv('DB_USER') ?: 'your_db_user');
define('DB_PASS', getenv('DB_PASS') ?: 'your_db_password');

define('JWT_SECRET',   getenv('JWT_SECRET')   ?: 'change_this_to_a_strong_random_secret');
define('JWT_ISSUER',   'adslife.in');
define('JWT_AUDIENCE', 'adslife_users');
define('JWT_TTL',      86400);

define('APP_ENV',      getenv('APP_ENV')      ?: 'development');
define('APP_URL',      getenv('APP_URL')      ?: 'https://yourdomain.com/backend');
define('FRONTEND_URL', getenv('FRONTEND_URL') ?: 'https://yourdomain.com');

define('MYMEMORY_API',  'https://api.mymemory.translated.net/get');
define('NOMINATIM_API', 'https://nominatim.openstreetmap.org');

// Firebase Cloud Messaging – place firebase-service-account.json in this directory
define('FCM_SERVER_KEY', getenv('FCM_SERVER_KEY') ?: '');

// Cashfree Payment Gateway
define('CASHFREE_APP_ID',         getenv('CASHFREE_APP_ID')         ?: '');
define('CASHFREE_SECRET_KEY',     getenv('CASHFREE_SECRET_KEY')     ?: '');
define('CASHFREE_WEBHOOK_SECRET', getenv('CASHFREE_WEBHOOK_SECRET') ?: '');
define('CASHFREE_ENV',            getenv('CASHFREE_ENV')            ?: 'sandbox');
define('CASHFREE_BASE_URL', CASHFREE_ENV === 'production'
    ? 'https://api.cashfree.com/pg'
    : 'https://sandbox.cashfree.com/pg');
