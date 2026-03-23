<?php

ini_set("display_errors", "0");
ini_set("log_errors", "1");
error_reporting(E_ALL);

register_shutdown_function(function () {
	$err = error_get_last();
	if (
		$err &&
		in_array($err["type"], [
			E_ERROR,
			E_PARSE,
			E_COMPILE_ERROR,
			E_CORE_ERROR,
		])
	) {
		if (!headers_sent()) {
			http_response_code(500);
			header("Content-Type: application/json");
		}
		echo json_encode([
			"error" => "INTERNAL_ERROR",
			"message" =>
				$err["message"] .
				" in " .
				basename($err["file"]) .
				":" .
				$err["line"],
		]);
	}
});

$path = strtok($_SERVER["REQUEST_URI"] ?? "/", "?");

if ($path === "/docs" || $path === "/docs/") {
	readfile(__DIR__ . "/docs/index.html");
	exit();
}
if ($path === "/docs/openapi.yaml") {
	header("Content-Type: application/yaml");
	header("Access-Control-Allow-Origin: *");
	readfile(__DIR__ . "/docs/openapi.yaml");
	exit();
}

require_once __DIR__ . "/../vendor/autoload.php";

$app = require_once __DIR__ . "/../bootstrap/app.php";

$router = $app->getRouter();

require_once __DIR__ . "/../routes/api.php";

$app->run();
