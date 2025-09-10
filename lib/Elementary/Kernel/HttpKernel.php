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
use Psr\Container\ContainerInterface;
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

        if (str_starts_with($config->get('app.env', 'dev'), 'dev')) {
            $whoops = new Run();
            $whoops->pushHandler(new PrettyPageHandler());
            $whoops->register();
        }

        $request = Request::createFromGlobals();

        $this->container->bind(SessionBag::class, fn() => new SessionBag());
        $this->container->bind(FlashBag::class, fn(Container $c) => new FlashBag($c->get(SessionBag::class)));
        $this->container->bind(Connection::class, fn(Container $c) => new Connection($c->get(ConfigBag::class)));
        $this->container->bind(Request::class, fn() => $request);
        $this->container->bind(ContainerInterface::class, fn() => $this->container);

        $container = $this->container;
        require_once BASE_PATH . '/bootstrap.php';

        Router::middleware('web')->group(function () {
            require_once BASE_PATH . '/routes/web.php';
        });
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
            $config = $this->container->get(ConfigBag::class);

            if (str_starts_with( $config->get('app.env', 'dev') , 'dev')) {
                throw $e;
            }

            try {
                /** @var \Elementary\Template\Cigg\Engine $engine */
                $engine = $this->container->get(\Elementary\Template\Cigg\Engine::class);

                if ($e->getCode() === 404) {
                    $content = $engine->render('errors/404');
                    return new Response(404, $content);
                }

                $content = $engine->render('errors/500');
                return new Response(500, $content);
            } catch (\Throwable $renderingException) {
                // Fallback if the template rendering itself fails
                if ($e->getCode() === 404) {
                    return new Response(404, 'Page not found.');
                }
                return new Response(500, 'An internal server error occurred.');
            }
        }
    }

    private function dispatchController(callable|string $controller, Request $request): Response
    {
        $action = null;
        if (is_string($controller)) {
            [$className, $methodName] = explode('@', $controller);
            $object = $this->container->get($className);
            if (!method_exists($object, $methodName)) {
                return new Response(500, "Method {$methodName} not found in {$className}");
            }
            $action =  [$object, $methodName];
        } else {
            $action = $controller;
        }

        $reflection = new \ReflectionFunction(\Closure::fromCallable($action));
        $parameters = $reflection->getParameters();
        $args = [];
        foreach ($parameters as $parameter) {
            $paramType = $parameter->getType();
            if ($paramType && !$paramType->isBuiltin()) {
                $paramClass = $paramType->getName();
                if ($paramClass === Request::class) {
                    $args[] = $request;
                } else {
                    $args[] = $this->container->get($paramClass);
                }
            } elseif ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
            } else {
                // Cannot resolve the parameter
                throw new \RuntimeException("Cannot resolve parameter \${$parameter->getName()}");
            }
        }
        return $action(...$args);
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

