<?php
/**
 * Sends real-time signals to Firestore using the existing service account.
 * Uses Firestore REST API — no PHP SDK needed.
 *
 * Signal format: { updatedAt: timestamp, meta: {...} }
 * Frontend listens with onSnapshot and re-fetches MySQL data when fired.
 */
class FirestoreService {

    private static function getAccessToken(): string|false {
        $path = __DIR__ . '/../config/firebase-service-account.json';
        if (!file_exists($path)) return false;
        $sa = json_decode(file_get_contents($path), true);
        if (empty($sa['project_id'])) return false;

        $now     = time();
        $b64     = fn($d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
        $header  = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $b64(json_encode([
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/datastore',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));
        $data = "$header.$payload";
        openssl_sign($data, $sig, $sa['private_key'], 'SHA256');
        $jwt = "$data." . $b64($sig);

        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content'       => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
            'timeout'       => 5,
            'ignore_errors' => true,
        ]]);
        $resp = @file_get_contents('https://oauth2.googleapis.com/token', false, $ctx);
        return json_decode($resp, true)['access_token'] ?? false;
    }

    /**
     * Write a signal document to Firestore.
     *
     * @param string $collection  e.g. 'feed', 'vendor_stats', 'notifications'
     * @param string $docId       e.g. 'latest', vendor ID, user ID
     * @param array  $meta        Small extra data (not the full row)
     */
    public static function signal(string $collection, string $docId, array $meta = []): bool {
        $token = self::getAccessToken();
        if (!$token) return false;

        $sa        = json_decode(file_get_contents(__DIR__ . '/../config/firebase-service-account.json'), true);
        $projectId = $sa['project_id'];
        $url       = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/signals_{$collection}/{$docId}";

        // Build Firestore field map
        $fields = ['updatedAt' => ['integerValue' => (string)time()]];
        foreach ($meta as $k => $v) {
            $fields[$k] = is_int($v) ? ['integerValue' => (string)$v] : ['stringValue' => (string)$v];
        }

        $body = json_encode(['fields' => $fields]);
        $ctx  = stream_context_create(['http' => [
            'method'        => 'PATCH',
            'header'        => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
            'content'       => $body,
            'timeout'       => 5,
            'ignore_errors' => true,
        ]]);
        $resp   = @file_get_contents($url, false, $ctx);
        $result = json_decode($resp, true);
        return isset($result['name']);
    }

    /** New offer created — notify all feed listeners */
    public static function offerCreated(int $vendorId): void {
        self::signal('feed', 'latest', ['vendorId' => $vendorId]);
        self::signal('vendor_stats', (string)$vendorId, ['vendorId' => $vendorId]);
    }

    /** Offer updated or deleted */
    public static function offerChanged(int $vendorId): void {
        self::signal('feed', 'latest', ['vendorId' => $vendorId]);
        self::signal('vendor_stats', (string)$vendorId, ['vendorId' => $vendorId]);
    }

    /** User interaction (view/click/save/redeem) */
    public static function interactionLogged(int $vendorId): void {
        self::signal('vendor_stats', (string)$vendorId, ['vendorId' => $vendorId]);
    }

    /** New notification for a user */
    public static function notificationSent(int $userId): void {
        self::signal('notifications', (string)$userId, ['userId' => $userId]);
    }
}
