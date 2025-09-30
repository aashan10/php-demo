<?php

use App\Controllers\AuthController;
use App\Controllers\PagesController;
use App\Controllers\PostController;
use App\Controllers\UserController;
use Elementary\Routing\Router;

// Authentication routes
Router::middleware('guest')->group(function() {
    Router::get('/login', AuthController::class . '@showLoginForm')->name('login');
    Router::post('/login', AuthController::class . '@login');
    Router::get('/register', AuthController::class . '@showRegistrationForm')->name('register');
    Router::post('/register', AuthController::class . '@register');
});

Router::middleware('auth')->group(function() {
    Router::get('/logout', AuthController::class . '@logout')->name('logout');
});

// Public Blog Routes
Router::get('/posts', PostController::class . '@index')->name('posts.index');
Router::get('/posts/{id}', PostController::class . '@show')->name('posts.show');


Router::middleware('auth')->group(function() {
    Router::get('/', PagesController::class . '@home')->name('home');

    Router::get('/users', UserController::class . '@index')->name('users.index');
    Router::post('/users/create', UserController::class . '@store')->name('users.store');
    Router::get('/users/create', UserController::class . '@create')->name('users.create');

    // Admin Blog Routes
    Router::get('/admin/posts/create', PostController::class . '@create')->name('posts.create');
    Router::post('/admin/posts', PostController::class . '@store')->name('posts.store');
});

// Spark test route (accessible to all)
Router::get('/spark-test', App\Controllers\SparkTestController::class. '@index');
