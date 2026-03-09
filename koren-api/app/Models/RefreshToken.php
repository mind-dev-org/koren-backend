<?php

namespace App\Models;

use Vine\Database\Model;

class RefreshToken extends Model
{
    protected static string $table = 'refresh_tokens';
    protected static array $fillable = ['user_id', 'token_hash', 'expires_at'];
    protected static array $schema = [
        'user_id'    => 'INT REFERENCES users(id) ON DELETE CASCADE',
        'token_hash' => 'VARCHAR(255) UNIQUE NOT NULL',
        'expires_at' => 'TIMESTAMPTZ NOT NULL',
    ];

    public static function findValid(string $hash): ?array
    {
        return static::query()->where('token_hash', '=', $hash)->first();
    }
}
