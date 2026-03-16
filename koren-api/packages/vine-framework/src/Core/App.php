<?php

namespace Vine\Core;

class App
{
    private static ?App $instance = null;
    private Container $container;
    private Router $router;
    private array $globalMiddlewares = [];

    private function __construct()
    {
        $this->container = new Container();
        $this->router = new Router($this->container);
    }

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function bind(string $abstract, callable $factory): void
    {
        $this->container->bind($abstract, $factory);
    }

    public function singleton(string $abstract, callable $factory): void
    {
        $this->container->singleton($abstract, $factory);
    }

    public function use(string $middleware): void
    {
        $this->globalMiddlewares[] = $middleware;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function make(string $abstract): mixed
    {
        return $this->container->make($abstract);
    }

    public function run(): void
    {
        $request = new Request();

        $handler = function(Request $req) {
            return $this->router->dispatch($req);
        };

        $globalStack = array_reverse($this->globalMiddlewares);
        foreach ($globalStack as $mwClass) {
            $mw = $this->container->make($mwClass);
            $next = $handler;
            $handler = function(Request $req) use ($mw, $next) {
                return $mw->handle($req, $next);
            };
        }

        try {
            $response = $handler($request);
        } catch (\Throwable $e) {
            $response = Response::error(
                'INTERNAL_ERROR',
                $_ENV['APP_ENV'] === 'production' ? 'Something went wrong' : $e->getMessage(),
                500
            );
        }

        $response->send();
    }
}
