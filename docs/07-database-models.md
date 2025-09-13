# Database and Models

The Elementary framework provides a powerful and fluent query builder to interact with your database, while allowing your model classes to remain clean and simple.

## Configuration

Database connection settings are stored in the `config/database.php` file. These settings are used by the `Connection` class to establish a PDO connection to your database.

## Models

Models are classes that represent a single table in your database. They should extend the `Elementary\Database\Model` class, which provides the core ORM functionality.

The only requirement for a model class is to define the static `$tableName` property.

```php
// src/Models/User.php

namespace App\Models;

class User extends Model
{
    protected static string $tableName = 'users';

    // Public properties corresponding to table columns
    public int $id;
    public string $FirstName;
    public string $LastName;
    public string $username;
    public string $password;
}
```

### User Model Configuration

For authentication and other framework features that interact with your application's user model, the specific class is configured in `config/auth.php`:

```php
// config/auth.php

return [
    'model' => App\Models\User::class,
];
```

This allows the framework to remain decoupled from your application's specific user model implementation.

## Querying the Database

All queries are performed through the fluent query builder. To start a query, use the static `query()` method on your model.

```php
$users = User::query()->get();
```

The `query()` method returns an instance of the `QueryBuilder` class, which allows you to chain various methods to build and execute your query.

### Retrieving Records

You can retrieve multiple records using `get()` or a single record using `first()`.

```php
// Get all users
$allUsers = User::query()->get();

// Get the first user
$firstUser = User::query()->first();
```

### Adding `WHERE` Clauses

You can filter your query by adding `WHERE` clauses.

```php
// Get all active users
$activeUsers = User::query()->where('status', '=', 'active')->get();

// Get a specific user by email
$user = User::query()->where('username', '=', 'jane@example.com')->first();
```

### Finding by Primary Key

A convenient shortcut for finding a record by its `id` is the `find()` method.

```php
// Find a user with an ID of 1
$user = User::query()->find(1);

// This is equivalent to:
$user = User::query()->where('id', '=', 1)->first();
```

### Ordering and Limiting

You can easily order and limit your results.

```php
// Get the 10 most recently registered users
$latestUsers = User::query()->orderBy('created_at', 'DESC')->limit(10)->get();
```

## Custom Query Methods

For queries that you run often, you can add custom methods to your model class to create reusable query scopes.

```php
// In the User model

class User extends Model
{
    // ...

    /**
     * Finds a user by their email address (username).
     */
    public static function findByEmail(string $email): ?static
    {
        return static::query()->where('username', '=', $email)->first();
    }
}
```

This allows you to easily find a user by their email from anywhere in your application:

```php
$user = User::findByEmail('john@example.com');
```

## Modifying Records

The query builder supports `INSERT`, `UPDATE`, and `DELETE` operations.

### Inserting Records

You can insert new records into the database using the `create()` static method on your model.

```php
// Create a new user
$success = User::create([
    'FirstName' => 'John',
    'LastName' => 'Doe',
    'username' => 'john.doe@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT), // Always hash passwords!
]);
```

### Updating Records

To update existing records, you can use the `update()` method on a model instance. This will update the record corresponding to the model's primary key.

```php
// Assuming $user is an instance of User model, e.g., $user = User::find(1);
$user->FirstName = 'Jonathan';
$affectedRows = $user->update(['FirstName' => $user->FirstName]);
```

Alternatively, you can update records using the query builder with `where` clauses:

```php
// Update all users with a specific username
$affectedRows = User::query()->where('username', '=', 'old@example.com')->update(['username' => 'new@example.com']);
```

### Deleting Records

To delete a record, you can use the `delete()` method on a model instance.

```php
// Assuming $user is an instance of User model, e.g., $user = User::find(1);
$affectedRows = $user->delete();
```

You can also delete records using the query builder with `where` clauses:

```php
// Delete all inactive users
$affectedRows = User::query()->where('status', '=', 'inactive')->delete();
```
