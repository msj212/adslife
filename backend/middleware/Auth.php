<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/CORS.php';

class Auth {
    private static ?array $currentUser = null;

    public static function require(): array {
        $token = self::extractToken();
        if (!$token) jsonError('Authorization token required', 401);

        $payload = self::validateToken($token);
        if (!$payload) jsonError('Invalid or expired token', 401);

        self::$currentUser = $payload;
        return $payload;
    }

    public static function requireRole(string ...$roles): array {
        $user = self::require();
        if (!in_array($user['role'], $roles, true)) {
            jsonError('Insufficient permissions', 403);
        }
        return $user;
    }

    public static function optional(): ?array {
        $token = self::extractToken();
        if (!$token) return null;
        return self::validateToken($token);
    }

    public static function generateToken(int $userId, string $role): string {
        $header  = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'iss'     => JWT_ISSUER,
            'aud'     => JWT_AUDIENCE,
            'sub'     => $userId,
            'role'    => $role,
            'user_id' => $userId,
            'iat'     => time(),
            'exp'     => time() + JWT_TTL,
        ]));
        $sig = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
        return "$header.$payload.$sig";
    }

    private static function extractToken(): ?string {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }
        return null;
    }

    private static function validateToken(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $payload, $sig] = $parts;
        $expectedSig = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));

        if (!hash_equals($expectedSig, $sig)) return null;

        $data = json_decode(base64_decode($payload), true);
        if (!$data) return null;
        if (($data['exp'] ?? 0) < time()) return null;
        if (($data['iss'] ?? '') !== JWT_ISSUER) return null;

        return $data;
    }
}
