<?php

declare(strict_types=1);

namespace Elementary\Kernel;

use Elementary\Config\ConfigBag;
use Elementary\Console\Commands\SessionCleanCommand;
use Elementary\Console\Commands\TemplateCompileCommand;
use Elementary\Database\Connection;
use Elementary\DI\Container;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Http\Router;
// Removed App\Models\AbstractModel, App\Repositories\UserRepository, App\Repositories\UserRepositoryInterface
use Elementary\Utils\FlashBag;
use Elementary\Utils\SessionBag;
use Elementary\Utils\Traits\BetterTry;
use ReflectionClass;
use Whoops\Run;
use Whoops\Handler\PlainTextHandler;

class ConsoleKernel implements KernelInterface
{
    use BetterTry;
    private Container $container;
    private array $cliCommands = [];

    public function __construct()
    {
        $this->cliCommands = [
            $this->getCommandName(TemplateCompileCommand::class) => TemplateCompileCommand::class,
            $this->getCommandName(SessionCleanCommand::class)    => SessionCleanCommand::class,
        ];
        // Constructor is empty, bootstrap will set up the container
    }

    public function bootstrap(): void
    {
        $this->container = new Container();

        // Register Whoops error handler for development
        $this->container->bind(ConfigBag::class, fn() => new ConfigBag(BASE_PATH . '/config')); // Temporarily bind ConfigBag to get env
        $config = $this->container->get(ConfigBag::class);

        if ($config->get('app.env') === 'development') {
            $whoops = new Run();
            $whoops->pushHandler(new PlainTextHandler());
            $whoops->register();
        }
        // End Whoops registration

        // Bind framework-level components
        // ConfigBag is already bound above
        $this->container->bind(ConfigBag::class, fn() => $config); // Re-bind the instance

        $session = new SessionBag();
        $this->container->bind(SessionBag::class, fn() => $session);

        $flashBag = new FlashBag($session);
        $this->container->bind(FlashBag::class, fn() => $flashBag);

        $this->container->bind(Connection::class, fn(Container $c) => new Connection($c->get(ConfigBag::class)));

        // Load application-specific bindings from bootstrap.php
        $container = $this->container; // Make container available to included file
        require_once BASE_PATH . '/bootstrap.php';

        // Discover CLI commands
        $this->discoverCommands();
    }

    private function discoverCommands(): void
    {
        $commandPath = BASE_PATH . '/src/Console/Commands';
        if (!is_dir($commandPath)) {
            return; // No commands directory
        }

        $files = glob($commandPath . '/*.php');
        foreach ($files as $file) {
            $className = basename($file, '.php');
            $fqcn = 'App\\Console\\Commands\\' . $className;

            if (class_exists($fqcn)) {
                [$command, $error] = $this->try(fn() => $this->getCommandName($fqcn));
                if (!$error && $command) {
                    $this->cliCommands[$command] = $fqcn;
                }
            }
        }
    }
    private function getCommandName(string $fqcn): string 
    {
        $reflectionClass = new ReflectionClass($fqcn);
        if ($reflectionClass->hasProperty('signature') && $reflectionClass->getProperty('signature')->isStatic()) {
            $commandName = $reflectionClass->getStaticPropertyValue('signature');
            [$command] = explode(' ', $commandName); // Validate format
            return $command;
        }
        throw new \InvalidArgumentException("Class {$fqcn} does not have a valid static 'signature' property.");
    }

    private function displayHelp(?string $commandClass = null): void
    {
        if (null === $commandClass) {
            $this->displayAppHelp();
            return;
        }
        $fqcn = $this->cliCommands[$commandClass] ?? null;
        if ($fqcn === null) {
            echo "Error: Unknown command '{$commandClass}'.\n\n";
            $this->displayAppHelp();
            return;
        }
        $reflectionClass = new ReflectionClass($fqcn);
        if ($reflectionClass->hasProperty('description') && $reflectionClass->getProperty('description')->isStatic()) {
            $description = $reflectionClass->getStaticPropertyValue('description');
        } else {
            $description = 'No description available.';
        }
        $signature = $reflectionClass->getStaticPropertyValue('signature');
        echo "Usage: elementary {$signature}\n\n";
        echo "{$description}\n";
    }

    private function displayAppHelp(): void 
    {
        echo "Usage: elementary <command>\n\n";
        echo "Available commands:\n";
        ksort($this->cliCommands); // Sort commands alphabetically
        foreach ($this->cliCommands as $name => $fqcn) {
            echo "  - {$name}\n";
        }
    }

    public function handle(): Response
    {
        // Not applicable for ConsoleKernel
        throw new \BadMethodCallException("handle not implemented for ConsoleKernel");
    }

    public function handleCli(array $argv): int
    {
        if (!isset($this->container)) {
            $this->bootstrap();
        }

        $commandName = $argv[1] ?? null;

        // Handle help command or no command
        if ($commandName === null || $commandName === 'help') {
            $this->displayHelp();
            return 0; // Success
        }

        $help = false;

        foreach ($argv as $arg) {
            if (in_array($arg, ['-h', '--help'], true)) {
                $help = true;
                break;
            }
        }
        if ($help) {
            $this->displayHelp($commandName);
            return 0; // Success
        }


        $commandClass = $this->cliCommands[$commandName] ?? null;

        if ($commandClass === null) {
            echo "Error: Unknown command '{$commandName}'.\n\n";
            $this->displayHelp();
            return 1; // Error
        }


        try {
            /** @var object $command */
            $command = $this->container->get($this->cliCommands[$commandName]);
            $startTime  = microtime(true);
            $statusCode = $command->execute(array_slice($argv, 2));
            $endtime    = microtime(true);
            $duration   = $endtime - $startTime;
            echo "\nExecution time: " . number_format($duration, 4) . " seconds\n";
            return $statusCode;
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage() . "\n";
            return 1;
        }
    }

    public function terminate(Response $response): void
    {
        // Not applicable for ConsoleKernel
        throw new \BadMethodCallException("terminate not implemented for ConsoleKernel");
    }

    public function terminateCli(int $statusCode): void
    {
        // Perform any cleanup or logging after CLI command
        // For now, nothing specific
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}
