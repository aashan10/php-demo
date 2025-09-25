<?php

declare(strict_types=1);

namespace Tests\Unit\Kernel;

use Elementary\Http\Response;
use Elementary\Kernel\ConsoleKernel;
use Elementary\Kernel\HttpKernel;
use Elementary\Kernel\KernelInterface;
use Elementary\Routing\RouteCollection;
use PHPUnit\Framework\TestCase;

class KernelInterfaceTest extends TestCase
{
    private string $tempAppDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create temporary app structure for testing
        $this->tempAppDir = sys_get_temp_dir() . '/elementary_kernel_interface_test_' . uniqid();
        mkdir($this->tempAppDir, 0777, true);
        mkdir($this->tempAppDir . '/config', 0777, true);
        mkdir($this->tempAppDir . '/src/Console/Commands', 0777, true);
        
        // Create basic config files
        file_put_contents($this->tempAppDir . '/config/app.php', '<?php return ["env" => "testing"];');
        file_put_contents($this->tempAppDir . '/config/middleware.php', '<?php return ["aliases" => [], "groups" => []];');
        file_put_contents($this->tempAppDir . '/config/template.php', '<?php return ["directives" => []];');
        file_put_contents($this->tempAppDir . '/constants.php', '<?php define("BASE_PATH", "' . $this->tempAppDir . '");');
        
        // Create minimal bootstrap.php
        file_put_contents($this->tempAppDir . '/bootstrap.php', '<?php // Minimal bootstrap for testing');
        
