# Database and Models

The Elementary framework provides a straightforward way to interact with your database using an Active Record-like pattern.

## Configuration

Database connection settings are stored in the `config/database.php` file. These settings are used by the `Connection` class to establish a PDO connection to your database.

## Models

Models are classes that represent a single table in your database. They are located in the `src/Models` directory and should extend the `App\Models\AbstractModel`.

### Creating a Model

To create a model, you need to define the class and set the static `$tableName` property.

```php
// src/Models/User.php

namespace App\Models;

class User extends AbstractModel
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

### Conventions

The `AbstractModel` assumes the following conventions:

-   The primary key of the table is named `id`.
-   The model's public properties have the same names as the columns in the table.

## Basic Usage

The `AbstractModel` provides several static methods for common database operations.

### Finding Records

```php
// Find a user by their primary key
$user = User::find(1);

// Find all users
$allUsers = User::findAll();
```

### Creating Records

The `create()` method accepts an associative array of data to insert. It returns the ID of the new record.

```php
$newUserId = User::create([
    'FirstName' => 'John',
    'LastName' => 'Doe',
    'username' => 'john.doe@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT),
]);
```

### Updating Records

The `update()` method takes the ID of the record to update and an associative array of data.

```php
User::update(1, [
    'FirstName' => 'Jane'
]);
```

### Deleting Records

The `delete()` method takes the ID of the record to delete.

```php
User::delete(1);
```

### Custom Queries

For more complex queries, you can add custom methods to your model. You can get the PDO instance via the container to build your own queries.

```php
// In the User model
public static function findByEmail(string $email): ?static
{
    /** @var static $instance */
    $instance = self::getContainer()->get(static::class);

    $stmt = $instance->getPdo()->prepare("SELECT * FROM " . static::$tableName . " WHERE username = :email");
    $stmt->execute(['email' => $email]);
    $record = $stmt->fetch(\PDO::FETCH_ASSOC);

    // ... (code to hydrate and return a model instance)
}
```
