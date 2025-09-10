<?php
declare(strict_types=1);

namespace Elementary\Routing;

use Elementary\Http\Request;

class Router
{
    private static array $groupStack = [];

    private static function newRoute(array|string $methods, string $uri, mixed $action): Route
    {
        $route = new Route($methods, $uri, $action);
        
        if (!empty(static::$groupStack)) {
            $route->prefix(static::mergeGroupPrefixes());
            $route->middleware(static::mergeGroupMiddleware());
        }
        
        RouteCollection::getInstance()->add($route);
        
        return $route;
    }

    private static function mergeGroupPrefixes(): string
    {
        $prefixes = [];
        
        foreach (static::$groupStack as $group) {
            if (!empty($group['prefix'])) {
                $prefixes[] = trim($group['prefix'], '/');
            }
        }
        
        return implode('/', array_filter($prefixes));
    }

    private static function mergeGroupMiddleware(): array
    {
        $middleware = [];
        
        foreach (static::$groupStack as $group) {
            if (!empty($group['middleware'])) {
                $middleware = array_merge($middleware, (array) $group['middleware']);
            }
        }
        
        return $middleware;
    }

    public static function get(string $uri, mixed $action): Route
    {
        return static::newRoute(['GET', 'HEAD'], $uri, $action);
    }

    public static function post(string $uri, mixed $action): Route
    {
        return static::newRoute(['POST'], $uri, $action);
    }

    public static function put(string $uri, mixed $action): Route
    {
        return static::newRoute(['PUT'], $uri, $action);
    }

    public static function patch(string $uri, mixed $action): Route
    {
        return static::newRoute(['PATCH'], $uri, $action);
    }

    public static function delete(string $uri, mixed $action): Route
    {
        return static::newRoute(['DELETE'], $uri, $action);
    }

    public static function prefix(string $prefix): RouteRegistrar
    {
        return (new RouteRegistrar())->prefix($prefix);
    }

    public static function middleware(string|array $middleware): RouteRegistrar
    {
        return (new RouteRegistrar())->middleware($middleware);
    }

    public static function group(array $attributes, callable $callback): void
    {
        static::$groupStack[] = $attributes;
        $callback();
        array_pop(static::$groupStack);
    }

    public static function match(Request $request): ?Route
    {
        $routes = RouteCollection::getInstance()->all();
        $path = rtrim($request->pathinfo(), '/') ?: '/';

        if (empty($path)) {
            $path = '/';
        }

        foreach ($routes as $route) {
            if (!in_array($request->method(), $route->methods)) {
                continue;
            }

            $routeUri = $route->getUri();

            // Split the URI into parts to quote static segments and replace dynamic ones
            $uriParts = preg_split('/(\{[a-zA-Z0-9_]+\})/', $routeUri, -1, PREG_SPLIT_DELIM_CAPTURE);

            $pattern = '';
            foreach ($uriParts as $part) {
                if (empty($part)) continue;
                if (preg_match('/^\{([a-zA-Z0-9_]+)\}$/', $part, $matches)) {
                    // This is a dynamic parameter, add the regex pattern
                    $pattern .= '(?<' . $matches[1] . '>[^/]+)';
                } else {
                    // This is a static part, quote it
                    $pattern .= preg_quote($part, '~');
                }
            }

            $regex = '~^' . $pattern . '$~';

            if (preg_match($regex, $path, $matches)) {
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $request->attributes->set($key, $value);
                    }
                }
                return $route;
            }
        }

        return null;
    }

    public static function url(Route $route, array $params = []): string 
    {
        $uri = $route->uri;
        foreach ($params as $key => $value) {
            $uri = str_replace('{' . $key . '}', (string)$value, $uri);
        }
        return '/' . ltrim($uri, '/');
    }
}
