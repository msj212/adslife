<?php
define('SECRET',   'adslife_deploy_2026');
define('REPO',     'msj212/adslife');
define('BRANCH',   'main');
define('BASE_DIR', __DIR__);
define('LOG_FILE', '/tmp/adslife_deploy.log');

// ── Verify GitHub signature ──────────────────────────────────
$sig     = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$payload = file_get_contents('php://input');

if (!hash_equals('sha256=' . hash_hmac('sha256', $payload, SECRET), $sig)) {
    http_response_code(403); exit('Forbidden');
}

$data = json_decode($payload, true);
if (($data['ref'] ?? '') !== 'refs/heads/' . BRANCH) {
    http_response_code(200); exit('Not ' . BRANCH . ' — skipped');
}

// ── Download repo zip from GitHub ────────────────────────────
$zip_url = "https://github.com/" . REPO . "/archive/refs/heads/" . BRANCH . ".zip";
$tmp_zip = sys_get_temp_dir() . '/adslife_deploy_' . time() . '.zip';
$tmp_dir = sys_get_temp_dir() . '/adslife_extract_' . time();

$log = date('Y-m-d H:i:s') . " | Deploy started\n";

$ch = curl_init($zip_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 60,
]);
$zip_data  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200 || !$zip_data) {
    $log .= "ERROR: Failed to download zip (HTTP $http_code)\n";
    file_put_contents(LOG_FILE, $log, FILE_APPEND);
    http_response_code(500); exit('Download failed');
}

file_put_contents($tmp_zip, $zip_data);
$log .= "Downloaded: " . round(strlen($zip_data) / 1024) . " KB\n";

// ── Extract zip ──────────────────────────────────────────────
$zip = new ZipArchive();
if ($zip->open($tmp_zip) !== true) {
    $log .= "ERROR: Cannot open zip\n";
    file_put_contents(LOG_FILE, $log, FILE_APPEND);
    http_response_code(500); exit('Zip open failed');
}
$zip->extractTo($tmp_dir);
$zip->close();
unlink($tmp_zip);

// ── Copy files to site root (skip sensitive files) ───────────
$skip = [
    'deploy.php',
    'backend/config/config.php',
    'backend/config/firebase-service-account.json',
    '.env',
];

function deployCopy(string $src, string $dst, array $skip, string $baseDst): void {
    foreach (scandir($src) as $item) {
        if ($item === '.' || $item === '..') { continue; }
        $s   = $src . '/' . $item;
        $d   = $dst . '/' . $item;
        $rel = ltrim(str_replace($baseDst, '', $d), '/');
        if (in_array($rel, $skip, true)) { continue; }
        if (is_dir($s)) {
            if (!is_dir($d)) { mkdir($d, 0755, true); }
            deployCopy($s, $d, $skip, $baseDst);
        } else {
            copy($s, $d);
        }
    }
}

$src = $tmp_dir . '/' . basename(REPO) . '-' . BRANCH;
deployCopy($src, BASE_DIR, $skip, BASE_DIR);
$log .= "Files deployed to: " . BASE_DIR . "\n";

// ── Cleanup temp ─────────────────────────────────────────────
array_map('unlink', glob($tmp_dir . '/*/*') ?: []);
array_map('rmdir',  glob($tmp_dir . '/*')   ?: []);
rmdir($tmp_dir);

$log .= "Done at " . date('Y-m-d H:i:s') . "\n---\n";
file_put_contents(LOG_FILE, $log, FILE_APPEND);

http_response_code(200);
echo json_encode(['deployed' => true, 'log' => $log]);
