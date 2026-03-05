<?php declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use App\Test;

$test = new Test();

header("Content-Type: application/json");
echo json_encode([
	"message" => "Docker is working",
	"test_class" => get_class($test),
	"uri" => $_SERVER["REQUEST_URI"],
	"method" => $_SERVER["REQUEST_METHOD"],
]);
