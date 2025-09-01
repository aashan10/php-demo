<?php

declare(strict_types=1);

namespace Elementary\Kernel;

use Elementary\Config\ConfigBag;
use Elementary\Database\Connection;
use Elementary\DI\Container;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Http\Router;
use Elementary\Utils\FlashBag;
use Elementary\Utils\SessionBag;
use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;

class HttpKernel implements KernelInterface
{
    private Container $container;
    private Router $router;

    public function __construct()
    {
        // Constructor is empty, bootstrap will set up the container and router
    }

    public function bootstrap(): void
    {
        $this->container = new Container();

        // Register Whoops error handler for development
        $this->container->bind(ConfigBag::class, fn() => new ConfigBag(BASE_PATH . '/config')); // Temporarily bind ConfigBag to get env
        $config = $this->container->get(ConfigBag::class);

        if ($config->get('app.env') === 'development') {
            $whoops = new Run();
            $whoops->pushHandler(new PrettyPageHandler());
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

        // Bind Request to a factory
        $this->container->bind(Request::class, fn() => Request::createFromGlobals());

        // Load application-specific bindings from bootstrap.php
        $container = $this->container; // Make container available to included file
        require_once BASE_PATH . '/bootstrap.php';

        // Instantiate Router
        $this->router = new Router($this->container);

        // Load application routes
        $router = $this->router; // Make router available to included file
        require_once BASE_PATH . '/routes/web.php';
    }

    public function handle(): Response
    {
        if (!isset($this->router)) {
            $this->bootstrap(); // Ensure bootstrap is called if not already
        }
        $request = $this->container->get(Request::class);
        return $this->router->handle($request);
    }

    public function handleCli(array $argv): int
    {
        // Not applicable for HttpKernel
        throw new \BadMethodCallException("handleCli not implemented for HttpKernel");
    }

    public function terminate(Response $response): void
    {
        // Perform any cleanup or logging after response is sent
        // For now, nothing specific
    }

    public function terminateCli(int $statusCode): void
    {
        // Not applicable for HttpKernel
        throw new \BadMethodCallException("terminateCli not implemented for HttpKernel");
    }
}
