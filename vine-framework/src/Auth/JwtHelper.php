<?php

namespace Vine\Auth;

class JwtHelper
{
    private string $secret;
    private int $ttl;

    public function __construct()
    {
        $this->secret = $_ENV['APP_KEY'] ?? 'default-secret-change-me';
        $this->ttl = (int) ($_ENV['JWT_TTL'] ?? 900);
    }

    public function encode(array $payload): string
    {
        $payload['iat'] = time();
        $payload['exp'] = time() + $this->ttl;

        $header = $this->base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = $this->base64url(json_encode($payload));
        $signature = $this->base64url(hash_hmac('sha256', "$header.$body", $this->secret, true));

        return "$header.$body.$signature";
    }

    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $body, $signature] = $parts;

        $expected = $this->base64url(hash_hmac('sha256', "$header.$body", $this->secret, true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode($this->base64urlDecode($body), true);
        if (!$payload) {
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    public function generateRefreshToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
