# Authentication

The Elementary framework provides a flexible and extensible authentication system to manage user logins, sessions, and access control.

## User Model & Interface

At the core of the authentication system is your application's User model. This model must implement the `Elementary\Authentication\UserInterface` and use the `Elementary\Authentication\Traits\Authenticatable` trait.

*   **`UserInterface`**: Defines the contract for an authenticatable user, requiring methods like `getId()`, `getUsername()`, and `getPasswordHash()`.
*   **`Authenticatable` Trait**: Provides a default implementation for the `UserInterface` methods, assuming standard `id`, `username`, and `password` columns. You can override these by setting static properties (`$idColumn`, `$usernameColumn`, `$passwordColumn`) in your User model.

Your application's `User` model (`App\Models\User`) should look something like this:

```php
// src/Models/User.php

namespace App\Models;

use Elementary\Authentication\Traits\Authenticatable;
use Elementary\Authentication\UserInterface;
use Elementary\Database\Model; // Assuming you've renamed AbstractModel to Model

final class User extends Model implements UserInterface
{
    use Authenticatable;

    protected static string $table = 'users';
    protected static string $usernameColumn = 'email'; // Use 'email' as the username
    protected static string $passwordColumn = 'password'; // Use 'password' for the hash

    // ... other properties and methods
}
```

## Authentication Configuration

The specific User model class that the framework should use for authentication is configured in `config/auth.php`:

```php
// config/auth.php

return [
    'model' => App\\Models\\User::class,
];
```

## Authentication Middleware

The framework provides several middleware components to handle different authentication mechanisms. These are typically applied to your routes via middleware groups (e.g., the `auth` group).

*   **`Elementary\Authentication\Middleware\AuthenticateSessionMiddleware`**:
    *   Checks for a `user_id` in the session.
    *   If found, it retrieves the user from the database using the configured user model.
    *   If the user is authenticated, it sets the `Request::$user` property.
    *   If not authenticated, it redirects to `/login`. This middleware is suitable for web-based authentication.

*   **`Elementary\Authentication\Middleware\AuthenticateCookieMiddleware`**:
    *   Checks for a persistent authentication cookie (e.g., `elementary_auth`).
    *   If found, it attempts to log the user in based on the cookie's contents (user ID and remember token).
    *   This is used for "Remember Me" functionality.

*   **`Elementary\Authentication\Middleware\AuthenticateApiMiddleware`**:
    *   (Currently a placeholder) Intended for API token-based authentication. It checks for an `x-elementray-api-token` header. You would extend this to validate the token against your user store.

You can apply these middleware to your routes:

```php
// routes/web.php

use Elementary\Routing\Router;

// Apply the 'auth' middleware group to a set of routes
Router::middleware('auth')->group(function() {
    Router::get('/dashboard', 'App\\Controllers\\DashboardController@index');
    Router::get('/profile', 'App\\Controllers\\ProfileController@show');
});
```

## Accessing the Authenticated User

Once a user is authenticated by one of the middleware components, the authenticated user object is available via the `Request::$user` property.

### In Controllers:

```php
// In your Controller method
public function showProfile(Request $request)
{
    if ($request->user) {
        $userId = $request->user->getId();
        $username = $request->user->getUsername();
        // ...
    }
    // ...
}
```

### In Templates:

The `app/cigg` layout already makes the authenticated user available via the `$auth` variable.

```html
{{-- templates/layouts/guest.cigg (or app.cigg) --}}

@if ($auth->check)
    <span>Welcome, {{ $auth->user->first_name }}</span>
    <a href="/logout">Logout</a>
@else
    <a href="/login">Login</a>
    <a href="/register">Register</a>
@endif
```

*   `$auth->check`: A boolean indicating if a user is currently logged in.
*   `$auth->user`: The authenticated user object (an instance of your `App\Models\User` class), or `null` if not logged in.

## Login and Registration Flow

The `App\Controllers\AuthController` handles the typical login and registration processes.

*   **Login**:
    *   Displays the login form (`/login`).
    *   Processes login credentials, authenticates the user, and sets the `user_id` in the session.
    *   Handles "Remember Me" functionality by setting a persistent cookie.
*   **Registration**:
    *   Displays the registration form (`/register`).
    *   Validates user input, creates a new user record, and logs the user in.

## "Remember Me" Functionality

The "Remember Me" checkbox on the login form, combined with the `AuthenticateCookieMiddleware`, allows users to remain logged in across browser sessions.

When a user checks "Remember Me" during login:
1.  A unique `remember_token` is generated and stored in the database for the user.
2.  A cookie (`elementary_auth`) containing the user's ID and this `remember_token` is set in the browser.
3.  On subsequent requests, if a session is not active, `AuthenticateCookieMiddleware` attempts to log the user in using this cookie.
