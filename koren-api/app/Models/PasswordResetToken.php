<?php

namespace App\Models;

use Vine\Database\Model;

class PasswordResetToken extends Model
{
    protected static string $table = 'password_reset_tokens';
    protected static array $fillable = ['email', 'token_hash', 'expires_at'];
    protected static array $schema = [
        'email'      => 'VARCHAR(150) NOT NULL',
        'token_hash' => 'VARCHAR(255) UNIQUE NOT NULL',
        'expires_at' => 'TIMESTAMPTZ NOT NULL',
    ];

    public static function findValidByHash(string $hash): ?array
    {
        return static::query()->where('token_hash', '=', $hash)->first();
    }
}
