<?php

// session_start(); // This is now handled by the StartSession middleware

define('BASE_PATH', __DIR__ . '/..');
define('TEMPLATE_PATH', BASE_PATH . '/templates');
define('CACHE_PATH', BASE_PATH . '/cache');
define('PUBLIC_PATH', __DIR__);

require_once BASE_PATH . '/vendor/autoload.php';

use Elementary\Kernel\HttpKernel;
// Removed use Elementary\Http\Request;

$kernel = new HttpKernel();
$kernel->bootstrap();

$response = $kernel->handle(); // No Request parameter
$response->send();
$kernel->terminate($response); // No Request parameter
