<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
$authUser = Auth::optional();

$userId = (int)($_GET['user_id'] ?? ($authUser['user_id'] ?? 0));
$lat    = (float)($_GET['lat'] ?? 13.0827);
$lng    = (float)($_GET['lng'] ?? 80.2707);
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;

$db = Database::getInstance();

// Fetch user preferences
$prefStmt = $db->prepare('SELECT * FROM user_preferences WHERE user_id = ?');
$prefStmt->execute([$userId]);
$prefs = $prefStmt->fetch();

$preferredCategories = $prefs ? (json_decode($prefs['preferred_categories'], true) ?: []) : [];
$preferredVendors    = $prefs ? (json_decode($prefs['preferred_vendors'], true) ?: []) : [];
$maxDist             = $prefs ? (int)$prefs['max_distance_km'] : 15;

// Fetch active offers with vendor location
$offset = ($page - 1) * 100;
$stmt   = $db->prepare(
    'SELECT o.*, v.business_name, v.logo_url as vendor_logo, v.lat as vlat, v.lng as vlng,
            v.category as vendor_category, v.city as vendor_city,
            v.address as vendor_address, v.phone as vendor_phone, v.website as vendor_website
     FROM offers o
     JOIN vendors v ON o.vendor_id = v.id
     WHERE o.is_active = 1
       AND (o.valid_until IS NULL OR o.valid_until >= NOW())
       AND v.status = "approved"
     ORDER BY o.is_featured DESC, o.created_at DESC
     LIMIT 200 OFFSET ?'
);
$stmt->execute([$offset]);
$offers = $stmt->fetchAll();

// Score each offer
$scored = [];
foreach ($offers as $offer) {
    $score = 0.0;

    // 1. Category match (0.35 weight)
    $categoryMatch = 0.0;
    if (!empty($preferredCategories) && in_array($offer['category'], $preferredCategories, true)) {
        $categoryMatch = 1.0;
    } elseif (!empty($offer['category'])) {
        $categoryMatch = 0.3; // slight base score
    }
    $score += $categoryMatch * 0.35;

    // 2. Distance decay (0.25 weight) — Haversine; treat unknown vendor location as local
    if ($offer['vlat'] === null || $offer['vlng'] === null) {
        $dist      = 0;
        $distScore = 0.8;
    } else {
        $dist = haversine($lat, $lng, (float)$offer['vlat'], (float)$offer['vlng']);
        if ($dist <= 1)       { $distScore = 1.0; }
        elseif ($dist <= 5)   { $distScore = 0.8; }
        elseif ($dist <= 10)  { $distScore = 0.5; }
        elseif ($dist <= 20)  { $distScore = 0.3; }
        else                  { $distScore = 0.1; }
    }
    $score += $distScore * 0.25;

    // 3. Discount attractiveness (0.20 weight)
    $discountScore = min((float)$offer['discount_percent'] / 100.0, 1.0);
    $score += $discountScore * 0.20;

    // 4. Recency (0.10 weight)
    $ageHours    = max(0, (time() - strtotime($offer['created_at'])) / 3600);
    $recencyScore = $ageHours < 24 ? 1.0 : ($ageHours < 72 ? 0.7 : ($ageHours < 168 ? 0.4 : 0.1));
    $score += $recencyScore * 0.10;

    // 5. Vendor follow bonus (0.10 weight)
    $followBonus = in_array($offer['vendor_id'], $preferredVendors, true) ? 1.0 : 0.0;
    $score += $followBonus * 0.10;

    // Featured boost
    if ($offer['is_featured']) $score += 0.05;

    $offer['score']    = round($score, 4);
    $offer['distance'] = round($dist, 1);
    $scored[]          = $offer;
}

// Sort by score
usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
$result = array_slice($scored, 0, $limit);

jsonSuccess($result);

function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float {
    if ($lat2 == 0 && $lng2 == 0) return 999.0;
    $R      = 6371;
    $dLat   = deg2rad($lat2 - $lat1);
    $dLng   = deg2rad($lng2 - $lng1);
    $a      = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}
