# Database and Models

The Elementary framework provides a powerful and scalable database layer with connection pooling, a fluent query builder, and an elegant Active Record ORM pattern. Built for production-scale applications serving millions of users.

## Features

- **Connection Pooling**: High-performance connection management for scalability
- **Container-Free Architecture**: Clean models without dependency injection
- **Active Record Pattern**: Laravel-style model methods for intuitive database interaction
- **Database Migrations**: Version control for your database schema changes ([See Migration Guide](08-database-migrations.md))
- **Database Sessions**: Session storage in database for multi-server deployments
- **Query Builder**: Fluent, chainable query interface with advanced features
- **Production Ready**: Horizontal scaling, Docker support, enterprise-grade architecture

## Configuration

Database connection settings are stored in the `config/database.php` file. The configuration supports both traditional single connections and high-performance connection pooling.

### Basic Configuration

```php
// config/database.php

return [
    // Basic Database Settings
    'driver' => 'mysql',
    'host' => 'mysql',
    'port' => 3306,
    'database' => 'php_demo',
    'username' => 'php_demo',
    'password' => 'php_demo',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    
    // Connection Pool Configuration (Recommended for Production)
    'pool' => [
        'enabled' => true,
        'min_connections' => 5,
        'max_connections' => 20,
        'connection_timeout' => 30,
        'idle_timeout' => 300,
    ],
    
    // Session Storage
    'session' => [
        'driver' => 'database',
        'table' => 'sessions',
        'lifetime' => 120, // minutes
    ],
];
```

## Architecture

Elementary's database layer uses a **DatabaseManager** as the central coordinator, eliminating container dependencies from models while maintaining clean Active Record patterns. The system automatically initializes during application bootstrap:

```php
// bootstrap.php
DatabaseManager::initialize($config);
```

## Models

Models are classes that represent a single table in your database. They extend the `Elementary\Database\Model` class, providing powerful Active Record functionality without container dependencies.

### Basic Model Definition

```php
// app/Models/User.php

namespace App\Models;

use Elementary\Database\Model;

class User extends Model
{
    protected static string $table = 'users';
    protected static string $primaryKey = 'id';
    protected static array $fillable = ['name', 'email', 'password'];
    protected static array $guarded = ['id'];
}
```

### Model Properties

- **`$table`**: Database table name (auto-generated from class name if not specified)
- **`$primaryKey`**: Primary key column name (defaults to 'id')
- **`$fillable`**: Columns that can be mass-assigned
- **`$guarded`**: Columns that cannot be mass-assigned (defaults to ['id'])

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

Elementary provides both static model methods and a fluent query builder for database operations.

### Static Model Methods

The simplest way to interact with your models:

```php
// Get all users
$users = User::all();

// Find by primary key
$user = User::find(1);

// Create new user
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT)
]);

// Count records
$count = User::count();

 // Query with conditions
$activeUsers = User::where('status', '=', 'active')->get();

// Eager load relationships
$usersWithPosts = User::with('posts')->get();```

### Query Builder

For complex queries, use the fluent query builder:

```php
$users = User::query()
    ->where('status', '=', 'active')
    ->where('created_at', '>', '2024-01-01')
    ->orderBy('name', 'ASC')
    ->limit(10)
    ->get();
```

### Advanced Query Methods

```php
// Multiple WHERE conditions
$users = User::query()
    ->where('status', '=', 'active')
    ->where('age', '>=', 18)
    ->get();

// WHERE IN / NOT IN
$users = User::query()
    ->whereIn('role', ['admin', 'moderator'])
    ->whereNotIn('status', ['banned', 'suspended'])
    ->get();

// NULL checks
$users = User::query()
    ->whereNull('deleted_at')
    ->whereNotNull('email_verified_at')
    ->get();

// Joins
$users = User::query()
    ->join('profiles', 'users.id', '=', 'profiles.user_id')
    ->select(['users.*', 'profiles.bio'])
    ->get();

// Group By and Having
$userCounts = User::query()
    ->select(['status', 'COUNT(*) as count'])
    ->groupBy('status')
    ->having('count', '>', 10)
    ->get();
```

### Pagination

Built-in pagination support:

```php
$paginatedUsers = User::query()
    ->where('status', '=', 'active')
    ->paginate($page = 1, $perPage = 15);

// Returns:
// [
//     'data' => [...],
//     'current_page' => 1,
//     'per_page' => 15,
//     'total' => 150,
//     'last_page' => 10,
//     'from' => 1,
//     'to' => 15,
// ]
```

## Model Relationships

You can define relationships between your models to easily query and interact with related data.

### Defining Relationships

Relationships are defined as methods on your model class. These methods return dedicated relationship objects, which allow for both powerful query building and fluent relationship management.

**One-to-One (`hasOne`)**

For example, a `User` might have one `Profile`.

```php
// In app/Models/User.php
use App\Models\Profile;
use Elementary\Database\Relations\HasOne;

class User extends Model
{
    // ...

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }
}
```

**One-to-Many (`hasMany`)**

For example, a `User` can have many `Post` models.

