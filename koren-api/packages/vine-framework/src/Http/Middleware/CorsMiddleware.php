<?php

namespace Vine\Http\Middleware;

use Vine\Core\Request;
use Vine\Core\Response;

class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins;

    public function __construct()
    {
        $origins = $_ENV['CORS_ORIGINS'] ?? '*';
        $this->allowedOrigins = $origins === '*' ? ['*'] : explode(',', $origins);
    }

    public function handle(Request $request, callable $next): Response
    {
        $origin = $request->header('origin', '');

        $response = $next($request);

        if (in_array('*', $this->allowedOrigins)) {
            $response->header('Access-Control-Allow-Origin', '*');
        } elseif (in_array($origin, $this->allowedOrigins)) {
            $response->header('Access-Control-Allow-Origin', $origin);
            $response->header('Vary', 'Origin');
        }

        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->header('Access-Control-Max-Age', '86400');

        return $response;
    }
}
