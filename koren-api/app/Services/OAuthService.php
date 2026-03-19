<?php

namespace App\Services;

use App\Models\User;
use App\Models\LoyaltyAccount;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class OAuthService
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function loginWithGoogle(string $idToken): array
    {
        $client = new \Google\Client(['client_id' => $_ENV['GOOGLE_CLIENT_ID'] ?? '']);
        $payload = $client->verifyIdToken($idToken);

        if (!$payload) {
            throw new \RuntimeException('GOOGLE_TOKEN_INVALID');
        }

        $googleId = $payload['sub'];
        $email    = $payload['email'] ?? null;
        $name     = $payload['name'] ?? 'Google User';
        $avatar   = $payload['picture'] ?? null;

        return $this->findOrCreateOAuthUser([
            'provider'   => 'google',
            'provider_id'=> $googleId,
            'email'      => $email,
            'name'       => $name,
            'avatar_url' => $avatar,
        ]);
    }

    public function loginWithApple(string $identityToken, ?string $name = null): array
    {
        $keysJson = @file_get_contents('https://appleid.apple.com/auth/keys');
        if (!$keysJson) {
            throw new \RuntimeException('APPLE_KEYS_UNAVAILABLE');
        }

        $keysData = json_decode($keysJson, true);

        try {
            $keys    = JWK::parseKeySet($keysData);
            $payload = JWT::decode($identityToken, $keys);
        } catch (\Exception $e) {
            throw new \RuntimeException('APPLE_TOKEN_INVALID');
        }

        $clientId = $_ENV['APPLE_CLIENT_ID'] ?? '';
        if ($clientId && $payload->aud !== $clientId) {
            throw new \RuntimeException('APPLE_TOKEN_INVALID');
        }

        $appleId = $payload->sub;
        $email   = $payload->email ?? null;

        return $this->findOrCreateOAuthUser([
            'provider'    => 'apple',
            'provider_id' => $appleId,
            'email'       => $email,
            'name'        => $name ?? 'Apple User',
            'avatar_url'  => null,
        ]);
    }

    private function findOrCreateOAuthUser(array $data): array
    {
        $field = $data['provider'] === 'google' ? 'google_id' : 'apple_id';

        $user = $data['provider'] === 'google'
            ? User::findByGoogleId($data['provider_id'])
            : User::findByAppleId($data['provider_id']);

        if (!$user && $data['email']) {
            $user = User::findByEmail($data['email']);
            if ($user) {
                User::update($user['id'], [$field => $data['provider_id']]);
                $user = User::find($user['id']);
            }
        }

        if (!$user) {
            $user = User::create([
                'name'       => $data['name'],
                'email'      => $data['email'],
                'role'       => 'customer',
                'avatar_url' => $data['avatar_url'],
                'is_verified'=> true,
                $field       => $data['provider_id'],
            ]);

            LoyaltyAccount::create([
                'user_id'      => $user['id'],
                'balance'      => 0,
                'total_earned' => 0,
                'tier'         => 'sprout',
            ]);
        }

        return $this->authService->issueTokensPublic($user);
    }
}
