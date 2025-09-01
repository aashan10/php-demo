<?php

define('TEMPLATE_PATH', __DIR__ . '/../templates/');
define('UPLOAD_PATH', __DIR__ . "/uploads/");

require_once __DIR__ . '/../vendor/autoload.php';


use App\Controllers\LoginController;
use App\Controllers\PagesController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;

$request = Request::createFromGlobals();


$router = new Router();

require_once __DIR__ . '/../routes/web.php';


$response = $router->handle($request);

$response->send();
