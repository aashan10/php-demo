<?php

use Elementary\Routing\Router;
use Elementary\Http\Request;
use Elementary\Http\Response;

Router::middleware('web')->group(function () {
    Router::get('/', function () {
        return new Response(200, '<h1>Hello from the new Routing Layer!</h1>');
    })->name('home');

    Router::post('/test-middleware', function (Request $request) {
        dd($request->post->all());
    })->name('test.middleware'); // 'trim' middleware is now applied via the 'web' group

    Router::prefix('admin')->middleware('auth')->group(function () {
        Router::get('/dashboard', function () {
            return new Response(200, '<h1>Admin Dashboard</h1>');
        })->name('admin.dashboard');
    });

    Router::get('/users', 'App\\Controllers\\UserController@index')->name('users.index');
});

