<?php

namespace App\Models;

use Vine\Database\Model;

class LoyaltyTransaction extends Model
{
    protected static string $table = 'loyalty_transactions';
    protected static array $fillable = ['user_id', 'order_id', 'type', 'amount', 'description'];
    protected static array $schema = [
        'user_id'     => 'INT REFERENCES users(id)',
        'order_id'    => 'INT',
        'type'        => 'VARCHAR(20) NOT NULL',
        'amount'      => 'INT NOT NULL',
        'description' => 'TEXT',
    ];
}
