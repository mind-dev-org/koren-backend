<?php

namespace App\Models;

use Vine\Database\Model;

class SubscriptionItem extends Model
{
    protected static string $table = 'subscription_items';
    protected static array $fillable = ['subscription_id', 'product_id', 'qty'];
    protected static array $schema = [
        'subscription_id' => 'INT REFERENCES subscriptions(id)',
        'product_id'      => 'INT REFERENCES products(id)',
        'qty'             => 'INT NOT NULL',
    ];
}
