<?php
define('SECRET',   'adslife_deploy_2026');
define('REPO',     'msj212/adslife');
define('BRANCH',   'main');
define('BASE_DIR', __DIR__);
define('LOG_FILE', '/tmp/adslife_deploy.log');

$sig     = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$payload = file_get_contents('php://input');

if (!hash_equals('sha256=' . hash_hmac('sha256', $payload, SECRET), $sig)) {
    http_response_code(403); exit('Forbidden');
}

$data = json_decode($payload, true);
if (($data['ref'] ?? '') !== 'refs/heads/' . BRANCH) {
    http_response_code(200); exit('Not ' . BRANCH . ' — skipped');
}

$zip_url = "https://github.com/" . REPO . "/archive/refs/heads/" . BRANCH . ".zip";
$tmp_zip = sys_get_temp_dir() . '/adslife_' . time() . '.zip';
$tmp_dir = sys_get_temp_dir() . '/adslife_ext_' . time();

$log = date('Y-m-d H:i:s') . " | Deploy started\n";

// Download zip
$ch = curl_init($zip_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 120,
]);
$zip_data  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200 || !$zip_data) {
    $log .= "ERROR: Download failed (HTTP $http_code)\n";
    file_put_contents(LOG_FILE, $log, FILE_APPEND);
    http_response_code(500); exit('Download failed');
}

file_put_contents($tmp_zip, $zip_data);
$log .= "Downloaded: " . round(strlen($zip_data) / 1024) . " KB\n";

// Extract
$zip = new ZipArchive();
if ($zip->open($tmp_zip) !== true) {
    file_put_contents(LOG_FILE, $log . "ERROR: zip open failed\n", FILE_APPEND);
    http_response_code(500); exit('Zip failed');
}
$zip->extractTo($tmp_dir);
$zip->close();
unlink($tmp_zip);

// Files to never overwrite
$skip = [
    'deploy.php',
    'backend/config/config.php',
    'backend/config/firebase-service-account.json',
    'backend/uploads',
    '.env',
];

function deployCopy(string $src, string $dst, array $skip, string $base): void {
    foreach (scandir($src) as $item) {
        if ($item === '.' || $item === '..') { continue; }
        $s   = "$src/$item";
        $d   = "$dst/$item";
        $rel = ltrim(str_replace($base, '', $d), '/');
        foreach ($skip as $sk) {
            if (str_starts_with($rel, $sk)) { continue 2; }
        }
        if (is_dir($s)) {
            if (!is_dir($d)) { mkdir($d, 0755, true); }
            deployCopy($s, $d, $skip, $base);
        } else {
            copy($s, $d);
        }
    }
}

$src = $tmp_dir . '/' . basename(REPO) . '-' . BRANCH;
deployCopy($src, BASE_DIR, $skip, BASE_DIR);
$log .= "Deployed to: " . BASE_DIR . "\n";

// Cleanup
array_map('unlink', glob("$tmp_dir/*/*") ?: []);
array_map('rmdir',  glob("$tmp_dir/*")   ?: []);
@rmdir($tmp_dir);

// Permissions
$backendDir = BASE_DIR . '/backend';
if (is_dir($backendDir)) {
    shell_exec("find $backendDir -name '*.php' -exec chmod 644 {} \\;");
    shell_exec("chmod -R 755 $backendDir/api/");
}

$log .= "Done at " . date('Y-m-d H:i:s') . "\n---\n";
file_put_contents(LOG_FILE, $log, FILE_APPEND);

http_response_code(200);
echo json_encode(['deployed' => true, 'log' => $log]);
