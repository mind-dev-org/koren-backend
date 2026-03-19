<?php

namespace App\Models;

use Vine\Database\Model;

class Payment extends Model
{
    protected static string $table = 'payments';
    protected static array $fillable = [
        'order_id', 'user_id', 'stripe_payment_intent_id',
        'stripe_client_secret', 'amount', 'currency', 'status',
    ];
    protected static array $schema = [
        'order_id'                   => 'INT REFERENCES orders(id)',
        'user_id'                    => 'INT REFERENCES users(id)',
        'stripe_payment_intent_id'   => 'VARCHAR(100) UNIQUE',
        'stripe_client_secret'       => 'VARCHAR(255)',
        'amount'                     => 'DECIMAL(10,2) NOT NULL',
        'currency'                   => "VARCHAR(3) DEFAULT 'eur'",
        'status'                     => "VARCHAR(30) DEFAULT 'pending'",
    ];

    public static function findByIntent(string $intentId): ?array
    {
        return static::query()->where('stripe_payment_intent_id', '=', $intentId)->first();
    }

    public static function findByOrder(int $orderId): ?array
    {
        return static::query()->where('order_id', '=', $orderId)->first();
    }
}
