<?php

namespace App\Models;

use Vine\Database\Model;

class User extends Model
{
	protected static string $table = "users";
	protected static array $fillable = [
		"name",
		"email",
		"phone",
		"password_hash",
		"role",
		"avatar_url",
		"address_city",
		"address_street",
		"address_lat",
		"address_lng",
		"google_id",
		"apple_id",
		"is_verified",
	];
	protected static array $hidden = ["password_hash"];

	protected static array $schema = [
		"name" => "VARCHAR(100) NOT NULL",
		"email" => "VARCHAR(150) UNIQUE",
		"phone" => "VARCHAR(20)",
		"password_hash" => "VARCHAR(255)",
		"role" => "VARCHAR(20) DEFAULT 'customer'",
		"avatar_url" => "VARCHAR(255)",
		"address_city" => "VARCHAR(100)",
		"address_street" => "TEXT",
		"address_lat" => "DECIMAL(10,7)",
		"address_lng" => "DECIMAL(10,7)",
		"is_verified" => "BOOLEAN DEFAULT FALSE",
		"google_id" => "VARCHAR(100) UNIQUE",
		"apple_id" => "VARCHAR(100) UNIQUE",
	];

	public static function findByEmail(string $email): ?array
	{
		return static::query()->where("email", "=", $email)->first();
	}

	public static function findByGoogleId(string $googleId): ?array
	{
		return static::query()->where("google_id", "=", $googleId)->first();
	}

	public static function findByAppleId(string $appleId): ?array
	{
		return static::query()->where("apple_id", "=", $appleId)->first();
	}
}
