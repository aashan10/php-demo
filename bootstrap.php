<?php

use Elementary\Config\ConfigBag;
use Elementary\Database\Connection;
use Elementary\DI\Container;
use App\Models\AbstractModel;
use App\Repositories\UserRepository;
use App\Repositories\UserRepositoryInterface;
use Elementary\Template\Cigg\Engine as ElementaryEngine;
use Elementary\Utils\FlashBag;
use Elementary\Utils\SessionBag;
use Elementary\Template\Cigg\Lexer\Lexer;
use Elementary\Template\Cigg\Parser\Parser;
use Elementary\Template\Cigg\Directives\DirectiveRegistry;
use Elementary\Template\Cigg\Compiler\Compiler;

/** @var Container $container */

// Bind ConfigBag
$config = new ConfigBag(BASE_PATH . '/config');
$container->bind(ConfigBag::class, fn() => $config);

// Bind SessionBag
$session = new SessionBag();
$container->bind(SessionBag::class, fn() => $session);

// Bind FlashBag
$flashBag = new FlashBag($session);
$container->bind(FlashBag::class, fn() => $flashBag);

// Bind Connection (using a factory to ensure ConfigBag is available)
$container->bind(Connection::class, fn(Container $c) => new Connection($c->get(ConfigBag::class)));

// Bind User Repository Interface
$container->bind(UserRepositoryInterface::class, UserRepository::class);

// Bind DirectiveRegistry with configured directives
$container->bind(DirectiveRegistry::class, function(Container $c) {
    $registry = new DirectiveRegistry();
    $config = $c->get(ConfigBag::class);
    $directives = $config->get('template.directives', []);

    foreach ($directives as $directiveClass) {
        // The container will create the directive instance if it's not already bound
        $directiveInstance = $c->get($directiveClass);
        $registry->register($directiveInstance);
    }

    return $registry;
});

// Bind Template Engine
$container->bind(ElementaryEngine::class, function (Container $c): ElementaryEngine {
    $engine = new ElementaryEngine(
        $c->get(ConfigBag::class),
        $c->get(Lexer::class),
        $c->get(Parser::class),
        $c->get(Compiler::class)
    );

    $engine->addGlobal('app_name', $c->get(ConfigBag::class)->get('app.name', 'MyApp'));

    return $engine;
});


AbstractModel::setContainer($container);