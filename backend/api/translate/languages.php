<?php
require_once __DIR__ . '/../../middleware/CORS.php';

applyCORS();

jsonSuccess([
    ['code' => 'en', 'name' => 'English',    'native' => 'English'],
    ['code' => 'ta', 'name' => 'Tamil',      'native' => 'தமிழ்'],
    ['code' => 'hi', 'name' => 'Hindi',      'native' => 'हिंदी'],
    ['code' => 'te', 'name' => 'Telugu',     'native' => 'తెలుగు'],
    ['code' => 'kn', 'name' => 'Kannada',    'native' => 'ಕನ್ನಡ'],
    ['code' => 'ml', 'name' => 'Malayalam',  'native' => 'മലയാളം'],
]);
