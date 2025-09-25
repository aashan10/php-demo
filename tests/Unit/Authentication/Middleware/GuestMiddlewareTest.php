<?php

declare(strict_types=1);

namespace Tests\Unit\Authentication\Middleware;

use Elementary\Authentication\Middleware\GuestMiddleware;
use Elementary\Authentication\UserInterface;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Utils\SessionBag;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class GuestMiddlewareTest extends TestCase
{
    private GuestMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new GuestMiddleware();
    }

    private function createRequest(?UserInterface $user = null): Request
    {
        $request = new Request(
            get: [],
            post: [],
            cookies: [],
            files: [],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/login'],
            headers: [],
            request: [],
            session: new SessionBag(),
            attributes: [],
            content: null
        );

        if ($user) {
            $request->user = $user;
        }

        return $request;
    }

    private function createMockUser(): UserInterface|MockObject
    {
        return $this->createMock(UserInterface::class);
    }

    public function testAllowsGuestUsers(): void
    {
        $request = $this->createRequest(); // No user set

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Login page');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Login page', $response->getContent());
    }

    public function testRedirectsAuthenticatedUsers(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest($user);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Should not be reached');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertFalse($nextCalled);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/', $response->headers->get('Location'));
        $this->assertEmpty($response->getContent());
    }

    public function testRedirectsToHomeForAuthenticatedUser(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest($user);

        $next = function (Request $request) {
            return new Response(200, 'Guest only content');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/', $response->headers->get('Location'));
    }

    public function testHandlesNullUser(): void
    {
        $request = $this->createRequest();
        $request->user = null; // Explicitly set to null

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Guest content');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Guest content', $response->getContent());
    }

    public function testHandlesUserInterfaceImplementation(): void
    {
        // Create a concrete user class that implements UserInterface
        $user = new class implements UserInterface {
            public function getId(): int
            {
                return 1;
            }
            
            public function getUsername(): string
            {
                return 'testuser';
            }
            
            public function getPasswordHash(): string
            {
                return 'hashed_password';
            }
        };

        $request = $this->createRequest($user);

        $next = function (Request $request) {
            return new Response(200, 'Should not be reached');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/', $response->headers->get('Location'));
    }

    public function testWithDifferentRequestMethods(): void
    {
        $user = $this->createMockUser();
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        foreach ($methods as $method) {
            $request = new Request(
                get: [],
                post: [],
                cookies: [],
                files: [],
                server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/register'],
                headers: [],
                request: [],
                session: new SessionBag(),
                attributes: [],
                content: null
            );
            $request->user = $user;

            $next = function (Request $request) {
                return new Response(200, 'Should not be reached');
            };

            $response = $this->middleware->process($request, $next);

            $this->assertEquals(302, $response->getStatusCode(), "Failed for method: $method");
            $this->assertEquals('/', $response->headers->get('Location'), "Failed for method: $method");
        }
    }

    public function testWithDifferentGuestRoutes(): void
    {
        $guestRoutes = ['/login', '/register', '/forgot-password', '/reset-password'];

        foreach ($guestRoutes as $route) {
            $request = new Request(
                get: [],
                post: [],
                cookies: [],
                files: [],
                server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $route],
                headers: [],
                request: [],
                session: new SessionBag(),
                attributes: [],
                content: null
            );
            // No user set - should be allowed

            $nextCalled = false;
            $next = function (Request $request) use (&$nextCalled) {
                $nextCalled = true;
                return new Response(200, "Content for route");
            };

            $response = $this->middleware->process($request, $next);

            $this->assertTrue($nextCalled, "Next should be called for guest route: $route");
            $this->assertEquals(200, $response->getStatusCode(), "Failed for route: $route");
        }
    }

    public function testProcessReturnType(): void
    {
        // Test with guest user
        $request = $this->createRequest();
        $next = function (Request $request) {
            return new Response(200, 'Guest content');
        };

        $response = $this->middleware->process($request, $next);
        $this->assertInstanceOf(Response::class, $response);

        // Test with authenticated user
        $user = $this->createMockUser();
        $request = $this->createRequest($user);
        $next = function (Request $request) {
            return new Response(200, 'Should not be reached');
        };

        $response = $this->middleware->process($request, $next);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRedirectResponseHeaders(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest($user);

        $next = function (Request $request) {
            return new Response(200, 'Should not be reached');
        };

        $response = $this->middleware->process($request, $next);

        // Verify it's a proper redirect response
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/', $response->headers->get('Location'));
        $this->assertEquals('', $response->getContent());
    }

    public function testUserPropertyAccess(): void
    {
        // Test that middleware correctly checks the user property
        $request = $this->createRequest();
        
        // Initially no user
        $this->assertNull($request->user ?? null);
        
        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Allowed');
        };

        $response = $this->middleware->process($request, $next);
        $this->assertTrue($nextCalled);

        // Now set a user
        $request->user = $this->createMockUser();
        
        $nextCalled = false;
        $response = $this->middleware->process($request, $next);
        $this->assertFalse($nextCalled);
        $this->assertEquals(302, $response->getStatusCode());
    }
}