# Cookies and Sessions

Web applications often need to store information about the user or the state of their interaction across multiple requests. The Elementary framework provides robust mechanisms for managing both cookies and sessions.

## Sessions

Sessions allow you to store user-specific data on the server, identified by a unique session ID that is typically stored in a cookie on the user's browser. This makes sessions more secure for sensitive data than cookies alone.

### Session Management

Session handling in Elementary is primarily managed by the `Elementary\Http\Middleware\StartSession` middleware. This middleware is responsible for starting the PHP session and loading the session data based on the configured driver.

### Session Drivers

Elementary supports two main session drivers, configurable in `config/session.php`:

*   **`file` driver**: (Default) Stores session data in files on the server's filesystem. This is simple and suitable for most small to medium-sized applications.
    *   **Configuration**: `session.files` (path to store session files).
*   **`database` driver**: Stores session data in a database table. This is useful for applications running on multiple web servers (load balancing) as it centralizes session storage.
    *   **Configuration**: `session.table` (name of the database table to use).

### Session Configuration (`config/session.php`)

```php
return [
    'driver' => 'file', // or 'database'
    'lifetime' => 120, // Session lifetime in minutes
    'expire_on_close' => false, // Whether session expires when browser closes
    'files' => BASE_PATH . '/cache/sessions', // Path for file driver
    'table' => 'sessions', // Table name for database driver
    'csrf_lifetime' => 10, // CSRF token lifetime in minutes
];
```

### Interacting with Session Data (`Elementary\Utils\SessionBag`)

The `SessionBag` class provides a convenient object-oriented interface to interact with the `$_SESSION` superglobal. You can inject `SessionBag` into your controllers or other services.

```php
use Elementary\Utils\SessionBag;

class MyController extends Controller
{
    public function __construct(private SessionBag $session)
    {
    }

    public function storeData()
    {
        // Store data in the session
        $this->session->set('user_id', 123);
        $this->session->set('cart', ['item1', 'item2']);

        // Retrieve data
        $userId = $this->session->get('user_id');
        $cart = $this->session->get('cart', []); // with default value

        // Check if a key exists
        if ($this->session->has('user_id')) {
            // ...
        }

        // Remove data
        $this->session->remove('cart');

        // Get all session data
        $allData = $this->session->all();

        // Clear all session data
        $this->session->clear();

        // Destroy the entire session (logs user out)
        $this->session->destroy();
    }
}
```

## Flash Messages

Flash messages are a special type of session data that are only available for the *next* request. They are commonly used for displaying one-time status messages (e.g., "User created successfully!").

### `Elementary\Utils\FlashBag`

The `FlashBag` class provides an interface for managing flash messages. It uses the `SessionBag` internally.

```php
use Elementary\Utils\FlashBag;

class MyController extends Controller
{
    public function __construct(private FlashBag $flashBag)
    {
    }

    public function createUser()
    {
        // Add a flash message
        $this->flashBag->add('success', 'User created successfully!');

        return $this->redirect('/users');
    }

    public function showUsers()
    {
        // Retrieve a flash message (it will be removed after this)
        $message = $this->flashBag->get('success');
        // ... pass $message to view ...
    }
}
```

### Displaying Flash Messages in Templates

The `@flash` Cigg directive provides a convenient way to display flash messages in your templates:

```html
{{-- templates/auth/login.cigg --}}

@flash('error')
```

This will output the content of the 'error' flash message if it exists, and then clear it from the session.

## Cookies

Cookies are small pieces of data stored directly in the user's web browser. They are typically used for remembering user preferences, tracking, or maintaining login status (e.g., "Remember Me" functionality).

### Setting and Retrieving Cookies

Cookies can be set on the `Elementary\Http\Response` object and retrieved from the `Elementary\Http\Request` object.

```php
use Elementary\Http\Request;
use Elementary\Http\Response;

class MyController extends Controller
{
    public function setCookie(Response $response)
    {
        // Set a cookie named 'my_preference' with value 'dark_mode' for 1 hour
        $response->cookies->set('my_preference', 'dark_mode', time() + 3600);
        return $response;
    }

    public function getCookie(Request $request)
    {
        // Retrieve a cookie
        $preference = $request->cookies->get('my_preference');
        // ...
    }
}
```

### Encrypted Cookies

For sensitive information stored in cookies (like authentication tokens), Elementary provides the `Elementary\Http\Middleware\EncryptCookies` middleware. This middleware automatically encrypts all outgoing cookies and decrypts all incoming cookies, ensuring their confidentiality and integrity.

*   **Configuration**: The encryption key and cipher are defined in `config/encryption.php`.
*   **`Elementary\Utils\EncryptionService`**: This service handles the actual encryption and decryption process, ensuring data is securely protected with AES-256-CBC and a Message Authentication Code (MAC).

To exclude certain cookies from encryption, you can modify the `$except` property in the `EncryptCookies` middleware class.
