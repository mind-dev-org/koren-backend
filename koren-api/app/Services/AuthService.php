<?php

namespace App\Services;

use App\Models\User;
use App\Models\RefreshToken;
use App\Models\LoyaltyAccount;
use App\Models\FarmerProfile;
use App\Models\PasswordResetToken;
use Vine\Auth\JwtHelper;

class AuthService
{
    private JwtHelper $jwt;

    public function __construct()
    {
        $this->jwt = new JwtHelper();
    }

    public function register(array $data): array
    {
        $existing = User::findByEmail($data['email']);
        if ($existing) {
            throw new \RuntimeException('EMAIL_ALREADY_EXISTS');
        }

        $user = User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'phone'         => $data['phone'] ?? null,
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'          => 'customer',
        ]);

        LoyaltyAccount::create([
            'user_id'      => $user['id'],
            'balance'      => 0,
            'total_earned' => 0,
            'tier'         => 'sprout',
        ]);

        return $this->issueTokens($user);
    }

    public function registerFarmer(array $data): array
    {
        $existing = User::findByEmail($data['email']);
        if ($existing) {
            throw new \RuntimeException('EMAIL_ALREADY_EXISTS');
        }

        $user = User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'phone'         => $data['phone'] ?? null,
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'          => 'farmer',
            'is_verified'   => false,
        ]);

        FarmerProfile::create([
            'user_id'    => $user['id'],
            'farm_name'  => $data['farm_name'] ?? null,
            'region'     => $data['region'] ?? null,
            'bio'        => $data['bio'] ?? null,
            'years_exp'  => $data['years_exp'] ?? null,
            'farm_types' => isset($data['farm_types']) ? '{' . implode(',', $data['farm_types']) . '}' : null,
        ]);

        return $this->issueTokens($user);
    }

    public function login(string $email, string $password): array
    {
        $user = User::findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new \RuntimeException('INVALID_CREDENTIALS');
        }

        return $this->issueTokens($user);
    }

    public function refresh(string $rawToken): array
    {
        $hash   = hash('sha256', $rawToken);
        $stored = RefreshToken::findValid($hash);

        if (!$stored || strtotime($stored['expires_at']) < time()) {
            throw new \RuntimeException('REFRESH_TOKEN_INVALID');
        }

        $user = User::findOrFail($stored['user_id']);
        RefreshToken::delete($stored['id']);

        return $this->issueTokens($user);
    }

    public function logout(string $rawToken): void
    {
        $hash = hash('sha256', $rawToken);
        RefreshToken::query()->where('token_hash', '=', $hash)->delete();
    }

    public function forgotPassword(string $email): void
    {
        $user = User::findByEmail($email);
        if (!$user) {
            return;
        }

        PasswordResetToken::query()->where('email', '=', $email)->delete();

        $rawToken = bin2hex(random_bytes(32));
        $hash     = hash('sha256', $rawToken);

        PasswordResetToken::create([
            'email'      => $email,
            'token_hash' => $hash,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);
    }

    public function resetPassword(string $rawToken, string $newPassword): void
    {
        $hash   = hash('sha256', $rawToken);
        $record = PasswordResetToken::findValidByHash($hash);

        if (!$record || strtotime($record['expires_at']) < time()) {
            throw new \RuntimeException('TOKEN_INVALID');
        }

        $user = User::findByEmail($record['email']);
        if (!$user) {
            throw new \RuntimeException('TOKEN_INVALID');
        }

        User::update($user['id'], ['password_hash' => password_hash($newPassword, PASSWORD_BCRYPT)]);
        PasswordResetToken::query()->where('token_hash', '=', $hash)->delete();
    }

    private function issueTokens(array $user): array
    {
        $payload = [
            'sub'   => $user['id'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];

        $accessToken  = $this->jwt->encode($payload);
        $rawRefresh   = $this->jwt->generateRefreshToken();
        $refreshHash  = hash('sha256', $rawRefresh);

        RefreshToken::create([
            'user_id'    => $user['id'],
            'token_hash' => $refreshHash,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        $clean   = User::hide($user);
        $loyalty = LoyaltyAccount::findByUser($user['id']);

        $clean['points_balance'] = $loyalty ? (int) $loyalty['balance'] : 0;
        $clean['loyalty_tier']   = $loyalty ? $loyalty['tier'] : 'sprout';

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $rawRefresh,
            'token_type'    => 'Bearer',
            'expires_in'    => (int) ($_ENV['JWT_TTL'] ?? 900),
            'user'          => $clean,
        ];
    }
}
