<?php

use Elementary\Config\ConfigBag;
use Elementary\Database\Connection;
use Elementary\DI\Container;
use Elementary\Database\Model;
use App\Repositories\UserRepository;
use App\Repositories\UserRepositoryInterface;
use Elementary\Template\Cigg\Engine as ElementaryEngine;
use Elementary\Template\Cigg\LayoutManager;
use Elementary\Utils\FlashBag;
use Elementary\Utils\SessionBag;
use Elementary\Template\Cigg\Lexer\Lexer;
use Elementary\Template\Cigg\Parser\Parser;
use Elementary\Template\Cigg\Directives\DirectiveRegistry;
use Elementary\Template\Cigg\Compiler\Compiler;
use Elementary\Validation\Validator;
use Elementary\Database\QueryBuilder;
use Elementary\Utils\EncryptionService;
use Elementary\Session\SessionManager;

Validator::macro('validateName', function ($data) {
    /** @var Validator $this */
    [$field, $value,  $params ] = $data;

    $parts = explode(' ', $value);

    if (count($parts) < 2) {
        $this->addError($field, 'Name must be at least two words long!' );
    }
});


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

// Bind Query Builder
$container->bind(QueryBuilder::class, fn(Container $c) => new QueryBuilder($c->get(Connection::class)->getInstance()));

// Bind EncryptionService
$container->bind(EncryptionService::class, fn(Container $c) => new EncryptionService($c->get(ConfigBag::class)));

// Bind SessionManager
$container->bind(SessionManager::class, fn(Container $c) => new SessionManager($c->get(ConfigBag::class), $c->get(Connection::class)));



// Bind LayoutManager
$container->bind(LayoutManager::class, LayoutManager::class);

// Bind Template Engine
$container->bind(ElementaryEngine::class, function (Container $c): ElementaryEngine {
    $engine = new ElementaryEngine(
        $c->get(ConfigBag::class),
        $c->get(Lexer::class),
        $c->get(Parser::class),
        $c->get(Compiler::class),
        $c->get(LayoutManager::class),
        $c
    );

    $engine->addGlobal('app_name', $c->get(ConfigBag::class)->get('app.name', 'MyApp'));

    /** @var DirectiveRegistry $registry */
    $registry = $c->get(DirectiveRegistry::class); // Ensure directives are registered
    foreach ($registry->getAllDirectives() as $directive) {
        $directive->setEngine($engine);
    }

    return $engine;
});
// Bind DirectiveRegistry with configured directives
$container->bind(DirectiveRegistry::class, function(Container $c) {
    $registry = new DirectiveRegistry();
    $config = $c->get(ConfigBag::class);
    $directives = $config->get('template.directives', []);

    foreach ($directives as &$directiveClass) {
        // The container will create the directive instance if it's not already bound
        $directiveInstance = $c->get($directiveClass);
        $registry->register($directiveInstance);
    }

    return $registry;
});



Model::setContainer($container);
