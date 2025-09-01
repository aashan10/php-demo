<?php

use Elementary\Config\ConfigBag;
use Elementary\Database\Connection;
use Elementary\DI\Container;
use App\Models\AbstractModel;
use App\Repositories\UserRepository;
use App\Repositories\UserRepositoryInterface;
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

// Set the container on the AbstractModel for static access
AbstractModel::setContainer($container);
