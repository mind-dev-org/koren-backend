<?php

namespace Vine\Core;

class Response
{
    private int $status = 200;
    private array $headers = ['Content-Type' => 'application/json'];
    private mixed $body = null;

    public function status(int $code): static
    {
        $this->status = $code;
        return $this;
    }

    public function header(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function withHeader(string $key, string $value): static
    {
        return $this->header($key, $value);
    }

    public function json(mixed $data, int $status = 200): static
    {
        $this->status = $status;
        $this->body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }
        if ($this->body !== null) {
            echo $this->body;
        }
    }

    public static function make(): static
    {
        return new static();
    }

    public static function success(mixed $data, int $status = 200): static
    {
        return (new static())->json(['data' => $data], $status);
    }

    public static function collection(array $data, array $meta = []): static
    {
        $payload = ['data' => $data];
        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }
        return (new static())->json($payload);
    }

    public static function error(string $code, string $message, int $status = 400, array $details = []): static
    {
        $payload = ['error' => $code, 'message' => $message];
        if (!empty($details)) {
            $payload['details'] = $details;
        }
        return (new static())->json($payload, $status);
    }

    public static function notFound(string $message = 'Not found'): static
    {
        return static::error('NOT_FOUND', $message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): static
    {
        return static::error('UNAUTHORIZED', $message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): static
    {
        return static::error('FORBIDDEN', $message, 403);
    }
}
