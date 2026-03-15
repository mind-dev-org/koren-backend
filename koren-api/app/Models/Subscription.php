<?php

namespace App\Models;

use Vine\Database\Model;

class Subscription extends Model
{
    protected static string $table = 'subscriptions';
    protected static array $fillable = ['user_id', 'status', 'frequency', 'day_of_week', 'day_of_month', 'delivery_slot', 'delivery_address', 'delivery_lat', 'delivery_lng', 'next_delivery_at', 'pause_until', 'note'];
    protected static array $schema = [
        'user_id'          => 'INT REFERENCES users(id)',
        'status'           => "VARCHAR(20) DEFAULT 'active'",
        'frequency'        => 'VARCHAR(20) NOT NULL',
        'day_of_week'      => 'INT',
        'day_of_month'     => 'INT',
        'delivery_slot'    => 'VARCHAR(20)',
        'delivery_address' => 'TEXT',
        'delivery_lat'     => 'DECIMAL(10,7)',
        'delivery_lng'     => 'DECIMAL(10,7)',
        'next_delivery_at' => 'TIMESTAMPTZ',
        'pause_until'      => 'DATE',
        'note'             => 'TEXT',
    ];
}
