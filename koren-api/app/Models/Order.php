<?php

namespace App\Models;

use Vine\Database\Model;

class Order extends Model
{
    protected static string $table = 'orders';
    protected static array $fillable = ['user_id', 'buyer_name', 'buyer_phone', 'buyer_email', 'delivery_slot_id', 'delivery_address', 'delivery_lat', 'delivery_lng', 'subtotal', 'discount', 'total', 'status', 'tracking_token', 'redeem_points', 'subscription_id', 'note', 'courier_lat', 'courier_lng', 'courier_updated_at'];
    protected static array $schema = [
        'user_id'            => 'INT REFERENCES users(id)',
        'buyer_name'         => 'VARCHAR(100)',
        'buyer_phone'        => 'VARCHAR(20)',
        'buyer_email'        => 'VARCHAR(150)',
        'delivery_slot_id'   => 'INT',
        'delivery_address'   => 'TEXT',
        'delivery_lat'       => 'DECIMAL(10,7)',
        'delivery_lng'       => 'DECIMAL(10,7)',
        'subtotal'           => 'DECIMAL(10,2)',
        'discount'           => 'DECIMAL(10,2) DEFAULT 0',
        'total'              => 'DECIMAL(10,2)',
        'status'             => "VARCHAR(30) DEFAULT 'pending'",
        'tracking_token'     => 'VARCHAR(64) UNIQUE',
        'redeem_points'      => 'INT DEFAULT 0',
        'subscription_id'    => 'INT',
        'note'               => 'TEXT',
        'courier_lat'        => 'DECIMAL(10,7)',
        'courier_lng'        => 'DECIMAL(10,7)',
        'courier_updated_at' => 'TIMESTAMPTZ',
    ];
}
