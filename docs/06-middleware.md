# Middleware

Middleware provides a convenient mechanism for filtering HTTP requests entering your application. For example, the `StartSession` middleware begins session handling, and the `TrimStrings` middleware cleans up input data before it reaches your controllers.

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
        if (!$request->headers->has('X-My-Header')) {
            return new Response(400, 'Bad Request: Missing header');
        }

        // Call the next middleware in the stack
        $response = $next($request);

        // Perform actions after the controller has handled the request.
        $response->headers->set('X-Handled-By', 'MyCustomMiddleware');

        return $response;
    }
}
```

## Registering Middleware

All middleware is registered in the `config/middleware.php` file. This file contains two important arrays: `aliases` and `groups`.

### Middleware Aliases

To make middleware easier to apply, you can give any middleware class an alias. This is useful for middleware that you might apply to individual routes.

```php
// config/middleware.php

return [
    'aliases' => [
        'auth' => \App\Middleware\Authenticate::class,
        'my-alias' => \App\Middleware\MyCustomMiddleware::class, // Add your alias here
    ],
    // ...
];
```

### Middleware Groups

Middleware groups allow you to bundle several middleware under a single key. This is useful for applying a common set of middleware to large groups of routes, such as all routes in the `web` or `api` group.

The framework comes with a `web` group by default, which includes middleware for starting sessions and trimming strings.

```php
// config/middleware.php

return [
    // ...
    'groups' => [
        'web' => [
            \App\Middleware\StartSession::class,
            \App\Middleware\TrimStrings::class,
        ],
        'api' => [
            // Add API-specific middleware here
        ]
    ]
];
```

## Applying Middleware to Routes

Once the middleware has been defined and registered, you can apply it to a route or a route group using its alias or group name.

```php
// routes/web.php

// Apply the 'web' group to all routes in this file
Router::middleware('web')->group(function () {

    // Apply an individual middleware alias to a single route
    Router::get('/profile', 'ProfileController@show')->middleware('auth');

    // Apply middleware to a route group
    Router::prefix('admin')->middleware('auth')->group(function () {
        Router::get('/dashboard', 'AdminController@dashboard');
    });
});
```
