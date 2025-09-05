# Routing

The routing for the Elementary framework is handled in the `routes/web.php` file. The router allows you to define endpoints for your application that map to specific controller actions or closures.

## Basic Routing

You can define routes for different HTTP methods like GET, POST, PUT, PATCH, and DELETE.

```php
// routes/web.php
use Elementary\Routing\Router;
use Elementary\Http\Response;

// A simple GET route returning a response directly
Router::get('/', function () {
    return new Response(200, '<h1>Homepage</h1>');
});

// A POST route mapping to a controller action
Router::post('/users', 'App\\Controllers\\UserController@store');
```

## Route Parameters

You can capture segments of the URI by defining parameters in your route. Parameters are enclosed in curly braces `{}`.

```php
Router::get('/users/{id}', 'App\\Controllers\\UserController@show');
```

These parameters are automatically added to the `Request` object's `attributes` bag and can be accessed in your controller method:

```php
// In UserController.php
public function show(Request $request) {
    $userId = $request->attributes->get('id');
    // ...
}
```

## Named Routes

Naming your routes makes it easy to generate URLs in the future. You can name a route by chaining the `name()` method.

```php
Router::get('/users', 'App\\Controllers\\UserController@index')->name('users.index');
```

## Route Groups

Route groups allow you to apply attributes like prefixes and middleware to multiple routes without having to define them on each individual route.

### Prefixes

To prefix all routes in a group with a specific URI, you can use the `prefix()` method.

```php
Router::prefix('admin')->group(function () {
    Router::get('/dashboard', function () {
        // Handles the /admin/dashboard URI
        return new Response(200, '<h1>Admin Dashboard</h1>');
    });
});
```

### Middleware

You can also apply middleware to a group of routes.

```php
Router::middleware('auth')->group(function () {
    Router::get('/profile', 'App\\Controllers\\ProfileController@show');
});
```

You can combine prefixes and middleware:

```php
Router::prefix('admin')->middleware('auth')->group(function () {
    Router::get('/dashboard', 'App\\Controllers\\AdminController@dashboard');
});
```

