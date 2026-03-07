<?php

namespace Vine\Http\Middleware;

use Vine\Core\Request;
use Vine\Core\Response;

class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $windowSeconds;
    private string $storageDir;

    public function __construct(int $maxRequests = 60, int $windowSeconds = 60)
    {
        $this->maxRequests = (int) ($_ENV['RATE_LIMIT'] ?? $maxRequests);
        $this->windowSeconds = $windowSeconds;
        $this->storageDir = sys_get_temp_dir() . '/vine_ratelimit';

        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0777, true);
        }
    }

    public function handle(Request $request, callable $next): Response
    {
        $ip = $request->ip();
        $key = md5($ip);
        $file = $this->storageDir . '/' . $key . '.json';

        $now = time();
        $data = ['count' => 0, 'window_start' => $now];

        if (file_exists($file)) {
            $stored = json_decode(file_get_contents($file), true);
            if ($stored && ($now - $stored['window_start']) < $this->windowSeconds) {
                $data = $stored;
            }
        }

        $data['count']++;
        file_put_contents($file, json_encode($data));

        if ($data['count'] > $this->maxRequests) {
            $retryAfter = $this->windowSeconds - ($now - $data['window_start']);
            return Response::error('RATE_LIMIT_EXCEEDED', 'Too many requests', 429)
                ->header('Retry-After', (string) $retryAfter)
                ->header('X-RateLimit-Limit', (string) $this->maxRequests)
                ->header('X-RateLimit-Remaining', '0');
        }

        $response = $next($request);
        $response->header('X-RateLimit-Limit', (string) $this->maxRequests);
        $response->header('X-RateLimit-Remaining', (string) max(0, $this->maxRequests - $data['count']));

        return $response;
    }
}