```php
// In app/Models/User.php
use App\Models\Post;
use Elementary\Database\Relations\HasMany;

class User extends Model
{
    // ...

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
```

**One-to-Many (Inverse / `belongsTo`)**

To define the inverse of a `hasMany` relationship, use the `belongsTo` method. For example, a `Post` belongs to a `User`.

```php
// In app/Models/Post.php
use App\Models\User;
use Elementary\Database\Relations\BelongsTo;

class Post extends Model
{
    // ...

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

By default, the framework assumes foreign keys based on the model name (e.g., `user_id` for the `User` model). You can override this by passing custom key names as the second and third arguments to the relationship methods.

### Querying Relationships

Once relationships are defined, you can access them in several ways.

#### Dynamic Properties (Lazy Loading)

You can access the relationship as if it were a property on the model. The data is "lazy-loaded," meaning it will only be fetched from the database the first time you access it.

```php
$user = User::find(1);

// Accessing the 'posts' property executes a query to get the posts.
$posts = $user->posts;

foreach ($posts as $post) {
    echo $post->title;
}
```

While convenient, lazy loading can lead to the "N+1 query problem." If you were to loop through 100 users and access their posts, it would result in 101 separate database queries: 1 to get the users, and 100 more to get the posts for each user. This is inefficient.

#### Querying the Relationship

If you call the relationship method with parentheses, you get the relationship object, which allows you to chain additional query constraints before executing the query.

```php
$user = User::find(1);

// Get the relationship object
$postsQuery = $user->posts();

// Add more constraints and then get the result
$publishedPosts = $postsQuery->where('is_published', '=', true)->get();
```

### Eager Loading

To solve the N+1 problem, you should "eager load" your relationships using the `with()` method. This tells the query builder to fetch the primary models and their related models all in just two queries.

```php
// Fetch all users and their posts in only two queries.
$users = User::with('posts')->get();

foreach ($users as $user) {
    // The 'posts' relationship is already loaded, no extra query is run.
    $posts = $user->posts;
    echo $user->name . ' has ' . count($posts) . ' posts.';
}
```

#### Eager Loading Multiple Relationships

You can eager load multiple relationships at once:

```php
$posts = Post::with('user', 'comments')->get();
```

#### Constraining Eager Loads

Sometimes you may wish to eager load a relationship, but also specify additional query constraints for the eager loaded query. You can do this by passing an array to `with`, where the key is the relationship name and the value is a closure that adds the constraints:

```php
$users = User::with([
    'posts' => fn($query) => $query->where('published', true)->orderBy('created_at', 'desc')
])->get();
```

This provides a powerful and fluent API for working with your model relations.

### Serialization

When converting a model to an array or JSON, its loaded relationships will be automatically included in the attributes.

```php
$user = User::with('posts')->find(1);

return $user->toJson();

// {
//   "id": 1,
//   "name": "John Doe",
//   "email": "john@example.com",
//   "posts": [
//     { "id": 101, "user_id": 1, "title": "First Post" },
//     { "id": 102, "user_id": 1, "title": "Second Post" }
//   ]
// }
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
        return static::query()->where('email', '=', $email)->first();
    }
}
```

This allows you to easily find a user by their email from anywhere in your application:

```php
$user = User::findByEmail('john@example.com');
```

## Model Operations

Elementary provides multiple ways to create, update, and delete records with clean, intuitive APIs.

### Creating Records

```php
// Static create method (returns model instance)
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT)
]);

// Manual creation and save
$user = new User([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com'
]);
$user->save(); // Returns boolean
```

### Reading Records

```php
// Find by primary key
$user = User::find(1);

// Find or return null
$user = User::where('email', '=', 'john@example.com')->first();

// Get all records
$users = User::all();

// Check if record exists
$exists = User::where('email', '=', 'test@example.com')->exists();
```

### Updating Records

```php
// Update via model instance (returns boolean)
$user = User::find(1);
$user->name = 'Updated Name';
$success = $user->update(['name' => $user->name]);

// Save model changes (returns boolean)
$user = User::find(1);
$user->name = 'Updated Name';
$success = $user->save();

// Bulk update via query builder (returns affected rows)
$affectedRows = User::query()
    ->where('status', '=', 'inactive')
    ->update(['status' => 'active']);
```

### Deleting Records

```php
// Delete via model instance (returns boolean)
$user = User::find(1);
$success = $user->delete();

// Bulk delete via query builder (returns affected rows)
$affectedRows = User::query()
    ->where('status', '=', 'banned')
    ->delete();
```

### Model State Management

```php
$user = User::find(1);

// Check if model exists in database
$exists = $user->exists();

// Check if model has unsaved changes
$isDirty = $user->isDirty();
$isDirtyName = $user->isDirty('name');

// Get changed attributes
$changes = $user->getDirty();

// Refresh model from database
$user->refresh();

// Replicate model (create copy without primary key)
$newUser = $user->replicate(['created_at']);
```

## Connection Pooling

Elementary's connection pooling system provides enterprise-grade performance for high-traffic applications.

### Pool Configuration

```php
// config/database.php

