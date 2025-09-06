<?php

// session_start(); // This is now handled by the StartSession middleware

require_once __DIR__ . '/../constants.php';

require_once BASE_PATH . '/vendor/autoload.php';

use Elementary\Kernel\HttpKernel;
// Removed use Elementary\Http\Request;

$kernel = new HttpKernel();
$kernel->bootstrap();

$response = $kernel->handle(); // No Request parameter
$response->send();
$kernel->terminate($response); // No Request parameter