        // Override BASE_PATH constant for testing
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', $this->tempAppDir);
        }
    }

    public function testHttpKernelImplementsKernelInterface(): void
    {
        $httpKernel = new HttpKernel();
        
        $this->assertInstanceOf(KernelInterface::class, $httpKernel);
    }

    public function testConsoleKernelImplementsKernelInterface(): void
    {
        $consoleKernel = new ConsoleKernel();
        
        $this->assertInstanceOf(KernelInterface::class, $consoleKernel);
    }

    public function testHttpKernelHasAllRequiredMethods(): void
    {
        $httpKernel = new HttpKernel();
        
        $this->assertTrue(method_exists($httpKernel, 'bootstrap'));
        $this->assertTrue(method_exists($httpKernel, 'handle'));
        $this->assertTrue(method_exists($httpKernel, 'handleCli'));
        $this->assertTrue(method_exists($httpKernel, 'terminate'));
        $this->assertTrue(method_exists($httpKernel, 'terminateCli'));
    }

    public function testConsoleKernelHasAllRequiredMethods(): void
    {
        $consoleKernel = new ConsoleKernel();
        
        $this->assertTrue(method_exists($consoleKernel, 'bootstrap'));
        $this->assertTrue(method_exists($consoleKernel, 'handle'));
        $this->assertTrue(method_exists($consoleKernel, 'handleCli'));
        $this->assertTrue(method_exists($consoleKernel, 'terminate'));
        $this->assertTrue(method_exists($consoleKernel, 'terminateCli'));
    }

    public function testHttpKernelBootstrapMethod(): void
    {
        // Clean up routes
        RouteCollection::getInstance()->clear();
        
        // Mock $_SERVER for Request::createFromGlobals()
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'HTTPS' => 'off'
        ]);
        
        $httpKernel = new HttpKernel();
        
        // Should not throw any exceptions
        $httpKernel->bootstrap();
        
        // Clean up Whoops handler
        $httpKernel->terminate(new Response(200, ''));
        
        $this->assertTrue(true);
    }

    public function testConsoleKernelBootstrapMethod(): void
    {
        $consoleKernel = new ConsoleKernel();
        
        // Should not throw any exceptions
        $consoleKernel->bootstrap();
        
        $this->assertTrue(true);
    }

    public function testHttpKernelHandleReturnsResponse(): void
    {
        // Clean up routes
        RouteCollection::getInstance()->clear();
        
        // Mock $_SERVER for 404
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/nonexistent',
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'HTTPS' => 'off'
        ]);
        
        $httpKernel = new HttpKernel();
        
        try {
            $response = $httpKernel->handle();
            $this->assertInstanceOf(Response::class, $response);
        } catch (\Exception $e) {
            // In development mode, exceptions are thrown
            $this->assertEquals(404, $e->getCode());
        } finally {
            // Clean up Whoops handler
            $httpKernel->terminate(new Response(200, ''));
        }
    }

    public function testConsoleKernelHandleThrowsException(): void
    {
        $consoleKernel = new ConsoleKernel();
        
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('handle not implemented for ConsoleKernel');
        
        $consoleKernel->handle();
    }

    public function testHttpKernelHandleCliThrowsException(): void
    {
        $httpKernel = new HttpKernel();
        
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('handleCli not implemented for HttpKernel');
        
        $httpKernel->handleCli(['test']);
    }

    public function testConsoleKernelHandleCliReturnsInt(): void
    {
        $consoleKernel = new ConsoleKernel();
        
        // Test with help command which should return 0
        $exitCode = $consoleKernel->handleCli(['elementary', 'help']);
        
        $this->assertIsInt($exitCode);
        $this->assertEquals(0, $exitCode);
    }

    public function testHttpKernelTerminateAcceptsResponse(): void
    {
        $httpKernel = new HttpKernel();
        $response = new Response(200, 'Test');
        
        // Should not throw any exceptions
        $httpKernel->terminate($response);
        
        $this->assertTrue(true);
    }

    public function testConsoleKernelTerminateThrowsException(): void
    {
        $consoleKernel = new ConsoleKernel();
        $response = new Response(200, 'Test');
        
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('terminate not implemented for ConsoleKernel');
        
        $consoleKernel->terminate($response);
    }

    public function testHttpKernelTerminateCliThrowsException(): void
    {
        $httpKernel = new HttpKernel();
        
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('terminateCli not implemented for HttpKernel');
        
        $httpKernel->terminateCli(0);
    }

    public function testConsoleKernelTerminateCliAcceptsInt(): void
    {
        $consoleKernel = new ConsoleKernel();
        
        // Should not throw any exceptions
        $consoleKernel->terminateCli(0);
        $consoleKernel->terminateCli(1);
        
        $this->assertTrue(true);
    }

    /**
     * Test that the interface methods have correct parameter and return types
     */
    public function testInterfaceMethodSignatures(): void
    {
        $reflection = new \ReflectionClass(KernelInterface::class);
        
        // Test bootstrap method
        $bootstrapMethod = $reflection->getMethod('bootstrap');
        $this->assertEquals('void', $bootstrapMethod->getReturnType()->getName());
        $this->assertCount(0, $bootstrapMethod->getParameters());
        
        // Test handle method
        $handleMethod = $reflection->getMethod('handle');
        $this->assertEquals(Response::class, $handleMethod->getReturnType()->getName());
        $this->assertCount(0, $handleMethod->getParameters());
        
        // Test handleCli method
        $handleCliMethod = $reflection->getMethod('handleCli');
        $this->assertEquals('int', $handleCliMethod->getReturnType()->getName());
        $this->assertCount(1, $handleCliMethod->getParameters());
        $this->assertEquals('argv', $handleCliMethod->getParameters()[0]->getName());
        $this->assertEquals('array', $handleCliMethod->getParameters()[0]->getType()->getName());
        
        // Test terminate method
        $terminateMethod = $reflection->getMethod('terminate');
        $this->assertEquals('void', $terminateMethod->getReturnType()->getName());
        $this->assertCount(1, $terminateMethod->getParameters());
        $this->assertEquals('response', $terminateMethod->getParameters()[0]->getName());
        $this->assertEquals(Response::class, $terminateMethod->getParameters()[0]->getType()->getName());
        
        // Test terminateCli method
        $terminateCliMethod = $reflection->getMethod('terminateCli');
        $this->assertEquals('void', $terminateCliMethod->getReturnType()->getName());
        $this->assertCount(1, $terminateCliMethod->getParameters());
        $this->assertEquals('statusCode', $terminateCliMethod->getParameters()[0]->getName());
        $this->assertEquals('int', $terminateCliMethod->getParameters()[0]->getType()->getName());
    }

    /**
     * Test that both kernel implementations follow the interface contract properly
     */
    public function testKernelContractCompliance(): void
    {
        $httpKernel = new HttpKernel();
        $consoleKernel = new ConsoleKernel();
        
        // Both should implement the interface
        $this->assertInstanceOf(KernelInterface::class, $httpKernel);
        $this->assertInstanceOf(KernelInterface::class, $consoleKernel);
        
        // Both should be able to bootstrap without throwing exceptions
        $httpKernel->bootstrap();
        $consoleKernel->bootstrap();
        
        // HttpKernel should throw for CLI methods
        try {
            $httpKernel->handleCli(['test']);
            $this->fail('Expected BadMethodCallException not thrown');
        } catch (\BadMethodCallException $e) {
            $this->assertTrue(true);
        }
        
        try {
            $httpKernel->terminateCli(0);
            $this->fail('Expected BadMethodCallException not thrown');
        } catch (\BadMethodCallException $e) {
            $this->assertTrue(true);
        }
        
        // ConsoleKernel should throw for HTTP methods
        try {
            $consoleKernel->handle();
            $this->fail('Expected BadMethodCallException not thrown');
        } catch (\BadMethodCallException $e) {
            $this->assertTrue(true);
        }
        
        try {
            $response = new Response(200, 'Test');
            $consoleKernel->terminate($response);
            $this->fail('Expected BadMethodCallException not thrown');
        } catch (\BadMethodCallException $e) {
            $this->assertTrue(true);
        }
        
        // Clean up Whoops handler
        $httpKernel->terminate(new Response(200, ''));
    }

    protected function tearDown(): void
    {
        // Clean up routes after each test
        RouteCollection::getInstance()->clear();
        
        // Clean up temporary directory
        if (is_dir($this->tempAppDir)) {
            $this->removeDirectory($this->tempAppDir);
        }
        
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}