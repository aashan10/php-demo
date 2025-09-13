# Controllers

Controllers are responsible for handling incoming requests, processing data, and returning a response. In the Elementary framework, controllers are located in the `src/Controllers` directory.

## Creating a Controller

A controller is a simple PHP class that typically extends the `Elementary\Http\Controller`. Extending the base controller provides access to helper methods, such as `render()` for rendering templates.

Here is an example of a basic controller:

```php
// src/Controllers/UserController.php

namespace App\Controllers;

use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Http\Controller;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = [/* ... array of users ... */];

        // The render method returns a Response object
        return $this->render('users/index', ['users' => $users]);
    }

    public function show(Request $request): Response
    {
        $userId = $request->attributes->get('id');
        // Fetch user from database...
        $user = ['name' => 'John Doe']; // Dummy data

        return $this->render('users/show', ['user' => $user]);
    }
}
```

## Controller Methods

Each public method in a controller can be mapped to a route. These methods receive the `Request` object, which gives you access to the request data (GET/POST parameters, headers, etc.). They should always return an instance of the `Elementary\Http\Response` class.

### Accessing Request Data

The `Request` object is automatically injected into your controller methods by the DI container. You can use it to access input data:

```php
public function store(Request $request): Response
{
    // Get all POST data
    $allPostData = $request->post->all();

    // Get a specific POST field
    $name = $request->post->get('name');

    // ... process data ...

    return new Response(201, 'User created');
}
```

### Returning Responses

Your controller methods must return a `Response` object. You can create a response with a status code and content directly, or you can use the `render()` helper to return a rendered template.

```php
// Return a simple text response
return new Response(200, '<h1>Hello</h1>');

// Return a JSON response
$data = ['status' => 'success'];
$json = json_encode($data);
$response = new Response(200, $json);
$response->headers->set('Content-Type', 'application/json');
return $response;

// Render a template (most common)
return $this->render('template/name', ['data' => $myData]);
```
