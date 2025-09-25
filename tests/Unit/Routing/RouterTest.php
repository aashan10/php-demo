<?php

declare(strict_types=1);

namespace Tests\Unit\Routing;

use Elementary\Routing\Router;
use Elementary\Routing\Route;
use Elementary\Routing\RouteCollection;
use Tests\Support\TestCase;

class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RouteCollection::getInstance()->clear();
    }

    protected function tearDown(): void
    {
        RouteCollection::getInstance()->clear();
        parent::tearDown();
    }

    public function testCanCreateGetRoute(): void
    {
        $route = Router::get('/test', 'TestController@index');
        
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals(['GET', 'HEAD'], $route->methods);
        $this->assertEquals('/test', $route->getUri());
    }

    public function testCanCreatePostRoute(): void
    {
        $route = Router::post('/test', 'TestController@store');
        
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals(['POST'], $route->methods);
        $this->assertEquals('/test', $route->getUri());
    }

    public function testCanCreatePutRoute(): void
    {
        $route = Router::put('/test', 'TestController@update');
        
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals(['PUT'], $route->methods);
        $this->assertEquals('/test', $route->getUri());
    }

    public function testCanCreateDeleteRoute(): void
    {
        $route = Router::delete('/test', 'TestController@destroy');
        
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals(['DELETE'], $route->methods);
        $this->assertEquals('/test', $route->getUri());
    }

    public function testCanCreatePatchRoute(): void
    {
        $route = Router::patch('/test', 'TestController@patch');
        
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals(['PATCH'], $route->methods);
        $this->assertEquals('/test', $route->getUri());
    }

    public function testCanCreateRouteWithMultipleMethods(): void
    {
        $route = Router::newRoute(['GET', 'POST'], '/test', 'TestController@index');
        
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals(['GET', 'POST'], $route->methods);
        $this->assertEquals('/test', $route->getUri());
    }

    public function testCanCreateRouteWithAllMethods(): void
    {
        $route = Router::any('/test', 'TestController@index');
        
        $this->assertInstanceOf(Route::class, $route);
        $expectedMethods = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $this->assertEquals($expectedMethods, $route->methods);
    }

    public function testCanCreateNamedRoute(): void
    {
        $route = Router::get('/test', 'TestController@index')->name('test.index');
        
        $this->assertEquals('test.index', $route->name);
    }

    public function testCanCreateRouteWithMiddleware(): void
    {
        $route = Router::get('/test', 'TestController@index')->middleware('auth');
        
        $this->assertContains('auth', $route->middleware);
    }

    public function testCanCreateRouteWithMultipleMiddleware(): void
    {
        $route = Router::get('/test', 'TestController@index')
            ->middleware(['auth', 'admin']);
        
        $middleware = $route->middleware;
        $this->assertContains('auth', $middleware);
        $this->assertContains('admin', $middleware);
    }

    public function testCanCreateRouteGroup(): void
    {
        Router::group(['prefix' => 'api'], function() {
            Router::get('/users', 'UserController@index');
            Router::post('/users', 'UserController@store');
        });
        
        $routes = RouteCollection::getInstance()->all();
        
        $this->assertCount(2, $routes);
        $this->assertEquals('/api/users', $routes[0]->getUri());
        $this->assertEquals('/api/users', $routes[1]->getUri());
        $this->assertEquals(['GET', 'HEAD'], $routes[0]->methods);
        $this->assertEquals(['POST'], $routes[1]->methods);
    }

    public function testCanCreateNestedRouteGroups(): void
    {
        Router::group(['prefix' => 'api'], function() {
            Router::group(['prefix' => 'v1'], function() {
                Router::get('/users', 'UserController@index');
            });
        });
        
        $routes = RouteCollection::getInstance()->all();
        
        $this->assertCount(1, $routes);
        $this->assertEquals('/api/v1/users', $routes[0]->getUri());
    }

    public function testCanCreateRouteGroupWithMiddleware(): void
    {
        Router::middleware('auth')->group(function() {
            Router::get('/dashboard', 'DashboardController@index');
            Router::get('/profile', 'ProfileController@show');
        });
        
        $routes = RouteCollection::getInstance()->all();
        
        $this->assertCount(2, $routes);
        $this->assertContains('auth', $routes[0]->middleware);
        $this->assertContains('auth', $routes[1]->middleware);
    }

    public function testCanCreateRouteGroupWithMultipleMiddleware(): void
    {
        Router::middleware(['auth', 'verified'])->group(function() {
            Router::get('/admin', 'AdminController@index');
        });
        
        $routes = RouteCollection::getInstance()->all();
        
        $this->assertCount(1, $routes);
        $middleware = $routes[0]->middleware;
        $this->assertContains('auth', $middleware);
        $this->assertContains('verified', $middleware);
    }

    public function testRouteGroupsStackCorrectly(): void
    {
        Router::middleware('auth')->group(function() {
            Router::middleware('admin')->group(function() {
                Router::get('/admin/users', 'AdminController@users');
            });
        });
        
        $routes = RouteCollection::getInstance()->all();
        
        $this->assertCount(1, $routes);
        $middleware = $routes[0]->middleware;
        $this->assertContains('auth', $middleware);
        $this->assertContains('admin', $middleware);
    }

    public function testRoutesAreAddedToCollection(): void
    {
        Router::get('/test1', 'TestController@test1');
        Router::post('/test2', 'TestController@test2');
        
        $routes = RouteCollection::getInstance()->all();
        
        $this->assertCount(2, $routes);
        $this->assertEquals('/test1', $routes[0]->getUri());
        $this->assertEquals('/test2', $routes[1]->getUri());
    }
}
