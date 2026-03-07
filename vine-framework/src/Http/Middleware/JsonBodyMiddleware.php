<?php

namespace Vine\Http\Middleware;

use Vine\Core\Request;
use Vine\Core\Response;

class JsonBodyMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $contentType = $request->header('content-type', '');

        if (!str_contains($contentType, 'application/json') && in_array($request->method, ['POST', 'PUT', 'PATCH'])) {
            return Response::error('BAD_REQUEST', 'Content-Type must be application/json', 400);
        }

        return $next($request);
    }
}
