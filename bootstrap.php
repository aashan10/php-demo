<?php

use Elementary\Config\ConfigBag;
use Elementary\Database\Connection;
use Elementary\DI\Container;
use App\Models\AbstractModel;
use App\Repositories\UserRepository;
use App\Repositories\UserRepositoryInterface;
use Elementary\Template\Cigg\Compiler\Compiler;
use Elementary\Template\Cigg\Engine;
use Elementary\Template\Cigg\Lexer\Lexer;
use Elementary\Template\Cigg\Parser\Parser;
use Elementary\Utils\FlashBag;
use Elementary\Utils\SessionBag;

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



$container->bind(Engine::class, function (Container $c): Engine {
    $engine = new Engine(
        $c->get(ConfigBag::class),
        $c->get(Lexer::class),
        $c->get(Parser::class),
        $c->get(Compiler::class)
    );

    $engine->addGlobal('app_name', $c->get(ConfigBag::class)->get('app.name', 'MyApp'));

    return $engine;
});


AbstractModel::setContainer($container);
