<?php

declare(strict_types=1);

namespace Tests\Support;

use Elementary\DI\Container;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Define BASE_PATH constant if not already defined
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', dirname(__DIR__, 2));
        }
        
        // Define other constants if not already defined
        if (!defined('TEMPLATE_PATH')) {
            define('TEMPLATE_PATH', BASE_PATH . '/templates');
        }
        if (!defined('CACHE_PATH')) {
            define('CACHE_PATH', BASE_PATH . '/cache');
        }
        if (!defined('PUBLIC_PATH')) {
            define('PUBLIC_PATH', BASE_PATH . '/public');
        }
        
        $this->container = new Container();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}