<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use Elementary\Http\Middleware\VerifyCsrfToken;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Utils\Csrf;
use Elementary\Utils\SessionBag;
use Elementary\Config\ConfigBag;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class VerifyCsrfTokenTest extends TestCase
{
    private Csrf|MockObject $csrf;
    private VerifyCsrfToken $middleware;
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->csrf = $this->createMock(Csrf::class);
        $this->middleware = new VerifyCsrfToken($this->csrf);
    }

    private function createRequest(
        string $method = 'GET',
        string $uri = '/',
        array $post = [],
        array $headers = []
    ): Request {
        return new Request(
            get: [],
            post: $post,
            cookies: [],
            files: [],
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
            headers: $headers,
            request: [],
            session: new SessionBag(),
            attributes: [],
            content: null
        );
    }

    public function testAllowsReadingMethods(): void
    {
        $readingMethods = ['GET', 'HEAD', 'OPTIONS'];
        
        foreach ($readingMethods as $method) {
            $request = $this->createRequest($method);
            
            $nextCalled = false;
            $next = function (Request $request) use (&$nextCalled, $method) {
                $nextCalled = true;
                return new Response(200, "Success for $method");
            };

            $response = $this->middleware->process($request, $next);

            $this->assertTrue($nextCalled, "Next should be called for method $method");
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertStringContainsString("Success for $method", $response->getContent());
        }
    }

    public function testBlocksWritingMethodsWithoutToken(): void
    {
        $writingMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        
        // Mock CSRF validation to return false for all calls
        $this->csrf->expects($this->any())
            ->method('validate')
            ->with(null) // No token provided
            ->willReturn(false);
            
        foreach ($writingMethods as $method) {
            $request = $this->createRequest($method);

            $nextCalled = false;
            $next = function (Request $request) use (&$nextCalled) {
                $nextCalled = true;
                return new Response(200, 'Should not be reached');
            };

            $response = $this->middleware->process($request, $next);

            $this->assertFalse($nextCalled, "Next should not be called for method $method without token");
            $this->assertEquals(419, $response->getStatusCode());
            $this->assertEquals('<h1>Session Expired</h1><p>For your security, this form has expired. Please <a href="javascript:window.location.reload()">refresh the page</a> and try again.</p><script>setTimeout(function(){ window.location.reload(); }, 3000);</script>', $response->getContent());
        }
    }

    public function testAllowsWritingMethodsWithValidTokenInPost(): void
    {
        $request = $this->createRequest('POST', '/', ['_token' => 'valid_token']);
        
        // Mock CSRF validation to return true
        $this->csrf->method('validate')
            ->with('valid_token')
            ->willReturn(true);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    public function testAllowsWritingMethodsWithValidTokenInHeader(): void
    {
        $request = $this->createRequest('POST', '/', [], ['x-csrf-token' => 'valid_token']);
        
        // Mock CSRF validation to return true
        $this->csrf->method('validate')
            ->with('valid_token')
            ->willReturn(true);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    public function testPrefersPostTokenOverHeader(): void
    {
        // Both POST and header tokens present - should prefer POST
        $request = $this->createRequest(
            'POST', 
            '/', 
            ['_token' => 'post_token'], 
            ['x-csrf-token' => 'header_token']
        );
        
        // Should validate with the POST token
        $this->csrf->method('validate')
            ->with('post_token')
            ->willReturn(true);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAllowsExcludedUris(): void
    {
        // Use reflection to set the except array
        $reflection = new \ReflectionClass($this->middleware);
        $exceptProperty = $reflection->getProperty('except');
        $exceptProperty->setAccessible(true);
        $exceptProperty->setValue($this->middleware, ['api/*', 'webhooks/stripe']);

        $excludedUris = [
            '/api/users',
            '/api/posts/123',
            '/webhooks/stripe',
            '/api/', // Should match api/*
        ];

        // Set up the mock to allow validate() calls but it shouldn't matter for excluded URIs
        $this->csrf->method('validate')
            ->willReturn(false);
            
        foreach ($excludedUris as $uri) {
            $request = $this->createRequest('POST', $uri);

            $nextCalled = false;
            $next = function (Request $request) use (&$nextCalled) {
                $nextCalled = true;
                return new Response(200, 'Success');
            };

            $response = $this->middleware->process($request, $next);

            $this->assertTrue($nextCalled, "Next should be called for excluded URI: $uri");
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    public function testDoesNotExcludeNonMatchingUris(): void
    {
        // Use reflection to set the except array
        $reflection = new \ReflectionClass($this->middleware);
        $exceptProperty = $reflection->getProperty('except');
        $exceptProperty->setAccessible(true);
        $exceptProperty->setValue($this->middleware, ['api/*']);

        $nonExcludedUris = [
            '/users',
            '/admin/api/users', // Doesn't start with api/
            '/apiusers', // No slash after api
        ];

        // Set up the mock to return false for validation
        $this->csrf->method('validate')
            ->with(null)
            ->willReturn(false);
            
        foreach ($nonExcludedUris as $uri) {
            $request = $this->createRequest('POST', $uri);

            $nextCalled = false;
            $next = function (Request $request) use (&$nextCalled) {
                $nextCalled = true;
                return new Response(200, 'Should not be reached');
            };

            $response = $this->middleware->process($request, $next);

            $this->assertFalse($nextCalled, "Next should not be called for non-excluded URI: $uri");
            $this->assertEquals(419, $response->getStatusCode());
        }
    }

    public function testRootPathExclusion(): void
    {
        // Use reflection to set the except array with root path
        $reflection = new \ReflectionClass($this->middleware);
        $exceptProperty = $reflection->getProperty('except');
        $exceptProperty->setAccessible(true);
        $exceptProperty->setValue($this->middleware, ['/']);

        $request = $this->createRequest('POST', '/');
        
        // Set up the mock, but it shouldn't be called for excluded root path
        $this->csrf->method('validate')
            ->willReturn(false);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testInvalidTokenReturns419(): void
    {
        $request = $this->createRequest('POST', '/', ['_token' => 'invalid_token']);
        
        // Mock CSRF validation to return false
        $this->csrf->method('validate')
            ->with('invalid_token')
            ->willReturn(false);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Should not be reached');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertFalse($nextCalled);
        $this->assertEquals(419, $response->getStatusCode());
        $this->assertEquals('<h1>Session Expired</h1><p>For your security, this form has expired. Please <a href="javascript:window.location.reload()">refresh the page</a> and try again.</p><script>setTimeout(function(){ window.location.reload(); }, 3000);</script>', $response->getContent());
    }

    public function testIsReadingMethod(): void
    {
        // Test the private isReading method via reflection
        $reflection = new \ReflectionClass($this->middleware);
        $isReadingMethod = $reflection->getMethod('isReading');
        $isReadingMethod->setAccessible(true);

        $readingMethods = ['GET', 'HEAD', 'OPTIONS'];
        $writingMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        foreach ($readingMethods as $method) {
            $request = $this->createRequest($method);
            $this->assertTrue(
                $isReadingMethod->invoke($this->middleware, $request),
                "Method $method should be considered reading"
            );
        }

        foreach ($writingMethods as $method) {
            $request = $this->createRequest($method);
            $this->assertFalse(
                $isReadingMethod->invoke($this->middleware, $request),
                "Method $method should not be considered reading"
            );
        }
    }

    public function testInExceptArrayMethod(): void
    {
        // Test the private inExceptArray method via reflection
        $reflection = new \ReflectionClass($this->middleware);
        $inExceptArrayMethod = $reflection->getMethod('inExceptArray');
        $inExceptArrayMethod->setAccessible(true);

        $exceptProperty = $reflection->getProperty('except');
        $exceptProperty->setAccessible(true);
        $exceptProperty->setValue($this->middleware, ['api/*', 'webhooks/stripe', '/']);

        // Test matching URIs
        $matchingUris = [
            '/',
            '/api/users',
            '/api/',
            '/webhooks/stripe',
        ];

        foreach ($matchingUris as $uri) {
            $request = $this->createRequest('POST', $uri);
            $this->assertTrue(
                $inExceptArrayMethod->invoke($this->middleware, $request),
                "URI $uri should be in except array"
            );
        }

        // Test non-matching URIs
        $nonMatchingUris = [
            '/users',
            '/admin/api/users',
            '/webhooks/paypal',
        ];

        foreach ($nonMatchingUris as $uri) {
            $request = $this->createRequest('POST', $uri);
            $this->assertFalse(
                $inExceptArrayMethod->invoke($this->middleware, $request),
                "URI $uri should not be in except array"
            );
        }
    }

    public function testTokensMatchMethod(): void
    {
        // Test the private tokensMatch method via reflection
        $reflection = new \ReflectionClass($this->middleware);
        $tokensMatchMethod = $reflection->getMethod('tokensMatch');
        $tokensMatchMethod->setAccessible(true);

        // Test with POST token
        $request = $this->createRequest('POST', '/', ['_token' => 'post_token']);
        
        $this->csrf->method('validate')
            ->willReturnMap([
                ['post_token', true],
                ['header_token', true]
            ]);

        $this->assertTrue($tokensMatchMethod->invoke($this->middleware, $request));

        // Test with header token
        $request = $this->createRequest('POST', '/', [], ['x-csrf-token' => 'header_token']);
        
        // Mock already set up above

        $this->assertTrue($tokensMatchMethod->invoke($this->middleware, $request));
    }
}