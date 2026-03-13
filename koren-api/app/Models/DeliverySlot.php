<?php

namespace App\Models;

use Vine\Database\Model;

class DeliverySlot extends Model
{
    protected static string $table = 'delivery_slots';
    protected static array $fillable = ['date', 'city', 'time_range', 'capacity_total', 'capacity_used', 'price', 'is_eco'];
    protected static array $schema = [
        'date'           => 'DATE NOT NULL',
        'city'           => 'VARCHAR(100) NOT NULL',
        'time_range'     => 'VARCHAR(20) NOT NULL',
        'capacity_total' => 'INT DEFAULT 10',
        'capacity_used'  => 'INT DEFAULT 0',
        'price'          => 'DECIMAL(6,2) DEFAULT 0',
        'is_eco'         => 'BOOLEAN DEFAULT FALSE',
    ];
}
