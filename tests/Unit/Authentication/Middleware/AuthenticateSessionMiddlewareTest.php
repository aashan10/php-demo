<?php

declare(strict_types=1);

namespace Tests\Unit\Authentication\Middleware;

use Elementary\Authentication\Middleware\AuthenticateSessionMiddleware;
use Elementary\Authentication\UserInterface;
use Elementary\Config\ConfigBag;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Utils\SessionBag;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

// Mock user model class for testing
class TestSessionUserModel
{
    private static array $users = [];
    
    public static function setUsers(array $users): void
    {
        self::$users = $users;
    }
    
    public static function find(mixed $id): ?object
    {
        $intId = (int) $id; // Handle string to int conversion
        return self::$users[$intId] ?? null;
    }
    
    public static function clear(): void
    {
        self::$users = [];
    }
}

class AuthenticateSessionMiddlewareTest extends TestCase
{
    private ConfigBag|MockObject $config;
    private AuthenticateSessionMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->config = $this->createMock(ConfigBag::class);
        $this->middleware = new AuthenticateSessionMiddleware($this->config);
        
        // Clear the test user model
        TestSessionUserModel::clear();
    }

    private function createRequest(?UserInterface $user = null, array $sessionData = []): Request
    {
        $session = new SessionBag();
        foreach ($sessionData as $key => $value) {
            $session->set($key, $value);
        }

        $request = new Request(
            get: [],
            post: [],
            cookies: [],
            files: [],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard'],
            headers: [],
            request: [],
            session: $session,
            attributes: [],
            content: null
        );

        if ($user) {
            $request->user = $user;
        }

        return $request;
    }

    private function createMockUser(int $id = 1): UserInterface|MockObject
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getId')->willReturn($id);
        return $user;
    }

    public function testAllowsAlreadyAuthenticatedUser(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest($user);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Protected content');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Protected content', $response->getContent());
    }

    public function testRedirectsToLoginWhenNoUserIdInSession(): void
    {
        $request = $this->createRequest();

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Should not be reached');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertFalse($nextCalled);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->headers->get('Location'));
        
        // Verify redirection URL was stored in session
        $this->assertEquals('/dashboard', $request->session->get('redirection_url_after_login'));
    }

    public function testAuthenticatesUserFromSession(): void
    {
        $mockUser = new class implements UserInterface {
            public function getId(): int { return 123; }
            public function getUsername(): string { return 'testuser'; }
            public function getPasswordHash(): string { return 'hashed_password'; }
        };
        TestSessionUserModel::setUsers([123 => $mockUser]);

        $request = $this->createRequest(null, ['user_id' => 123]);

        // Configure the user model class
        $this->config->expects($this->once())
            ->method('get')
            ->with('auth.model')
            ->willReturn(TestSessionUserModel::class);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            // Verify user was set on request
            $this->assertInstanceOf(UserInterface::class, $request->user);
            $this->assertEquals(123, $request->user->getId());
            return new Response(200, 'Protected content');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Protected content', $response->getContent());
    }

    public function testRedirectsWhenUserNotFoundInDatabase(): void
    {
        // Don't set any users in TestSessionUserModel, so find() returns null
        
        $request = $this->createRequest(null, ['user_id' => 999]);

        $this->config->expects($this->once())
            ->method('get')
            ->with('auth.model')
            ->willReturn(TestSessionUserModel::class);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Should not be reached');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertFalse($nextCalled);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->headers->get('Location'));
        
        // Verify user_id was removed from session
        $this->assertFalse($request->session->has('user_id'));
        
        // Verify redirection URL was stored
        $this->assertEquals('/dashboard', $request->session->get('redirection_url_after_login'));
    }

    public function testRedirectsWhenUserIsNotUserInterface(): void
    {
        // Set up a non-UserInterface object
        $nonUserObject = new \stdClass();
        TestSessionUserModel::setUsers([123 => $nonUserObject]);

        $request = $this->createRequest(null, ['user_id' => 123]);

        $this->config->expects($this->once())
            ->method('get')
            ->with('auth.model')
            ->willReturn(TestSessionUserModel::class);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Should not be reached');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertFalse($nextCalled);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->headers->get('Location'));
        
        // Verify user_id was removed from session
        $this->assertFalse($request->session->has('user_id'));
    }

    public function testSetsRedirectionUrlFromCurrentUri(): void
    {
        // Test with different URIs
        $uris = [
            '/admin/dashboard',
            '/profile',
            '/settings/password',
            '/',
        ];

        foreach ($uris as $uri) {
            $request = new Request(
                get: [],
                post: [],
                cookies: [],
                files: [],
                server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri],
                headers: [],
                request: [],
                session: new SessionBag(),
                attributes: [],
                content: null
            );

            $next = function (Request $request) {
                return new Response(200, 'Should not be reached');
            };

            $response = $this->middleware->process($request, $next);

            $this->assertEquals(302, $response->getStatusCode());
            $this->assertEquals($uri, $request->session->get('redirection_url_after_login'));
        }
    }

    public function testHandlesSessionWithStringUserId(): void
    {
        $mockUser = new class implements UserInterface {
            public function getId(): int { return 456; }
            public function getUsername(): string { return 'testuser'; }
            public function getPasswordHash(): string { return 'hashed_password'; }
        };
        TestSessionUserModel::setUsers([456 => $mockUser]);

        // Session has user_id as string
        $request = $this->createRequest(null, ['user_id' => '456']);

        $this->config->expects($this->once())
            ->method('get')
            ->with('auth.model')
            ->willReturn(TestSessionUserModel::class);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            $this->assertEquals(456, $request->user->getId());
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHandlesInvalidUserIdInSession(): void
    {
        // Don't set any users in TestSessionUserModel, so find() returns null
        
        // Session has invalid user_id
        $request = $this->createRequest(null, ['user_id' => 'invalid']);

        $this->config->expects($this->once())
            ->method('get')
            ->with('auth.model')
            ->willReturn(TestSessionUserModel::class);

        $next = function (Request $request) {
            return new Response(200, 'Should not be reached');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->headers->get('Location'));
    }

    public function testPreservesExistingSessionData(): void
    {
        $session = new SessionBag();
        $session->set('existing_key', 'existing_value');
        $session->set('user_id', 123);

        $request = new Request(
            get: [],
            post: [],
            cookies: [],
            files: [],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard'],
            headers: [],
            request: [],
            session: $session,
            attributes: [],
            content: null
        );

        // Don't set any users in TestSessionUserModel, so find() returns null
        
        $this->config->expects($this->once())
            ->method('get')
            ->with('auth.model')
            ->willReturn(TestSessionUserModel::class);

        $next = function (Request $request) {
            return new Response(200, 'Should not be reached');
        };

        $response = $this->middleware->process($request, $next);

        // Verify existing session data is preserved
        $this->assertEquals('existing_value', $request->session->get('existing_key'));
        
        // But user_id should be removed
        $this->assertFalse($request->session->has('user_id'));
        
        // And redirection URL should be set
        $this->assertEquals('/dashboard', $request->session->get('redirection_url_after_login'));
    }
}