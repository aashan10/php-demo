<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Elementary\DI\Container;
use Elementary\Http\MiddlewareDispatcher;
use Elementary\Http\Middleware\MiddlewareInterface;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Utils\SessionBag;
use PHPUnit\Framework\TestCase;

class MiddlewareDispatcherTest extends TestCase
{
    private Container $container;
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->container = new Container();
        
        // Create a basic request
        $this->request = new Request(
            get: [],
            post: [],
            cookies: [],
            files: [],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            headers: [],
            request: [],
            session: new SessionBag(),
            attributes: [],
            content: null
        );
    }

    public function testDispatchWithNoMiddleware(): void
    {
        $handler = function (Request $request) {
            return new Response(200, 'Handler executed');
        };

        $dispatcher = new MiddlewareDispatcher([], $handler, $this->container);
        $response = $dispatcher->dispatch($this->request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Handler executed', $response->getContent());
    }

    public function testDispatchWithSingleMiddleware(): void
    {
        // Create a test middleware
        $testMiddleware = new class implements MiddlewareInterface {
            public function process(Request $request, callable $next): Response
            {
                $response = $next($request);
                return new Response($response->getStatusCode(), $response->getContent() . ' - Modified by middleware');
            }
        };

        $middlewareClass = get_class($testMiddleware);
        $this->container->bind($middlewareClass, fn() => $testMiddleware);

        $handler = function (Request $request) {
            return new Response(200, 'Handler executed');
        };

        $dispatcher = new MiddlewareDispatcher([$middlewareClass], $handler, $this->container);
        $response = $dispatcher->dispatch($this->request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Handler executed - Modified by middleware', $response->getContent());
    }

    public function testDispatchWithMultipleMiddleware(): void
    {
        // Create multiple test middleware
        $middleware1 = new class implements MiddlewareInterface {
            public function process(Request $request, callable $next): Response
            {
                $response = $next($request);
                return new Response($response->getStatusCode(), $response->getContent() . ' - Middleware 1');
            }
        };

        $middleware2 = new class implements MiddlewareInterface {
            public function process(Request $request, callable $next): Response
            {
                $response = $next($request);
                return new Response($response->getStatusCode(), $response->getContent() . ' - Middleware 2');
            }
        };

        $middlewareClass1 = get_class($middleware1);
        $middlewareClass2 = get_class($middleware2);
        
        $this->container->bind($middlewareClass1, fn() => $middleware1);
        $this->container->bind($middlewareClass2, fn() => $middleware2);

        $handler = function (Request $request) {
            return new Response(200, 'Handler executed');
        };

        $dispatcher = new MiddlewareDispatcher(
            [$middlewareClass1, $middlewareClass2], 
            $handler, 
            $this->container
        );
        $response = $dispatcher->dispatch($this->request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        // Middleware should execute in order: 1 -> 2 -> handler -> 2 response -> 1 response
        $this->assertEquals('Handler executed - Middleware 2 - Middleware 1', $response->getContent());
    }

    public function testMiddlewareCanModifyRequest(): void
    {
        // Create middleware that modifies the request
        $requestModifyingMiddleware = new class implements MiddlewareInterface {
            public function process(Request $request, callable $next): Response
            {
                $request->attributes->set('modified', 'by_middleware');
                return $next($request);
            }
        };

        $middlewareClass = get_class($requestModifyingMiddleware);
        $this->container->bind($middlewareClass, fn() => $requestModifyingMiddleware);

        $handler = function (Request $request) {
            $modified = $request->attributes->get('modified', 'not_modified');
            return new Response(200, "Handler executed: $modified");
        };

        $dispatcher = new MiddlewareDispatcher([$middlewareClass], $handler, $this->container);
        $response = $dispatcher->dispatch($this->request);

        $this->assertEquals('Handler executed: by_middleware', $response->getContent());
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        // Create middleware that doesn't call next
        $shortCircuitMiddleware = new class implements MiddlewareInterface {
            public function process(Request $request, callable $next): Response
            {
                // Don't call $next, return early
                return new Response(403, 'Access denied by middleware');
            }
        };

        $middlewareClass = get_class($shortCircuitMiddleware);
        $this->container->bind($middlewareClass, fn() => $shortCircuitMiddleware);

        $handler = function (Request $request) {
            return new Response(200, 'Handler executed - should not be reached');
        };

        $dispatcher = new MiddlewareDispatcher([$middlewareClass], $handler, $this->container);
        $response = $dispatcher->dispatch($this->request);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('Access denied by middleware', $response->getContent());
    }

    public function testMiddlewareExecutionOrder(): void
    {
        $executionOrder = [];

        // Create middleware that track execution order
        $middleware1 = new class($executionOrder) implements MiddlewareInterface {
            public function __construct(private array &$executionOrder) {}
            
            public function process(Request $request, callable $next): Response
            {
                $this->executionOrder[] = 'middleware1_before';
                $response = $next($request);
                $this->executionOrder[] = 'middleware1_after';
                return $response;
            }
        };

        $middleware2 = new class($executionOrder) implements MiddlewareInterface {
            public function __construct(private array &$executionOrder) {}
            
            public function process(Request $request, callable $next): Response
            {
                $this->executionOrder[] = 'middleware2_before';
                $response = $next($request);
                $this->executionOrder[] = 'middleware2_after';
                return $response;
            }
        };

        $middlewareClass1 = get_class($middleware1);
        $middlewareClass2 = get_class($middleware2);
        
        $this->container->bind($middlewareClass1, fn() => $middleware1);
        $this->container->bind($middlewareClass2, fn() => $middleware2);

        $handler = function (Request $request) use (&$executionOrder) {
            $executionOrder[] = 'handler';
            return new Response(200, 'Handler executed');
        };

        $dispatcher = new MiddlewareDispatcher(
            [$middlewareClass1, $middlewareClass2], 
            $handler, 
            $this->container
        );
        $response = $dispatcher->dispatch($this->request);

        $expectedOrder = [
            'middleware1_before',
            'middleware2_before',
            'handler',
            'middleware2_after',
            'middleware1_after'
        ];

        $this->assertEquals($expectedOrder, $executionOrder);
    }

    public function testDispatcherIndexResetsAfterDispatch(): void
    {
        $testMiddleware = new class implements MiddlewareInterface {
            public function process(Request $request, callable $next): Response
            {
                return $next($request);
            }
        };

        $middlewareClass = get_class($testMiddleware);
        $this->container->bind($middlewareClass, fn() => $testMiddleware);

        $handler = function (Request $request) {
            return new Response(200, 'Handler executed');
        };

        $dispatcher = new MiddlewareDispatcher([$middlewareClass], $handler, $this->container);

        // First dispatch
        $response1 = $dispatcher->dispatch($this->request);
        $this->assertEquals('Handler executed', $response1->getContent());

        // Second dispatch should work the same way (index should reset)
        $response2 = $dispatcher->dispatch($this->request);
        $this->assertEquals('Handler executed', $response2->getContent());
    }

    public function testDispatchWithContainerResolution(): void
    {
        // Create a middleware that requires a dependency
        $dependency = new class {
            public function getMessage(): string
            {
                return 'Message from dependency';
            }
        };

        $middlewareClassName = 'TestMiddlewareWithDependency';
        
        $this->container->bind(get_class($dependency), fn() => $dependency);
        $this->container->bind($middlewareClassName, function(Container $c) use ($dependency) {
            return new class($dependency) implements MiddlewareInterface {
                public function __construct(private object $dependency) {}
                
                public function process(Request $request, callable $next): Response
                {
                    $response = $next($request);
                    $message = $this->dependency->getMessage();
                    return new Response($response->getStatusCode(), $response->getContent() . " - $message");
                }
            };
        });

        $handler = function (Request $request) {
            return new Response(200, 'Handler executed');
        };

        $dispatcher = new MiddlewareDispatcher([$middlewareClassName], $handler, $this->container);
        $response = $dispatcher->dispatch($this->request);

        $this->assertEquals('Handler executed - Message from dependency', $response->getContent());
    }
}