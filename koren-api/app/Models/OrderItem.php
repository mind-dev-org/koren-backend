<?php

namespace App\Models;

use Vine\Database\Model;

class OrderItem extends Model
{
    protected static string $table = 'order_items';
    protected static array $fillable = ['order_id', 'product_id', 'qty', 'unit_price'];
    protected static array $schema = [
        'order_id'   => 'INT REFERENCES orders(id)',
        'product_id' => 'INT REFERENCES products(id)',
        'qty'        => 'INT NOT NULL',
        'unit_price' => 'DECIMAL(8,2) NOT NULL',
    ];
}
