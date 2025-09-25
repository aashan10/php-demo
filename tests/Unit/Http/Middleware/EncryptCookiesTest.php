<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use Elementary\Http\Middleware\EncryptCookies;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Utils\EncryptionService;
use Elementary\Utils\SessionBag;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;

class EncryptCookiesTest extends TestCase
{
    private EncryptionService|MockObject $encryptionService;
    private EncryptCookies $middleware;
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->encryptionService = $this->createMock(EncryptionService::class);
        $this->middleware = new EncryptCookies($this->encryptionService);
        
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

    public function testProcessWithNoCookies(): void
    {
        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($this->request, $next);

        $this->assertTrue($nextCalled);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    public function testDecryptsCookiesOnRequest(): void
    {
        // Set up encrypted cookies on request
        $this->request->cookies->set('encrypted_cookie', 'encrypted_value');
        $this->request->cookies->set('another_cookie', 'another_encrypted_value');

        // Mock decryption
        $this->encryptionService->expects($this->exactly(2))
            ->method('decrypt')
            ->willReturnMap([
                ['encrypted_value', 'decrypted_value'],
                ['another_encrypted_value', 'another_decrypted_value']
            ]);

        $next = function (Request $request) {
            // Verify cookies were decrypted
            $this->assertEquals('decrypted_value', $request->cookies->get('encrypted_cookie'));
            $this->assertEquals('another_decrypted_value', $request->cookies->get('another_cookie'));
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($this->request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHandlesDecryptionFailure(): void
    {
        // Set up encrypted cookie that will fail to decrypt
        $this->request->cookies->set('bad_cookie', 'corrupted_encrypted_value');

        // Mock decryption failure
        $this->encryptionService->expects($this->once())
            ->method('decrypt')
            ->with('corrupted_encrypted_value')
            ->willThrowException(new RuntimeException('Decryption failed'));

        $next = function (Request $request) {
            // Verify cookie was set to null when decryption failed
            $this->assertNull($request->cookies->get('bad_cookie'));
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($this->request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testEncryptsCookiesOnResponse(): void
    {
        $next = function (Request $request) {
            $response = new Response(200, 'Success');
            $response->cookies->set('new_cookie', 'new_value');
            $response->cookies->set('another_new_cookie', 'another_new_value');
            return $response;
        };

        // Mock encryption
        $this->encryptionService->expects($this->exactly(2))
            ->method('encrypt')
            ->willReturnMap([
                ['new_value', 'encrypted_new_value'],
                ['another_new_value', 'encrypted_another_new_value']
            ]);

        $response = $this->middleware->process($this->request, $next);

        // Verify cookies were encrypted on response
        $this->assertEquals('encrypted_new_value', $response->cookies->get('new_cookie'));
        $this->assertEquals('encrypted_another_new_value', $response->cookies->get('another_new_cookie'));
    }

    public function testSkipsDisabledCookiesOnDecryption(): void
    {
        // Use reflection to set the except array
        $reflection = new \ReflectionClass($this->middleware);
        $exceptProperty = $reflection->getProperty('except');
        $exceptProperty->setAccessible(true);
        $exceptProperty->setValue($this->middleware, ['skip_cookie']);

        // Set up cookies - one that should be skipped, one that should be decrypted
        $this->request->cookies->set('skip_cookie', 'should_not_be_decrypted');
        $this->request->cookies->set('encrypt_cookie', 'encrypted_value');

        // Mock decryption - should only be called once
        $this->encryptionService->expects($this->once())
            ->method('decrypt')
            ->with('encrypted_value')
            ->willReturn('decrypted_value');

        $next = function (Request $request) {
            // Verify skipped cookie unchanged, other cookie decrypted
            $this->assertEquals('should_not_be_decrypted', $request->cookies->get('skip_cookie'));
            $this->assertEquals('decrypted_value', $request->cookies->get('encrypt_cookie'));
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($this->request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSkipsDisabledCookiesOnEncryption(): void
    {
        // Use reflection to set the except array
        $reflection = new \ReflectionClass($this->middleware);
        $exceptProperty = $reflection->getProperty('except');
        $exceptProperty->setAccessible(true);
        $exceptProperty->setValue($this->middleware, ['skip_cookie']);

        $next = function (Request $request) {
            $response = new Response(200, 'Success');
            $response->cookies->set('skip_cookie', 'should_not_be_encrypted');
            $response->cookies->set('encrypt_cookie', 'new_value');
            return $response;
        };

        // Mock encryption - should only be called once
        $this->encryptionService->expects($this->once())
            ->method('encrypt')
            ->with('new_value')
            ->willReturn('encrypted_new_value');

        $response = $this->middleware->process($this->request, $next);

        // Verify skipped cookie unchanged, other cookie encrypted
        $this->assertEquals('should_not_be_encrypted', $response->cookies->get('skip_cookie'));
        $this->assertEquals('encrypted_new_value', $response->cookies->get('encrypt_cookie'));
    }

    public function testFullWorkflow(): void
    {
        // Set up incoming encrypted cookies
        $this->request->cookies->set('existing_cookie', 'existing_encrypted_value');

        // Mock decryption for incoming cookies
        $this->encryptionService->expects($this->once())
            ->method('decrypt')
            ->with('existing_encrypted_value')
            ->willReturn('existing_decrypted_value');

        // Mock encryption for outgoing cookies
        $this->encryptionService->expects($this->exactly(2))
            ->method('encrypt')
            ->willReturnMap([
                ['existing_decrypted_value', 'existing_re_encrypted_value'],
                ['new_value', 'new_encrypted_value']
            ]);

        $next = function (Request $request) {
            // Verify incoming cookie was decrypted
            $this->assertEquals('existing_decrypted_value', $request->cookies->get('existing_cookie'));
            
            // Create response with new cookie
            $response = new Response(200, 'Success');
            $response->cookies->set('existing_cookie', 'existing_decrypted_value'); // Re-set existing
            $response->cookies->set('new_cookie', 'new_value'); // Add new
            return $response;
        };

        $response = $this->middleware->process($this->request, $next);

        // Verify both cookies were encrypted on response
        $this->assertEquals('existing_re_encrypted_value', $response->cookies->get('existing_cookie'));
        $this->assertEquals('new_encrypted_value', $response->cookies->get('new_cookie'));
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIsDisabledMethod(): void
    {
        // Test the private isDisabled method via reflection
        $reflection = new \ReflectionClass($this->middleware);
        $isDisabledMethod = $reflection->getMethod('isDisabled');
        $isDisabledMethod->setAccessible(true);

        $exceptProperty = $reflection->getProperty('except');
        $exceptProperty->setAccessible(true);
        $exceptProperty->setValue($this->middleware, ['disabled_cookie', 'another_disabled']);

        // Test disabled cookies
        $this->assertTrue($isDisabledMethod->invoke($this->middleware, 'disabled_cookie'));
        $this->assertTrue($isDisabledMethod->invoke($this->middleware, 'another_disabled'));
        
        // Test enabled cookies
        $this->assertFalse($isDisabledMethod->invoke($this->middleware, 'enabled_cookie'));
        $this->assertFalse($isDisabledMethod->invoke($this->middleware, 'normal_cookie'));
    }

    public function testWithEmptyExceptArray(): void
    {
        // Default behavior - no cookies should be skipped
        $this->request->cookies->set('cookie1', 'value1');
        $this->request->cookies->set('cookie2', 'value2');

        $this->encryptionService->expects($this->exactly(2))
            ->method('decrypt')
            ->willReturnMap([
                ['value1', 'decrypted1'],
                ['value2', 'decrypted2']
            ]);

        $this->encryptionService->expects($this->exactly(2))
            ->method('encrypt')
            ->willReturnMap([
                ['decrypted1', 'encrypted1'],
                ['decrypted2', 'encrypted2']
            ]);

        $next = function (Request $request) {
            $response = new Response(200, 'Success');
            $response->cookies->set('cookie1', $request->cookies->get('cookie1'));
            $response->cookies->set('cookie2', $request->cookies->get('cookie2'));
            return $response;
        };

        $response = $this->middleware->process($this->request, $next);

        $this->assertEquals('encrypted1', $response->cookies->get('cookie1'));
        $this->assertEquals('encrypted2', $response->cookies->get('cookie2'));
    }
}