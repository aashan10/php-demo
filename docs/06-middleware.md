# Middleware

Middleware provides a convenient mechanism for filtering HTTP requests entering your application. For example, the Elementary framework includes middleware that verifies if the user of your application is authenticated. If the user is not authenticated, the middleware will redirect the user to the login screen. However, if the user is authenticated, the middleware will allow the request to proceed further into the application.

## Creating Middleware

To create a new middleware, create a class that implements the `Elementary\Http\Middleware\MiddlewareInterface`. The most important method is `process()`.

```php
// src/Middleware/MyCustomMiddleware.php

namespace App\Middleware;

use Elementary\Http\Middleware\MiddlewareInterface;
use Elementary\Http\Request;
use Elementary\Http\Response;

class MyCustomMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        // Perform actions before the request is handled by the controller.
        // For example, check for a specific header.
        if (!$request->headers->has('X-My-Header')) {
            return new Response(400, 'Bad Request: Missing header');
        }

        // Call the next middleware in the stack
        $response = $next($request);

        // Perform actions after the controller has handled the request.
        // For example, add a header to the response.
        $response->headers->set('X-Handled-By', 'MyCustomMiddleware');

        return $response;
    }
}
```

## Registering Middleware

To use a middleware, you must first give it a short-hand key in the `$routeMiddleware` property of the `Elementary\Kernel\HttpKernel` class.

```php
// lib/Elementary/Kernel/HttpKernel.php

class HttpKernel implements KernelInterface
{
    protected array $routeMiddleware = [
        'trim' => TrimStrings::class,
        'auth' => Authenticate::class,
        'my-key' => \App\Middleware\MyCustomMiddleware::class, // Add your middleware here
    ];

    // ...
}
```

## Applying Middleware to Routes

Once the middleware has been defined and registered, you can apply it to a route or a route group using its key.

```php
// routes/web.php

// Apply to a single route
Router::get('/secret-data', 'SomeController@getData')->middleware('my-key');

// Apply to a route group
Router::prefix('api')->middleware('my-key')->group(function () {
    Router::get('/users', 'ApiController@users');
});
```
