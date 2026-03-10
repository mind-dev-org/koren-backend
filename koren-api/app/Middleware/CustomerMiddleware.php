<?php

namespace App\Middleware;

use Vine\Core\Request;
use Vine\Core\Response;
use Vine\Http\Middleware\MiddlewareInterface;

class CustomerMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $authMw = new AuthMiddleware();
        return $authMw->handle($request, function($req) use ($next) {
            if ($req->user()['role'] !== 'customer') {
                return Response::forbidden('Customer access required');
            }
            return $next($req);
        });
    }
}
