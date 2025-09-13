<?php

use App\Controllers\AuthController;
use App\Controllers\PagesController;
use App\Controllers\UserController;
use Elementary\Routing\Router;
use Elementary\Http\Request;
use Elementary\Http\Response;


// Authentication routes
Router::get('/login', AuthController::class . '@showLoginForm')->name('login');
Router::post('/login', AuthController::class . '@login');
Router::get('/register', AuthController::class . '@showRegistrationForm')->name('register');
Router::post('/register', AuthController::class . '@register');
Router::get('/logout', AuthController::class . '@logout')->name('logout')->middleware('auth');


Router::middleware('auth')->group(function() {
    Router::get('/', PagesController::class . '@home')->name('home');

    Router::get('/users', UserController::class . '@index')->name('users.index');
    Router::get('/users/create', UserController::class . '@create')->name('users.create');
});
