<?php

namespace App\Models;

use Vine\Database\Model;

class User extends Model
{
    protected static string $table = 'users';
    protected static array $fillable = ['name', 'email', 'phone', 'password_hash', 'role', 'avatar_url', 'address_city', 'address_street', 'address_lat', 'address_lng'];
    protected static array $hidden = ['password_hash'];

    protected static array $schema = [
        'name'           => 'VARCHAR(100) NOT NULL',
        'email'          => 'VARCHAR(150) UNIQUE NOT NULL',
        'phone'          => 'VARCHAR(20)',
        'password_hash'  => 'VARCHAR(255) NOT NULL',
        'role'           => "VARCHAR(20) DEFAULT 'customer'",
        'avatar_url'     => 'VARCHAR(255)',
        'address_city'   => 'VARCHAR(100)',
        'address_street' => 'TEXT',
        'address_lat'    => 'DECIMAL(10,7)',
        'address_lng'    => 'DECIMAL(10,7)',
        'is_verified'    => 'BOOLEAN DEFAULT FALSE',
    ];

    public static function findByEmail(string $email): ?array
    {
        return static::query()->where('email', '=', $email)->first();
    }
}
