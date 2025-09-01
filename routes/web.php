<?php

use App\Http\Router;
use App\Http\Request;
use App\Http\Response;
use App\Controllers\LoginController;
use App\Controllers\PagesController;
use App\Controllers\RegisterController;
use App\Controllers\GithubController;


/** @var Router $router */
$router->get('/login', LoginController::class . '@showLoginPage');
$router->post('/login', LoginController::class . '@loginUser');



$router->get('/Register', RegisterController::class . '@showRegisterPage');
$router->post('/Register', RegisterController::class . '@registerUser');

$router->get('/Profile',GithubController::class.'@githubRegisterPage');
$router->post('/Profile',GithubController::class.'@githubUser');


$router->get('/amrit', PagesController::class . '@homePage');

$router->get('/',PagesController::class.'@homePage');


// $router->get('/', function (Request $request): Response {
//     return new Response(200, '');
// });