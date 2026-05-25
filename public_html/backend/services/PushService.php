<?php
/**
 * Firebase Cloud Messaging push sender (HTTP v1 API).
 * Requires a service account JSON key at backend/config/firebase-service-account.json
 */
class PushService {

    private static function getServiceAccount(): array {
        $path = __DIR__ . '/../config/firebase-service-account.json';
        if (!file_exists($path)) { return []; }
        return json_decode(file_get_contents($path), true) ?? [];
    }

    /**
     * Generate a short-lived OAuth2 access token from the service account key.
     * Implements the JWT Bearer flow without any external libraries.
     */
    private static function getAccessToken(array $sa): string|false {
        $now = time();
        $header  = self::b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = self::b64url(json_encode([
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $data = "$header.$payload";
        openssl_sign($data, $sig, $sa['private_key'], 'SHA256');
        $jwt = "$data." . self::b64url($sig);

        $resp = self::httpPost('https://oauth2.googleapis.com/token', http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]), 'application/x-www-form-urlencoded');

        $json = json_decode($resp, true);
        return $json['access_token'] ?? false;
    }

    private static function b64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function httpPost(string $url, string $body, string $contentType): string {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: $contentType\r\n",
            'content' => $body,
            'timeout' => 10,
            'ignore_errors' => true,
        ]]);
        return (string)@file_get_contents($url, false, $ctx);
    }

    /**
     * Send a push notification to one or more users.
     *
     * @param int|int[] $userIds
     * @param string $title
     * @param string $body
     * @param array  $data   Extra key-value pairs (offer_id, type, etc.)
     */
    public static function send(int|array $userIds, string $title, string $body, array $data = []): int {
        $sa = self::getServiceAccount();
        if (empty($sa['project_id'])) { return 0; }

        $accessToken = self::getAccessToken($sa);
        if (!$accessToken) { return 0; }

        $projectId = $sa['project_id'];
        $url       = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";

        // Fetch FCM tokens for the target users
        require_once __DIR__ . '/../config/database.php';
        $db = Database::getInstance();

        $ids = is_array($userIds) ? $userIds : [$userIds];
        if (empty($ids)) { return 0; }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare(
            "SELECT token FROM user_fcm_tokens WHERE user_id IN ($placeholders)"
        );
        $stmt->execute($ids);
        $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tokens)) { return 0; }

        $sent = 0;
        $staleTokens = [];

        // Map token → user_id for per-user notification records
        $plIds = implode(',', array_fill(0, count($ids), '?'));
        $mapStmt = $db->prepare("SELECT token, user_id FROM user_fcm_tokens WHERE user_id IN ($plIds)");
        $mapStmt->execute($ids);
        $tokenUserMap = [];
        foreach ($mapStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $tokenUserMap[$row['token']] = (int)$row['user_id'];
        }

        foreach ($tokens as $token) {
            $message = [
                'message' => [
                    'token'        => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data'         => array_map('strval', $data),
                    'android'      => ['notification' => ['icon' => 'ic_notification', 'color' => '#FF6200']],
                    'webpush'      => ['notification' => ['icon' => '/favicon.svg', 'badge' => '/favicon.svg']],
                ],
            ];

            $ctx = stream_context_create(['http' => [
                'method'  => 'POST',
                'header'  => "Authorization: Bearer $accessToken\r\nContent-Type: application/json\r\n",
                'content' => json_encode($message),
                'timeout' => 8,
                'ignore_errors' => true,
            ]]);
            $resp = @file_get_contents($url, false, $ctx);
            $result = json_decode($resp, true);

            if (isset($result['name'])) {
                $sent++;
                // Persist notification to DB so list.php can surface it
                $uid     = $tokenUserMap[$token] ?? null;
                $offerId = isset($data['offer_id']) ? (int)$data['offer_id'] : null;
                $type    = $data['type'] ?? 'push';
                if ($uid) {
                    $db->prepare(
                        'INSERT INTO notifications (user_id, title, body, type, offer_id, is_read) VALUES (?, ?, ?, ?, ?, 0)'
                    )->execute([$uid, $title, $body, $type, $offerId]);
                    require_once __DIR__ . '/FirestoreService.php';
                    FirestoreService::notificationSent($uid);
                }
            } elseif (isset($result['error']['details'])) {
                foreach ($result['error']['details'] as $detail) {
                    if (($detail['errorCode'] ?? '') === 'UNREGISTERED') {
                        $staleTokens[] = $token;
                    }
                }
            }
        }

        // Clean up stale tokens
        if (!empty($staleTokens)) {
            $pl = implode(',', array_fill(0, count($staleTokens), '?'));
            $db->prepare("DELETE FROM user_fcm_tokens WHERE token IN ($pl)")->execute($staleTokens);
        }

        return $sent;
    }
}
