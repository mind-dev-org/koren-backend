<?php

namespace App\Models;

use Vine\Database\Model;

class FarmerProfile extends Model
{
	protected static string $table = "farmer_profiles";
	protected static string $primaryKey = "user_id";
	protected static array $fillable = [
		"user_id",
		"farm_name",
		"region",
		"bio",
		"bio_short",
		"years_exp",
		"farm_types",
		"rating",
		"reviews_count",
	];
	protected static array $schema = [
		"user_id" => "INT REFERENCES users(id)",
		"farm_name" => "VARCHAR(150)",
		"region" => "VARCHAR(100)",
		"bio" => "TEXT",
		"bio_short" => "VARCHAR(280)",
		"years_exp" => "INT",
		"farm_types" => "TEXT[]",
		"rating" => "DECIMAL(3,2) DEFAULT 0",
		"reviews_count" => "INT DEFAULT 0",
	];

	public static function findByUserId(int $userId): ?array
	{
		return static::query()->where("user_id", "=", $userId)->first();
	}
}
