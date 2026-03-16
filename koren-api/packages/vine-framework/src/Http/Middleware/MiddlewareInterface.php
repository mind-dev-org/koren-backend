<?php

namespace Vine\Http\Middleware;

use Vine\Core\Request;
use Vine\Core\Response;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
