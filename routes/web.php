<?php

use Elementary\Http\Router;
use Elementary\Http\Request;
use Elementary\Http\Response;
use App\Controllers\LoginController;
use App\Controllers\PagesController;
use App\Controllers\RegisterController;

/** @var Router $router */

$router->get('/',PagesController::class.'@homePage');
$router->get('/login', LoginController::class . '@showLoginPage');
$router->post('/login', LoginController::class . '@loginUser');



$router->get('/Register', RegisterController::class . '@showRegisterPage');
$router->post('/Register', RegisterController::class . '@registerUser');




$router->get('/amrit', PagesController::class . '@homePage');



$router->get('/profile', PagesController::class . '@profilePage');
$router->post('/profile', PagesController::class . '@updateProfile');

$router->get('/user/{id}', PagesController::class . '@showUserProfile');

// $router->get('/', function (Request $request): Response {
//     return new Response(200, '');
// });
