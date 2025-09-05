<?php

declare(strict_types=1);

namespace Elementary\Routing;

class Route
{
    public string $uri;
    public array $methods;
    public mixed $action;
    public array $middleware = [];
    public ?string $name = null;
    public ?string $prefix = null;

    public function __construct(array|string $methods, string $uri, mixed $action)
    {
        $this->methods = (array) $methods;
        $this->uri = $uri;
        $this->action = $action;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        // Re-register with the collection to update the named route list
        RouteCollection::getInstance()->add($this);
        return $this;
    }

    public function middleware(string|array $middleware): self
    {
        $this->middleware = array_unique(array_merge($this->middleware, (array) $middleware));
        return $this;
    }

    public function prefix(string $prefix): self
    {
        $this->prefix = trim($prefix, '/');
        return $this;
    }

    public function getUri(): string
    {
        $uri = ($this->uri === '/' && !empty($this->prefix)) ? '' : $this->uri;
        $prefix = trim($this->prefix ?? '', '/');
        $path = trim($prefix . '/' . trim($uri, '/'), '/');
        return '/' . $path;
    }

    public function getAction(): mixed
    {
        return is_array($this->action) && isset($this->action['uses'])
            ? $this->action['uses']
            : $this->action;
    }
}
