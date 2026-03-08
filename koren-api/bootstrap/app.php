<?php

use Vine\Core\App;
use Vine\Database\Connection;
use Vine\Auth\JwtHelper;
use Vine\Http\Middleware\CorsMiddleware;
use Vine\Http\Middleware\RateLimitMiddleware;
use Vine\Http\Middleware\JsonBodyMiddleware;

$dotenv = __DIR__ . '/../.env';
if (file_exists($dotenv)) {
    foreach (file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($val);
        }
    }
}

$app = App::getInstance();

$app->singleton(Connection::class, fn() => Connection::getInstance());
$app->singleton(JwtHelper::class, fn() => new JwtHelper());

$app->use(CorsMiddleware::class);
$app->use(RateLimitMiddleware::class);
$app->use(JsonBodyMiddleware::class);

return $app;
