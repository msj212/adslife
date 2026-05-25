<?php
define('SECRET', 'adslife_deploy_2026');
define('REPO_PATH', '/home/sites/40a/2/22ecda06d2/public_html/adslife');

$sig     = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$payload = file_get_contents('php://input');

if (!hash_equals('sha256=' . hash_hmac('sha256', $payload, SECRET), $sig)) {
    http_response_code(403); exit('Forbidden');
}

$data = json_decode($payload, true);
if (($data['ref'] ?? '') !== 'refs/heads/main') {
    http_response_code(200); exit('Not main — skipped');
}

$log  = date('Y-m-d H:i:s') . "\n";
$log .= shell_exec('cd ' . REPO_PATH . ' && git pull origin main 2>&1');
$log .= shell_exec('find ' . REPO_PATH . '/backend/ -name "*.php" -exec chmod 644 {} \; 2>&1');
$log .= shell_exec('chmod -R 755 ' . REPO_PATH . '/backend/api/ 2>&1');
$log .= "\n---\n";

file_put_contents('/tmp/adslife_deploy.log', $log, FILE_APPEND);

http_response_code(200);
echo json_encode(['deployed' => true]);
