<?php

declare(strict_types=1);

namespace Elementary\Routing;

class RouteRegistrar
{
    protected array $attributes = [];

    public function prefix(string $prefix): self
    {
        $this->attributes['prefix'] = $prefix;
        return $this;
    }

    public function middleware(string|array $middleware): self
    {
        $this->attributes['middleware'] = array_merge(
            $this->attributes['middleware'] ?? [],
            (array) $middleware
        );
        return $this;
    }

    public function group(callable $callback): void
    {
        Router::group($this->attributes, $callback);
    }
}
