<?php
declare(strict_types=1);
namespace Elementary\Http;
use Elementary\DI\Container;
use Elementary\Http\Request;
use Elementary\Http\Response;

class Router 
{
    private array $routes = [
        'GET' => [],
        'POST' => []
    ];

    public function __construct(private Container $container)
    {
    }

    public function get(string $path, callable|string $controller): self
    {
        $this->routes['GET'][$path] = $controller;
        return $this;
    }

    public function post(string $path, callable|string $controller): self
    {
        $this->routes['POST'][$path] = $controller;
        return $this;
    }

    public function handle(Request $request): Response
    {
        $method = $request->method();
        $path = rtrim($request->pathinfo(), '/') ?: '/';

        foreach ($this->routes[$method] as $routePath => $controller) {
            // Fixed: added missing closing delimiter
            $regex = '~^' . preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?<$1>[^/]+)', $routePath) . '$~';

            if (preg_match($regex, $path, $matches)) {
                // Add named capture groups to request attributes
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $request->attributes->set($key, $value);
                    }
                }
                return $this->dispatch($controller, $request);
            }
        }

        return new Response(404, "Not found!");
    }

    private function dispatch(callable|string $controller, Request $request): Response
    {
        if (is_string($controller)) {
            [$className, $methodName] = explode('@', $controller);

            $object = $this->container->get($className);

            // Check if method exists before calling it
            if (!method_exists($object, $methodName)) {
                return new Response(500, "Method {$methodName} not found in {$className}");
            }

            return $object->{$methodName}($request);
        }

        return $controller($request);
    }
}
