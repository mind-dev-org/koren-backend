<?php

namespace App\Models;

use Vine\Database\Model;

class LoyaltyAccount extends Model
{
    protected static string $table = 'loyalty_accounts';
    protected static array $fillable = ['user_id', 'balance', 'total_earned', 'tier'];
    protected static array $schema = [
        'user_id'      => 'INT UNIQUE REFERENCES users(id)',
        'balance'      => 'INT DEFAULT 0',
        'total_earned' => 'INT DEFAULT 0',
        'tier'         => "VARCHAR(20) DEFAULT 'sprout'",
    ];

    public static function findByUser(int $userId): ?array
    {
        return static::query()->where('user_id', '=', $userId)->first();
    }
}
