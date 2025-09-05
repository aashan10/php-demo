<?php

declare(strict_types=1);

namespace Elementary\Routing;

class RouteCollection
{
    private static ?self $instance = null;
    /** @var Route[] */
    private array $routes = [];
    /** @var Route[] */
    private array $namedRoutes = [];

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public static function getInstance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function add(Route $route): void
    {
        $this->routes[] = $route;
        if ($route->name) {
            $this->namedRoutes[$route->name] = $route;
        }
    }

    /**
     * @return Route[]
     */
    public function all(): array
    {
        return $this->routes;
    }
}
