<?php

declare(strict_types=1);

namespace Elementary\Kernel;

use Elementary\Config\ConfigBag;
use Elementary\Database\Connection;
use Elementary\DI\Container;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Routing\Router;
use Elementary\Http\MiddlewareDispatcher;
use Elementary\Utils\FlashBag;
use Elementary\Utils\SessionBag;
use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;

class HttpKernel implements KernelInterface
{
    private Container $container;

    public function __construct()
    {
    }

    public function bootstrap(): void
    {
        $this->container = new Container();

        $this->container->bind(ConfigBag::class, fn() => new ConfigBag(BASE_PATH . '/config'));
        $config = $this->container->get(ConfigBag::class);

        if ($config->get('app.env') === 'development') {
            $whoops = new Run();
            $whoops->pushHandler(new PrettyPageHandler());
            $whoops->register();
        }

        $this->container->bind(SessionBag::class, fn() => new SessionBag());
        $this->container->bind(FlashBag::class, fn(Container $c) => new FlashBag($c->get(SessionBag::class)));
        $this->container->bind(Connection::class, fn(Container $c) => new Connection($c->get(ConfigBag::class)));
        $this->container->bind(Request::class, fn() => Request::createFromGlobals());

        $container = $this->container;
        require_once BASE_PATH . '/bootstrap.php';

        require_once BASE_PATH . '/routes/web.php';
    }

    public function handle(): Response
    {
        if (!isset($this->container)) {
            $this->bootstrap();
        }
        $request = $this->container->get(Request::class);
        try {
            $route = Router::match($request);

            if ($route === null) {
                throw new \Exception('Page not found', 404);
            }

            $controller = $route->getAction();
            $middlewareAliases = $route->middleware;

            $resolvedMiddleware = $this->resolveMiddleware($middlewareAliases);

            $controllerHandler = function (Request $request) use ($controller) {
                return $this->dispatchController($controller, $request);
            };

            $dispatcher = new MiddlewareDispatcher(
                $resolvedMiddleware,
                $controllerHandler,
                $this->container
            );

            return $dispatcher->dispatch($request);

        } catch (\Throwable $e) {
            if ($this->container->get(ConfigBag::class)->get('app.env') === 'development') {
                throw $e;
            }
            if ($e->getCode() === 404) {
                return new Response(404, 'Page not found.');
            }
            return new Response(500, 'An internal server error occurred.');
        }
    }

    private function dispatchController(callable|string $controller, Request $request): Response
    {
        if (is_string($controller)) {
            [$className, $methodName] = explode('@', $controller);
            $object = $this->container->get($className);
            if (!method_exists($object, $methodName)) {
                return new Response(500, "Method {$methodName} not found in {$className}");
            }
            return $object->{$methodName}($request);
        }
        return $controller($request);
    }

    private function resolveMiddleware(array $aliases): array
    {
        $resolved = [];
        $middlewareConfig = $this->container->get(ConfigBag::class)->get('middleware', []);
        $middlewareAliases = $middlewareConfig['aliases'] ?? [];
        $middlewareGroups = $middlewareConfig['groups'] ?? [];

        foreach ($aliases as $alias) {
            if (isset($middlewareGroups[$alias])) {
                // It's a group, recursively resolve the middleware inside it.
                $resolved = array_merge($resolved, $this->resolveMiddleware($middlewareGroups[$alias]));
            } elseif (isset($middlewareAliases[$alias])) {
                // It's an alias for a single middleware
                $resolved[] = $middlewareAliases[$alias];
            } elseif (class_exists($alias)) {
                // It's a direct FQCN
                $resolved[] = $alias;
            }
        }
        return array_unique($resolved);
    }

    public function handleCli(array $argv): int
    {
        throw new \BadMethodCallException("handleCli not implemented for HttpKernel");
    }

    public function terminate(Response $response): void
    {
    }

    public function terminateCli(int $statusCode): void
    {
        throw new \BadMethodCallException("terminateCli not implemented for HttpKernel");
    }
}