'pool' => [
    'enabled' => true,
    'min_connections' => 5,      // Minimum connections to maintain
    'max_connections' => 20,     // Maximum connections allowed
    'connection_timeout' => 30,  // Connection timeout in seconds
    'idle_timeout' => 300,       // Idle timeout in seconds
],
```

### Pool Statistics

Monitor connection pool performance:

```php
$stats = DatabaseManager::getInstance()->getConnectionStats();
// Returns:
// [
//     'active_connections' => 8,
//     'idle_connections' => 12,
//     'total_connections' => 20,
//     'queries_executed' => 15432,
//     'last_query_time' => 1640995200
// ]
```

### Performance Benefits

- **High Concurrency**: Handle thousands of simultaneous requests
- **Resource Efficiency**: Reuse connections instead of creating new ones
- **Automatic Management**: Connections automatically returned to pool
- **Scalability**: Horizontal scaling with multiple application instances

## Advanced Features

### Query Debugging

```php
// Get generated SQL and bindings
$debug = User::query()
    ->where('status', '=', 'active')
    ->toDebugSql();

// Returns:
// [
//     'sql' => 'SELECT * FROM `users` WHERE `status` = ?',
//     'bindings' => ['active']
// ]

// Get raw SQL with values interpolated (debugging only)
$sql = User::query()
    ->where('status', '=', 'active')
    ->toRawSql();
// Returns: "SELECT * FROM `users` WHERE `status` = 'active'"
```

### Distinct Queries

```php
$uniqueStatuses = User::query()
    ->select(['status'])
    ->distinct()
    ->get();
```

### Transactions

```php
// Using DatabaseManager for transactions
DatabaseManager::getInstance()->transaction(function() {
    User::create(['name' => 'John', 'email' => 'john@example.com']);
    Profile::create(['user_id' => 1, 'bio' => 'Developer']);
});
```

## Security

### Mass Assignment Protection

```php
class User extends Model 
{
    // Only these fields can be mass-assigned
    protected static array $fillable = ['name', 'email'];
    
    // These fields are always protected
    protected static array $guarded = ['id', 'created_at'];
}

// This will only set 'name' and 'email', ignoring 'id'
$user = User::create([
    'id' => 999,           // Ignored (guarded)
    'name' => 'John',      // Set (fillable)
    'email' => 'john@example.com', // Set (fillable)
    'is_admin' => true     // Ignored (not fillable)
]);
```

### Query Parameter Binding

All queries automatically use parameter binding to prevent SQL injection:

```php
// Safe - uses parameter binding
$users = User::query()
    ->where('email', '=', $userInput)
    ->get();
```

## Production Deployment

### Docker Configuration

Elementary works seamlessly with Docker for production deployment:

```dockerfile
# Dockerfile
FROM php:8.2-fpm
RUN docker-php-ext-install pdo pdo_mysql
COPY . /var/www/html
```

```yaml
# docker-compose.yml
version: '3.8'
services:
  app:
    build: .
    volumes:
      - .:/var/www/html
    environment:
      - DB_HOST=mysql
      - DB_DATABASE=elementary_prod
  
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: elementary_prod
      MYSQL_ROOT_PASSWORD: secure_password
    volumes:
      - mysql_data:/var/lib/mysql

volumes:
  mysql_data:
```

### Horizontal Scaling

For million-user applications:

1. **Multiple App Instances**: Run multiple Elementary containers
2. **Database Pooling**: Each instance maintains its own connection pool
3. **Session Storage**: Database sessions support multi-server deployments
4. **Load Balancing**: Use nginx or AWS Application Load Balancer

### Performance Monitoring

```php
// Monitor query performance
$config = DatabaseManager::getInstance()->getConfig();
if ($config->get('database.performance.log_queries')) {
    // Queries are logged for analysis
}

// Monitor slow queries
$slowThreshold = $config->get('database.performance.slow_query_threshold', 1000);
```

## Database Migrations

Elementary includes a comprehensive migration system for version controlling your database schema changes. Migrations allow you to modify your database structure in a structured and collaborative way.

### Quick Start

```bash
# Create a migration
php elementary make:migration create_users_table

# Create a model with migration
php elementary make:model Product -m

# Run migrations
php elementary migrate

# Check status
php elementary migrate:status

# Rollback
php elementary migrate:rollback
```

### Example Migration

```php
use Elementary\Database\Migration\Migration;

class Migration_2025_09_30_172032_CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('users', function($table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        $this->schema->dropIfExists('users');
    }
}
```

**📖 [Complete Migration Guide](08-database-migrations.md)** - Learn about schema building, rollbacks, best practices, and advanced features.

## Getting Started

Elementary's database layer is designed for simplicity and performance:

1. **Configure** your database connection in `config/database.php`
2. **Initialize** the DatabaseManager in your bootstrap
3. **Create** model classes extending `Elementary\Database\Model`
4. **Create** database migrations for schema changes
5. **Use** intuitive Active Record patterns for database operations

The architecture provides enterprise-grade features like connection pooling while maintaining clean, readable code throughout your application.
