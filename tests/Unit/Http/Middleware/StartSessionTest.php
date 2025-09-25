<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use Elementary\Config\ConfigBag;
use Elementary\Http\Middleware\StartSession;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Session\Drivers\SessionDriverInterface;
use Elementary\Session\SessionManager;
use Elementary\Utils\SessionBag;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class StartSessionTest extends TestCase
{
    private SessionManager|MockObject $sessionManager;
    private ConfigBag|MockObject $config;
    private SessionDriverInterface|MockObject $driver;
    private StartSession $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->sessionManager = $this->createMock(SessionManager::class);
        $this->config = $this->createMock(ConfigBag::class);
        
        // Create a proper session driver mock that implements all SessionHandlerInterface methods
        $this->driver = $this->createMock(SessionDriverInterface::class);
        $this->driver->method('open')->willReturn(true);
        $this->driver->method('close')->willReturn(true);
        $this->driver->method('read')->willReturn('');
        $this->driver->method('write')->willReturn(true);
        $this->driver->method('destroy')->willReturn(true);
        $this->driver->method('gc')->willReturn(1); // Returns int (number of sessions cleaned)
        
        $this->middleware = new StartSession($this->sessionManager, $this->config);
    }

    protected function tearDown(): void
    {
        // Clean up any active sessions
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // Reset session cookie parameters to defaults
        if (ini_get('session.use_cookies')) {
            session_set_cookie_params(0, '/', '', false, false);
        }
        
        parent::tearDown();
    }

    private function createRequest(array $cookies = []): Request
    {
        return new Request(
            get: [],
            post: [],
            cookies: $cookies,
            files: [],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            headers: [],
            request: [],
            session: new SessionBag(),
            attributes: [],
            content: null
        );
    }

    public function testSkipsIfSessionAlreadyActive(): void
    {
        // Mock an active session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_start();
        
        $request = $this->createRequest();
        
        // SessionManager should not be called if session is already active
        $this->sessionManager->expects($this->never())
            ->method('getDriver');

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        
        // Clean up
        session_destroy();
    }

    public function testStartsNewSession(): void
    {
        // Ensure no session is active
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        $request = $this->createRequest();
        
        // Mock session manager and driver
        $this->sessionManager->expects($this->once())
            ->method('getDriver')
            ->willReturn($this->driver);
            
        // Mock config values
        $this->config->expects($this->once())
            ->method('get')
            ->with('session.lifetime', 120)
            ->willReturn(120);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            // Session should be active now
            $this->assertEquals(PHP_SESSION_ACTIVE, session_status());
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        
        // Clean up
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testUsesCustomSessionLifetime(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        $request = $this->createRequest();
        
        $this->sessionManager->expects($this->once())
            ->method('getDriver')
            ->willReturn($this->driver);
            
        // Mock custom lifetime
        $this->config->expects($this->once())
            ->method('get')
            ->with('session.lifetime', 120)
            ->willReturn(1440); // 24 hours

        $next = function (Request $request) {
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        
        // Clean up
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testUsesExistingSessionIdFromCookie(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        $sessionName = session_name(); // Get the default session name
        $existingSessionId = 'existingsessionid123';
        
        $request = $this->createRequest([$sessionName => $existingSessionId]);
        
        $this->sessionManager->expects($this->once())
            ->method('getDriver')
            ->willReturn($this->driver);
            
        $this->config->expects($this->once())
            ->method('get')
            ->with('session.lifetime', 120)
            ->willReturn(120);

        $next = function (Request $request) use ($existingSessionId) {
            // Verify session ID was set from cookie
            $this->assertEquals($existingSessionId, session_id());
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        
        // Clean up
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testIgnoresInvalidSessionIdFromCookie(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        $sessionName = session_name();
        $invalidSessionId = 'invalid-session-id-with-special-chars!@#$';
        
        $request = $this->createRequest([$sessionName => $invalidSessionId]);
        
        $this->sessionManager->expects($this->once())
            ->method('getDriver')
            ->willReturn($this->driver);
            
        $this->config->expects($this->once())
            ->method('get')
            ->with('session.lifetime', 120)
            ->willReturn(120);

        $next = function (Request $request) use ($invalidSessionId) {
            // Invalid session ID should not be used
            $this->assertNotEquals($invalidSessionId, session_id());
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        
        // Clean up
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testSetsSessionCookieOnResponse(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        $sessionName = session_name();
        $request = $this->createRequest();
        
        $this->sessionManager->expects($this->once())
            ->method('getDriver')
            ->willReturn($this->driver);
            
        $this->config->expects($this->once())
            ->method('get')
            ->with('session.lifetime', 120)
            ->willReturn(120);

        $next = function (Request $request) {
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        
        // Verify session cookie was set on response
        $newSessionId = session_id();
        $this->assertNotEmpty($newSessionId);
        $this->assertEquals($newSessionId, $response->cookies->get($sessionName));
        
        // Clean up
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testDoesNotSetCookieIfSessionIdUnchanged(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        $sessionName = session_name();
        $existingSessionId = 'existingsessionid123';
        
        $request = $this->createRequest([$sessionName => $existingSessionId]);
        
        $this->sessionManager->expects($this->once())
            ->method('getDriver')
            ->willReturn($this->driver);
            
        $this->config->expects($this->once())
            ->method('get')
            ->with('session.lifetime', 120)
            ->willReturn(120);

        $next = function (Request $request) {
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        // If session ID didn't change, cookie should not be set again
        $this->assertEquals(200, $response->getStatusCode());
        
        // The cookie should not be set if the session ID is unchanged  
        $this->assertNull($response->cookies->get($sessionName));
        
        // Clean up
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testSessionCookieParameters(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // Reset session cookie parameters to defaults to ensure clean test
        if (ini_get('session.use_cookies')) {
            session_set_cookie_params(0, '/', '', false, false);
        }
        
        $request = $this->createRequest();
        
        $this->sessionManager->expects($this->once())
            ->method('getDriver')
            ->willReturn($this->driver);
            
        // Mock lifetime config - allow multiple calls since middleware might call config multiple times
        $this->config->expects($this->atLeastOnce())
            ->method('get')
            ->with('session.lifetime', 120)
            ->willReturn(120); // Use default 120 minutes (2 hours) that matches what's actually happening

        $next = function (Request $request) {
            // Verify session cookie parameters were set correctly
            $params = session_get_cookie_params();
            $this->assertEquals(120 * 60, $params['lifetime']); // 120 minutes in seconds
            $this->assertEquals('/', $params['path']);
            $this->assertEquals('', $params['domain']);
            $this->assertFalse($params['secure']);
            $this->assertTrue($params['httponly']);
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        
        // Clean up
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testHandlesEmptySessionCookie(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        $sessionName = session_name();
        $request = $this->createRequest([$sessionName => '']);
        
        $this->sessionManager->expects($this->once())
            ->method('getDriver')
            ->willReturn($this->driver);
            
        $this->config->expects($this->once())
            ->method('get')
            ->with('session.lifetime', 120)
            ->willReturn(120);

        $next = function (Request $request) {
            // Empty session ID should not be used, new one should be generated
            $this->assertNotEmpty(session_id());
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        
        // Clean up
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testSessionStartsWithoutCookies(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        $request = $this->createRequest();
        
        $this->sessionManager->expects($this->once())
            ->method('getDriver')
            ->willReturn($this->driver);
            
        $this->config->expects($this->once())
            ->method('get')
            ->with('session.lifetime', 120)
            ->willReturn(120);

        $next = function (Request $request) {
            // Session should start successfully even without cookies
            $this->assertEquals(PHP_SESSION_ACTIVE, session_status());
            $this->assertNotEmpty(session_id());
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        
        // Clean up
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testResponseContainsNewSessionId(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        $sessionName = session_name();
        $request = $this->createRequest();
        
        $this->sessionManager->expects($this->once())
            ->method('getDriver')
            ->willReturn($this->driver);
            
        $this->config->expects($this->once())
            ->method('get')
            ->with('session.lifetime', 120)
            ->willReturn(120);

        $next = function (Request $request) {
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        // Verify response contains the session ID
        $currentSessionId = session_id();
        $this->assertNotEmpty($currentSessionId);
        $this->assertEquals($currentSessionId, $response->cookies->get($sessionName));
        
        // Clean up
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
