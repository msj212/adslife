<?php
define('DB_HOST', getenv('DB_HOST') ?: 'shareddb-d.hosting.stackcp.net');
define('DB_NAME', getenv('DB_NAME') ?: 'adslife-3635f36d');
define('DB_USER', getenv('DB_USER') ?: 'adslife-3635f36d');
define('DB_PASS', getenv('DB_PASS') ?: 'Jaya91599@');

define('JWT_SECRET',    getenv('JWT_SECRET')    ?: 'adslife_jwt_secret_change_in_production');
define('JWT_ISSUER',    'adslife.in');
define('JWT_AUDIENCE',  'adslife_users');
define('JWT_TTL',       86400);

define('APP_ENV',  getenv('APP_ENV') ?: 'development');
define('APP_URL',      getenv('APP_URL')      ?: 'https://adslifebackend.stss.in');
define('FRONTEND_URL', getenv('FRONTEND_URL') ?: 'https://adslife.in/');

define('MYMEMORY_API', 'https://api.mymemory.translated.net/get');
define('NOMINATIM_API', 'https://nominatim.openstreetmap.org');

define('FCM_SERVER_KEY', getenv('FCM_SERVER_KEY') ?: '');

define('CASHFREE_APP_ID',       getenv('CASHFREE_APP_ID')       ?: 'TEST103494148a09ef036aa84ae62f2541494301');
define('CASHFREE_SECRET_KEY',   getenv('CASHFREE_SECRET_KEY')   ?: 'cfsk_ma_test_9c5ab65870fbc7172926ad33c4ba1a7d_228b4ccd');
define('CASHFREE_WEBHOOK_SECRET', getenv('CASHFREE_WEBHOOK_SECRET') ?: '3mcpvi6v8qeu5v7nzms6');
define('CASHFREE_ENV',          getenv('CASHFREE_ENV')          ?: 'sandbox');
define('CASHFREE_BASE_URL',     CASHFREE_ENV === 'production'
    ? 'https://api.cashfree.com/pg'
    : 'https://sandbox.cashfree.com/pg');
