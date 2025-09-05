<?php

declare(strict_types=1);

namespace Elementary\Http;

use Elementary\DI\Container;
use Elementary\Http\Middleware\MiddlewareInterface;

/**
 * Dispatches a request through a stack of middleware.
 */
final class MiddlewareDispatcher
{
    private int $index = 0;

    /**
     * @param array $middleware The middleware stack.
     * @param callable $handler The final handler (controller action).
     * @param Container $container The DI container to resolve middleware.
     */
    public function __construct(
        private array $middleware,
        private $handler,
        private Container $container
    ) {}

    /**
     * Dispatch the request through the middleware stack.
     */
    public function dispatch(Request $request): Response
    {
        if (isset($this->middleware[$this->index])) {
            $middlewareClass = $this->middleware[$this->index];
            /** @var MiddlewareInterface $middlewareInstance */
            $middlewareInstance = $this->container->get($middlewareClass);
            $this->index++;
            return $middlewareInstance->process($request, [$this, 'dispatch']);
        }

        return call_user_func($this->handler, $request);
    }
}
