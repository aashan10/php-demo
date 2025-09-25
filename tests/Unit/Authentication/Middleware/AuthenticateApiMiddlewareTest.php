<?php

declare(strict_types=1);

namespace Tests\Unit\Authentication\Middleware;

use Elementary\Authentication\Middleware\AuthenticateApiMiddleware;
use Elementary\Authentication\UserInterface;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Utils\SessionBag;
use Elementary\Utils\ParameterBag;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class AuthenticateApiMiddlewareTest extends TestCase
{
    private AuthenticateApiMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new AuthenticateApiMiddleware();
    }

    private function createRequest(?UserInterface $user = null, array $headers = []): Request
    {
        $headerBag = new ParameterBag($headers);
        
        $request = new Request(
            get: [],
            post: [],
            cookies: [],
            files: [],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/users'],
            headers: $headers,
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

    public function testAllowsAlreadyAuthenticatedUser(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest($user);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'API response');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('API response', $response->getContent());
    }

    public function testReturns401WhenNoApiTokenHeader(): void
    {
        $request = $this->createRequest();

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Should not be reached');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertFalse($nextCalled);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Unauthorized', $response->getContent());
    }

    public function testAllowsRequestWithApiTokenHeader(): void
    {
        $request = $this->createRequest(null, ['x-elementray-api-token' => 'valid-token']);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'API data');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('API data', $response->getContent());
    }

    public function testHandlesEmptyApiTokenHeader(): void
    {
        $request = $this->createRequest(null, ['x-elementray-api-token' => '']);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Should work with empty token');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testExactHeaderCaseRequired(): void
    {
        // Test that header checking requires exact case (current framework behavior)
        $validHeader = 'x-elementray-api-token';
        $request = $this->createRequest(null, [$validHeader => 'test-token']);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());

        // Test that different case fails
        $invalidCaseHeaders = [
            'X-Elementray-Api-Token',
            'X-ELEMENTRAY-API-TOKEN',
            'x-Elementray-Api-Token'
        ];

        foreach ($invalidCaseHeaders as $headerName) {
            $request = $this->createRequest(null, [$headerName => 'test-token']);

            $nextCalled = false;
            $next = function (Request $request) use (&$nextCalled) {
                $nextCalled = true;
                return new Response(200, 'Should not be reached');
            };

            $response = $this->middleware->process($request, $next);

            $this->assertFalse($nextCalled, "Should have failed for header: $headerName");
            $this->assertEquals(401, $response->getStatusCode());
        }
    }

    public function testWithDifferentApiTokenValues(): void
    {
        $tokenValues = [
            'simple-token',
            'bearer-token-123',
            'very-long-token-with-many-characters-and-numbers-12345',
            '123456789',
            'token_with_underscores',
            'token.with.dots',
            'token+with+plus',
            'token=with=equals'
        ];

        foreach ($tokenValues as $token) {
            $request = $this->createRequest(null, ['x-elementray-api-token' => $token]);

            $nextCalled = false;
            $next = function (Request $request) use (&$nextCalled) {
                $nextCalled = true;
                return new Response(200, 'Token accepted');
            };

            $response = $this->middleware->process($request, $next);

            $this->assertTrue($nextCalled, "Failed for token: $token");
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    public function testWithDifferentRequestMethods(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

        foreach ($methods as $method) {
            $request = new Request(
                get: [],
                post: [],
                cookies: [],
                files: [],
                server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/api/endpoint'],
                headers: ['x-elementray-api-token' => 'test-token'],
                request: [],
                session: new SessionBag(),
                attributes: [],
                content: null
            );

            $nextCalled = false;
            $next = function (Request $request) use (&$nextCalled) {
                $nextCalled = true;
                return new Response(200, 'Method allowed');
            };

            $response = $this->middleware->process($request, $next);

            $this->assertTrue($nextCalled, "Failed for method: $method");
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    public function testUserInterfaceTypeCheck(): void
    {
        // Create a concrete implementation of UserInterface
        $user = new class implements UserInterface {
            public function getId(): int
            {
                return 42;
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

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'User authenticated');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testMultipleHeadersWithApiToken(): void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'x-elementray-api-token' => 'api-token-123',
            'Authorization' => 'Bearer some-other-token',
            'User-Agent' => 'API Client/1.0'
        ];

        $request = $this->createRequest(null, $headers);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Multiple headers handled');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testReturnsCorrectUnauthorizedResponse(): void
    {
        $request = $this->createRequest();

        $next = function (Request $request) {
            return new Response(200, 'Should not be reached');
        };

        $response = $this->middleware->process($request, $next);

        // Verify the exact response format
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Unauthorized', $response->getContent());
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testMiddlewareDoesNotModifyRequest(): void
    {
        $originalHeaders = ['x-elementray-api-token' => 'test-token'];
        $request = $this->createRequest(null, $originalHeaders);

        $next = function (Request $request) use ($originalHeaders) {
            // Verify the request wasn't modified
            $this->assertEquals('test-token', $request->headers->get('x-elementray-api-token'));
            return new Response(200, 'Request preserved');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Request preserved', $response->getContent());
    }

    public function testWithApiRoutePatterns(): void
    {
        $apiRoutes = [
            '/api/v1/users',
            '/api/v2/products',
            '/api/auth/login',
            '/api/public/health',
            '/api/admin/settings'
        ];

        foreach ($apiRoutes as $route) {
            $request = new Request(
                get: [],
                post: [],
                cookies: [],
                files: [],
                server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $route],
                headers: ['x-elementray-api-token' => 'route-token'],
                request: [],
                session: new SessionBag(),
                attributes: [],
                content: null
            );

            $nextCalled = false;
            $next = function (Request $request) use (&$nextCalled) {
                $nextCalled = true;
                return new Response(200, 'API route accessed');
            };

            $response = $this->middleware->process($request, $next);

            $this->assertTrue($nextCalled, "Failed for route: $route");
            $this->assertEquals(200, $response->getStatusCode());
        }
    }
}