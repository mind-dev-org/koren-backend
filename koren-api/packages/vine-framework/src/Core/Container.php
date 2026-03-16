<?php

namespace Vine\Core;

class Container
{
    private array $bindings = [];
    private array $singletons = [];
    private array $instances = [];

    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    public function singleton(string $abstract, callable $factory): void
    {
        $this->singletons[$abstract] = $factory;
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function make(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->singletons[$abstract])) {
            if (!isset($this->instances[$abstract])) {
                $this->instances[$abstract] = ($this->singletons[$abstract])($this);
            }
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            return ($this->bindings[$abstract])($this);
        }

        if (class_exists($abstract)) {
            return new $abstract();
        }

        throw new \Exception("Cannot resolve: $abstract");
    }

    public function get(string $abstract): mixed
    {
        return $this->make($abstract);
    }
}
