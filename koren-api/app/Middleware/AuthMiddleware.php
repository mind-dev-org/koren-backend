<?php

namespace App\Middleware;

use Vine\Core\Request;
use Vine\Core\Response;
use Vine\Auth\JwtHelper;
use Vine\Http\Middleware\MiddlewareInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return Response::unauthorized('Bearer token is required');
        }

        $jwt     = new JwtHelper();
        $payload = $jwt->decode($token);

        if (!$payload) {
            return Response::error('TOKEN_INVALID', 'Token is invalid or expired', 401);
        }

        $request->setUser($payload);

        return $next($request);
    }
}
