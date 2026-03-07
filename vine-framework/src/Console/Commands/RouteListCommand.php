<?php

namespace Vine\Console\Commands;

use Vine\Core\App;

class RouteListCommand
{
    public function handle(array $args): void
    {
        $app = App::getInstance();
        $routes = $app->getRouter()->getRoutes();

        echo "\nRegistered Routes:\n\n";
        echo str_pad('Method', 10) . str_pad('Path', 50) . "Middlewares\n";
        echo str_repeat('-', 80) . "\n";

        foreach ($routes as $route) {
            $method = str_pad($route['method'], 10);
            $path = str_pad($route['path'], 50);
            $mws = implode(', ', array_map(fn($m) => class_basename($m), $route['middlewares']));
            echo "$method$path$mws\n";
        }

        echo "\nTotal: " . count($routes) . " route(s)\n\n";
    }
}
