<?php

namespace App\Models;

use Vine\Database\Model;

class Product extends Model
{
    protected static string $table = 'products';
    protected static string $alias = 'p';
    protected static array $fillable = ['farmer_id', 'category_id', 'name', 'slug', 'description', 'price', 'unit', 'stock_qty', 'image_url', 'tags', 'is_featured', 'harvested_at', 'is_active'];
    protected static array $schema = [
        'farmer_id'    => 'INT REFERENCES users(id)',
        'category_id'  => 'INT REFERENCES categories(id)',
        'name'         => 'VARCHAR(150) NOT NULL',
        'slug'         => 'VARCHAR(180) UNIQUE',
        'description'  => 'TEXT',
        'price'        => 'DECIMAL(8,2) NOT NULL',
        'unit'         => "VARCHAR(20) DEFAULT 'kg'",
        'stock_qty'    => 'INT DEFAULT 0',
        'image_url'    => 'VARCHAR(255)',
        'tags'         => 'TEXT[]',
        'is_featured'  => 'BOOLEAN DEFAULT FALSE',
        'is_active'    => 'BOOLEAN DEFAULT TRUE',
        'harvested_at' => 'DATE',
    ];
}
