<?php

namespace Vine\Core;

class Router
{
    private array $routes = [];
    private array $groupMiddlewares = [];
    private string $groupPrefix = '';
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function get(string $path, array|callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, array|callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, array|callable $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, array|callable $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, array|callable $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    public function group(array $options, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddlewares = $this->groupMiddlewares;

        $this->groupPrefix .= $options['prefix'] ?? '';
        if (isset($options['middleware'])) {
            $mws = is_array($options['middleware']) ? $options['middleware'] : [$options['middleware']];
            $this->groupMiddlewares = array_merge($this->groupMiddlewares, $mws);
        }

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddlewares = $previousMiddlewares;
    }

    private function addRoute(string $method, string $path, array|callable $handler): void
    {
        $fullPath = $this->groupPrefix . $path;
        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'handler' => $handler,
            'middlewares' => $this->groupMiddlewares,
            'pattern' => $this->buildPattern($fullPath),
        ];
    }

    private function buildPattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(Request $request): Response
    {
        if ($request->method === 'OPTIONS') {
            return Response::make()->status(204);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }

            if (!preg_match($route['pattern'], $request->path, $matches)) {
                continue;
            }

            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $request->params[$key] = $value;
                }
            }

            $handler = function(Request $req) use ($route) {
                return $this->callHandler($route['handler'], $req);
            };

            $middlewares = array_reverse($route['middlewares']);
            foreach ($middlewares as $mwClass) {
                $mw = $this->container->make($mwClass);
                $next = $handler;
                $handler = function(Request $req) use ($mw, $next) {
                    return $mw->handle($req, $next);
                };
            }

            return $handler($request);
        }

        return Response::notFound('Route not found');
    }

    private function callHandler(array|callable $handler, Request $request): Response
    {
        if (is_callable($handler)) {
            return $handler($request);
        }

        [$controllerClass, $method] = $handler;
        $controller = $this->container->make($controllerClass);
        return $controller->$method($request);
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
