<?php

namespace App\Models;

use Vine\Database\Model;

class Category extends Model
{
    protected static string $table = 'categories';
    protected static array $fillable = ['slug', 'name'];
    protected static array $schema = [
        'slug' => 'VARCHAR(50) UNIQUE NOT NULL',
        'name' => 'VARCHAR(100) NOT NULL',
    ];
}
