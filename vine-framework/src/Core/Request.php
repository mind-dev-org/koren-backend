<?php

namespace Vine\Core;

class Request
{
	public readonly string $method;
	public readonly string $uri;
	public readonly string $path;
	public array $params = [];
	private array $body = [];
	private array $queryParams = [];
	private array $headers = [];
	private ?array $user = null;

	public function __construct()
	{
		$this->method = strtoupper($_SERVER["REQUEST_METHOD"] ?? "GET");
		$this->uri = $_SERVER["REQUEST_URI"] ?? "/";
		$this->path = strtok($this->uri, "?");
		$this->queryParams = $_GET;

		$rawBody = file_get_contents("php://input");
		if ($rawBody) {
			$decoded = json_decode($rawBody, true);
			$this->body = $decoded ?? [];
		}

		foreach ($_SERVER as $key => $value) {
			if (str_starts_with($key, "HTTP_")) {
				$name = strtolower(str_replace("_", "-", substr($key, 5)));
				$this->headers[$name] = $value;
			}
		}
	}

	public function input(string $key, mixed $default = null): mixed
	{
		return $this->body[$key] ?? $default;
	}

	public function query(?string $key = null, mixed $default = null): mixed
	{
		if ($key === null) {
			return $this->queryParams;
		}
		return $this->queryParams[$key] ?? $default;
	}

	public function all(): array
	{
		return array_merge($this->queryParams, $this->body);
	}

	public function only(array $keys): array
	{
		$all = $this->all();
		$result = [];
		foreach ($keys as $key) {
			if (array_key_exists($key, $all)) {
				$result[$key] = $all[$key];
			}
		}
		return $result;
	}

	public function header(string $key, mixed $default = null): mixed
	{
		return $this->headers[strtolower($key)] ?? $default;
	}

	public function bearerToken(): ?string
	{
		$auth = $this->header("authorization", "");
		if (str_starts_with($auth, "Bearer ")) {
			return substr($auth, 7);
		}
		return null;
	}

	public function ip(): string
	{
		return $_SERVER["HTTP_X_FORWARDED_FOR"] ??
			($_SERVER["REMOTE_ADDR"] ?? "127.0.0.1");
	}

	public function setUser(array $user): void
	{
		$this->user = $user;
	}

	public function user(): ?array
	{
		return $this->user;
	}

	public function isMethod(string $method): bool
	{
		return $this->method === strtoupper($method);
	}
}
