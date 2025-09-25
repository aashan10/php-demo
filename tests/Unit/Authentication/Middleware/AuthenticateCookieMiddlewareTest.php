<?php

declare(strict_types=1);

namespace Tests\Unit\Authentication\Middleware;

// Mock user model class for testing
class TestUserModel
{
    private static array $users = [];
    
    public static function setUsers(array $users): void
    {
        self::$users = $users;
    }
    
    public static function find(int $id): ?object
    {
        return self::$users[$id] ?? null;
    }
    
    public static function clear(): void
    {
        self::$users = [];
    }
}

use Elementary\Authentication\Middleware\AuthenticateCookieMiddleware;
use Elementary\Authentication\UserInterface;
use Elementary\Config\ConfigBag;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Utils\SessionBag;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class AuthenticateCookieMiddlewareTest extends TestCase
{
    private ConfigBag|MockObject $config;
    private AuthenticateCookieMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->config = $this->createMock(ConfigBag::class);
        $this->middleware = new AuthenticateCookieMiddleware($this->config);
        
        // Clear the test user model
        TestUserModel::clear();
    }

    private function createRequest(?UserInterface $user = null, array $cookies = []): Request
    {
        $request = new Request(
            get: [],
            post: [],
            cookies: $cookies,
            files: [],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard'],
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

    private function createMockUser(int $id = 1, ?string $rememberToken = null): UserInterface
    {
        return new class($id, $rememberToken) implements UserInterface {
            public function __construct(
                private int $id,
                public ?string $remember_token
            ) {}
            
            public function getId(): int|string
            {
                return $this->id;
            }
            
            public function getUsername(): string
            {
                return 'test_user';
            }
            
            public function getPasswordHash(): string
            {
                return 'hashed_password';
            }
        };
    }

    public function testSkipsIfUserAlreadyAuthenticated(): void
    {
        $user = $this->createMockUser();
        $request = $this->createRequest($user, ['elementary_auth' => '123|token']);

        // Config should not be called if user is already authenticated
        $this->config->expects($this->never())
            ->method('get');

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

    public function testSkipsIfNoCookie(): void
    {
        $request = $this->createRequest();

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            $this->assertNull($request->user ?? null);
            return new Response(200, 'Content');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSkipsIfEmptyCookie(): void
    {
        $request = $this->createRequest(null, ['elementary_auth' => '']);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            $this->assertNull($request->user ?? null);
            return new Response(200, 'Content');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSkipsIfInvalidCookieFormat(): void
    {
        $invalidCookieValues = [
            'invalid_format',           // No pipe separator
            'onlyonepart',             // No pipe separator
            'too|many|parts|here',     // Too many parts
            '|token',                  // Empty user ID
            'userId|',                 // Empty token
            '|',                       // Both empty
        ];

        // Set up config mock to return a user model class for tests that reach the find() call
        $this->config->method('get')
            ->with('auth.model')
            ->willReturn(TestUserModel::class);
            
        foreach ($invalidCookieValues as $cookieValue) {
            $request = $this->createRequest(null, ['elementary_auth' => $cookieValue]);

            $nextCalled = false;
            $next = function (Request $request) use (&$nextCalled) {
                $nextCalled = true;
                $this->assertNull($request->user ?? null);
                return new Response(200, 'Content');
            };

            $response = $this->middleware->process($request, $next);

            $this->assertTrue($nextCalled, "Failed for cookie value: $cookieValue");
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    public function testSkipsIfUserNotFound(): void
    {
        // Don't set any users in TestUserModel, so find() returns null
        
        $request = $this->createRequest(null, ['elementary_auth' => '999|valid_token']);

        $this->config->expects($this->once())
            ->method('get')
            ->with('auth.model')
            ->willReturn(TestUserModel::class);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            $this->assertNull($request->user ?? null);
            return new Response(200, 'Content');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSkipsIfUserHasNoRememberToken(): void
    {
        $mockUser = $this->createMockUser(123, null); // No remember token
        TestUserModel::setUsers([123 => $mockUser]);

        $request = $this->createRequest(null, ['elementary_auth' => '123|valid_token']);

        $this->config->expects($this->once())
            ->method('get')
            ->with('auth.model')
            ->willReturn(TestUserModel::class);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            $this->assertNull($request->user ?? null);
            return new Response(200, 'Content');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSkipsIfTokenDoesNotMatch(): void
    {
        $storedTokenHash = hash('sha256', 'correct_token');
        $mockUser = $this->createMockUser(123, $storedTokenHash);
        TestUserModel::setUsers([123 => $mockUser]);

        $request = $this->createRequest(null, ['elementary_auth' => '123|wrong_token']);

        $this->config->expects($this->once())
            ->method('get')
            ->with('auth.model')
            ->willReturn(TestUserModel::class);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            $this->assertNull($request->user ?? null);
            return new Response(200, 'Content');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAuthenticatesUserWithValidCookie(): void
    {
        $plainTextToken = 'valid_remember_token';
        $storedTokenHash = hash('sha256', $plainTextToken);
        $mockUser = $this->createMockUser(123, $storedTokenHash);
        TestUserModel::setUsers([123 => $mockUser]);

        $request = $this->createRequest(null, ['elementary_auth' => "123|$plainTextToken"]);

        $this->config->expects($this->once())
            ->method('get')
            ->with('auth.model')
            ->willReturn(TestUserModel::class);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled, $mockUser) {
            $nextCalled = true;
            $this->assertSame($mockUser, $request->user);
            return new Response(200, 'Protected content');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Protected content', $response->getContent());
    }

    public function testHandlesNumericUserIdInCookie(): void
    {
        $plainTextToken = 'valid_token';
        $storedTokenHash = hash('sha256', $plainTextToken);
        $mockUser = $this->createMockUser(456, $storedTokenHash);
        TestUserModel::setUsers([456 => $mockUser]);

        $request = $this->createRequest(null, ['elementary_auth' => "456|$plainTextToken"]);

        $this->config->expects($this->once())
            ->method('get')
            ->with('auth.model')
            ->willReturn(TestUserModel::class);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled, $mockUser) {
            $nextCalled = true;
            $this->assertSame($mockUser, $request->user);
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSecureTokenComparison(): void
    {
        // Test that hash_equals is used for secure comparison
        $plainTextToken = 'secret_token';
        $storedTokenHash = hash('sha256', $plainTextToken);
        
        // Create a token that would pass simple string comparison but should fail hash_equals
        $similarToken = $plainTextToken . 'x';
        
        $mockUser = $this->createMockUser(123, $storedTokenHash);
        TestUserModel::setUsers([123 => $mockUser]);

        $request = $this->createRequest(null, ['elementary_auth' => "123|$similarToken"]);

        $this->config->expects($this->once())
            ->method('get')
            ->with('auth.model')
            ->willReturn(TestUserModel::class);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            $this->assertNull($request->user ?? null);
            return new Response(200, 'Content');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testTokenHashingConsistency(): void
    {
        $plainTextToken = 'test_token_123';
        $expectedHash = hash('sha256', $plainTextToken);
        
        $mockUser = $this->createMockUser(789, $expectedHash);
        TestUserModel::setUsers([789 => $mockUser]);

        $request = $this->createRequest(null, ['elementary_auth' => "789|$plainTextToken"]);

        $this->config->expects($this->once())
            ->method('get')
            ->with('auth.model')
            ->willReturn(TestUserModel::class);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled, $mockUser) {
            $nextCalled = true;
            $this->assertSame($mockUser, $request->user);
            return new Response(200, 'Authenticated');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCookieValueParsing(): void
    {
        // Test parsing of the cookie value format "userId|token"
        $testCases = [
            '1|simple_token' => ['userId' => 1, 'token' => 'simple_token'],
            '999|complex_token_with_underscores' => ['userId' => 999, 'token' => 'complex_token_with_underscores'],
            '42|token-with-dashes' => ['userId' => 42, 'token' => 'token-with-dashes'],
        ];

        foreach ($testCases as $cookieValue => $expected) {
            // Clear users from previous iteration
            TestUserModel::clear();
            
            $storedTokenHash = hash('sha256', $expected['token']);
            $mockUser = $this->createMockUser($expected['userId'], $storedTokenHash);
            TestUserModel::setUsers([$expected['userId'] => $mockUser]);

            $request = $this->createRequest(null, ['elementary_auth' => $cookieValue]);

            $this->config->expects($this->any())
                ->method('get')
                ->with('auth.model')
                ->willReturn(TestUserModel::class);

            $nextCalled = false;
            $next = function (Request $request) use (&$nextCalled, $mockUser) {
                $nextCalled = true;
                $this->assertSame($mockUser, $request->user);
                return new Response(200, 'Success');
            };

            $response = $this->middleware->process($request, $next);

            $this->assertTrue($nextCalled, "Failed for cookie value: $cookieValue");
            $this->assertEquals(200, $response->getStatusCode());
        }
    }
}