<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';

applyCORS();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$body    = json_decode(file_get_contents('php://input'), true);
$offerId = (int)($body['offer_id'] ?? 0);
$lang    = $body['target_lang'] ?? 'ta';

$supported = ['ta', 'hi', 'te', 'kn', 'ml', 'en'];
if (!in_array($lang, $supported, true)) jsonError('Unsupported language');
if (!$offerId) jsonError('offer_id required');

$db = Database::getInstance();

// Check existing translation
$existing = $db->prepare('SELECT title, description FROM offer_translations WHERE offer_id = ? AND language_code = ?');
$existing->execute([$offerId, $lang]);
$cached = $existing->fetch();
if ($cached) {
    jsonSuccess($cached);
}

// Fetch original
$orig = $db->prepare('SELECT title, description FROM offers WHERE id = ?');
$orig->execute([$offerId]);
$offer = $orig->fetch();
if (!$offer) jsonError('Offer not found', 404);

if ($lang === 'en') {
    jsonSuccess(['title' => $offer['title'], 'description' => $offer['description']]);
}

// Translate via MyMemory
$translatedTitle = translateText($offer['title'], $lang);
$translatedDesc  = translateText($offer['description'] ?? '', $lang);

// Cache translation
$db->prepare(
    'INSERT INTO offer_translations (offer_id, language_code, title, description) VALUES (?,?,?,?)
     ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description)'
)->execute([$offerId, $lang, $translatedTitle, $translatedDesc]);

jsonSuccess(['title' => $translatedTitle, 'description' => $translatedDesc]);

function translateText(string $text, string $targetLang): string {
    if (!$text) return '';
    $url      = MYMEMORY_API . '?' . http_build_query(['q' => $text, 'langpair' => "en|$targetLang"]);
    $context  = stream_context_create(['http' => ['timeout' => 5]]);
    $response = @file_get_contents($url, false, $context);
    $data     = $response ? json_decode($response, true) : null;
    return $data['responseData']['translatedText'] ?? $text;
}
