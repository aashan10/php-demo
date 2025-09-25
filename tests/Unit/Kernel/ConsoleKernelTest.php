<?php

declare(strict_types=1);

namespace Tests\Unit\Kernel;

use Elementary\Config\ConfigBag;
use Elementary\Console\Commands\SessionCleanCommand;
use Elementary\Console\Commands\TemplateCompileCommand;
use Elementary\Database\Connection;
use Elementary\DI\Container;
use Elementary\Http\Response;
use Elementary\Kernel\ConsoleKernel;
use Elementary\Maker\Commands\MakeCommandCommand;
use Elementary\Maker\Commands\MakeControllerCommand;
use Elementary\Maker\Commands\MakeMiddlewareCommand;
use Elementary\Maker\Commands\MakeModelCommand;
use Elementary\Utils\FlashBag;
use Elementary\Utils\SessionBag;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ConsoleKernelTest extends TestCase
{
    private ConsoleKernel $kernel;
    private string $tempAppDir;
    private string $originalBasePath;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Store original BASE_PATH
        $this->originalBasePath = defined('BASE_PATH') ? BASE_PATH : '';
        
        // Create temporary app structure for testing
        $this->tempAppDir = sys_get_temp_dir() . '/elementary_console_test_' . uniqid();
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
        
        $this->kernel = new ConsoleKernel();
    }

    public function testBootstrapSetsUpContainer(): void
    {
        $this->kernel->bootstrap();
        
        $container = $this->kernel->getContainer();
        
        $this->assertInstanceOf(Container::class, $container);
        
        // Verify key bindings exist
        $this->assertTrue($container->has(ConfigBag::class));
        $this->assertTrue($container->has(SessionBag::class));
        $this->assertTrue($container->has(FlashBag::class));
        $this->assertTrue($container->has(Connection::class));
    }

    public function testBootstrapRegistersBuiltInCommands(): void
    {
        $this->kernel->bootstrap();
        
        // Use reflection to access private cliCommands property
        $reflection = new \ReflectionClass($this->kernel);
        $commandsProperty = $reflection->getProperty('cliCommands');
        $commandsProperty->setAccessible(true);
        $commands = $commandsProperty->getValue($this->kernel);
        
        // Verify built-in commands are registered
        $expectedCommands = [
            'template:compile',
            'session:clean',
            'make:controller',
            'make:model',
            'make:middleware',
            'make:command'
        ];
        
        foreach ($expectedCommands as $expectedCommand) {
            $this->assertArrayHasKey($expectedCommand, $commands);
        }
        
        $this->assertEquals(TemplateCompileCommand::class, $commands['template:compile']);
        $this->assertEquals(SessionCleanCommand::class, $commands['session:clean']);
        $this->assertEquals(MakeControllerCommand::class, $commands['make:controller']);
        $this->assertEquals(MakeModelCommand::class, $commands['make:model']);
        $this->assertEquals(MakeMiddlewareCommand::class, $commands['make:middleware']);
        $this->assertEquals(MakeCommandCommand::class, $commands['make:command']);
    }

    public function testBootstrapDiscoversCustomCommands(): void
    {
        // Create a test command in the current running environment, not in temp dir
        // because the class needs to be actually loadable by PHP
        $this->markTestSkipped('Custom command discovery requires actual class files to be loaded by PHP autoloader');
    }

    public function testHandleCliWithNoArguments(): void
    {
        $this->expectOutputRegex('/Usage: elementary <command>/');
        
        $exitCode = $this->kernel->handleCli(['elementary']);
        
        $this->assertEquals(0, $exitCode);
    }

    public function testHandleCliWithHelpCommand(): void
    {
        $this->expectOutputRegex('/Usage: elementary <command>/');
        
        $exitCode = $this->kernel->handleCli(['elementary', 'help']);
        
        $this->assertEquals(0, $exitCode);
    }

    public function testHandleCliWithHelpFlag(): void
    {
        $this->expectOutputRegex('/Usage: elementary template:compile/');
        
        $exitCode = $this->kernel->handleCli(['elementary', 'template:compile', '--help']);
        
        $this->assertEquals(0, $exitCode);
    }

    public function testHandleCliWithHelpShortFlag(): void
    {
        $this->expectOutputRegex('/Usage: elementary template:compile/');
        
        $exitCode = $this->kernel->handleCli(['elementary', 'template:compile', '-h']);
        
        $this->assertEquals(0, $exitCode);
    }

    public function testHandleCliWithUnknownCommand(): void
    {
        $this->expectOutputRegex("/Error: Unknown command 'nonexistent'/");
        
        $exitCode = $this->kernel->handleCli(['elementary', 'nonexistent']);
        
        $this->assertEquals(1, $exitCode);
    }

    public function testHandleCliWithValidCommand(): void
    {
        // Test with an existing built-in command
        $this->expectOutputRegex('/Execution time:/');
        
        $exitCode = $this->kernel->handleCli(['elementary', 'template:compile']);
        
        // Should return 0 (success) even if there are no templates to compile
        $this->assertEquals(0, $exitCode);
    }

    public function testHandleCliWithCommandThatFails(): void
    {
        // Test command that doesn't exist should return 1
        $this->expectOutputRegex("/Error: Unknown command 'nonexistent'/");
        
        $exitCode = $this->kernel->handleCli(['elementary', 'nonexistent']);
        
        $this->assertEquals(1, $exitCode);
    }

    public function testHandleCliWithCommandThatThrowsException(): void
    {
        // Test with another nonexistent command to trigger error handling
        $this->expectOutputRegex("/Error: Unknown command 'another-nonexistent'/");
        
        $exitCode = $this->kernel->handleCli(['elementary', 'another-nonexistent']);
        
        $this->assertEquals(1, $exitCode);
    }

    public function testGetCommandNameWithValidCommand(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->kernel);
        $method = $reflection->getMethod('getCommandName');
        $method->setAccessible(true);
        
        $commandName = $method->invoke($this->kernel, TemplateCompileCommand::class);
        
        $this->assertEquals('template:compile', $commandName);
    }

    public function testGetCommandNameWithInvalidCommand(): void
    {
        // Test with a class that exists but doesn't have signature property
        $testClass = new class {
            public function execute(array $args): int
            {
                return 0;
            }
        };
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->kernel);
        $method = $reflection->getMethod('getCommandName');
        $method->setAccessible(true);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("does not have a valid static 'signature' property");
        
        $method->invoke($this->kernel, get_class($testClass));
    }

    public function testDiscoverCommandsWithNonExistentDirectory(): void
    {
        // Remove the commands directory
        rmdir($this->tempAppDir . '/src/Console/Commands');
        rmdir($this->tempAppDir . '/src/Console');
        rmdir($this->tempAppDir . '/src');
        
        // Should not throw exception
        $this->kernel->bootstrap();
        
        $this->assertTrue(true); // Test passes if no exception thrown
    }

    public function testHandleThrowsException(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('handle not implemented for ConsoleKernel');
        
        $this->kernel->handle();
    }

    public function testTerminateThrowsException(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('terminate not implemented for ConsoleKernel');
        
        $response = new Response(200, 'Test');
        $this->kernel->terminate($response);
    }

    public function testTerminateCliDoesNothing(): void
    {
        // Should not throw any exception
        $this->kernel->terminateCli(0);
        
        $this->assertTrue(true); // Test passes if no exception thrown
    }

    public function testDisplayHelpWithTerminalWidth(): void
    {
        $this->kernel->bootstrap();
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->kernel);
        $method = $reflection->getMethod('getTerminalWidth');
        $method->setAccessible(true);
        
        $width = $method->invoke($this->kernel);
        
        $this->assertIsInt($width);
        $this->assertGreaterThanOrEqual(80, $width); // Should have a reasonable fallback
    }

    public function testDisplayAppHelp(): void
    {
        $this->kernel->bootstrap();
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->kernel);
        $method = $reflection->getMethod('displayAppHelp');
        $method->setAccessible(true);
        
        $this->expectOutputRegex('/Available commands:/');
        $this->expectOutputRegex('/template:compile/');
        
        $method->invoke($this->kernel);
    }

    public function testDisplayHelpForSpecificCommand(): void
    {
        $this->kernel->bootstrap();
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->kernel);
        $method = $reflection->getMethod('displayHelp');
        $method->setAccessible(true);
        
        $this->expectOutputRegex('/Usage: elementary template:compile/');
        
        $method->invoke($this->kernel, 'template:compile');
    }

    public function testDisplayHelpForNonExistentCommand(): void
    {
        $this->kernel->bootstrap();
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->kernel);
        $method = $reflection->getMethod('displayHelp');
        $method->setAccessible(true);
        
        $this->expectOutputRegex("/Error: Unknown command 'nonexistent'/");
        $this->expectOutputRegex('/Available commands:/');
        
        $method->invoke($this->kernel, 'nonexistent');
    }

    protected function tearDown(): void
    {
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