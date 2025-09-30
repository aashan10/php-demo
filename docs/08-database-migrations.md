# Database Migrations

The Elementary Framework includes a powerful database migration system that allows you to version control your database schema changes. Migrations provide a convenient way to alter your database structure in a structured and organized manner.

## Table of Contents

- [Overview](#overview)
- [Creating Migrations](#creating-migrations)
- [Migration Structure](#migration-structure)
- [Schema Builder](#schema-builder)
- [Running Migrations](#running-migrations)
- [Rolling Back Migrations](#rolling-back-migrations)
- [Migration Status](#migration-status)
- [Best Practices](#best-practices)
- [Advanced Usage](#advanced-usage)

## Overview

Migrations are like version control for your database, allowing you to modify your database schema in a structured way. Each migration file contains instructions for both applying changes (`up` method) and reversing them (`down` method).

### Key Features

- **Version Control**: Track database schema changes over time
- **Team Collaboration**: Share database changes across development teams
- **Rollback Support**: Safely revert database changes when needed
- **Batch Tracking**: Group related migrations for precise rollback control
- **Auto-Discovery**: Automatically discover migration files
- **Blueprint System**: Fluent API for defining table structures

## Creating Migrations

### Using the make:migration Command

The easiest way to create a migration is using the `make:migration` command:

```bash
# Create a new migration
php elementary make:migration create_users_table

# This creates a file like: database/migrations/2025_09_30_172032_create_users_table.php
```

### Using make:model with -m Flag

You can also create a migration automatically when creating a model:

```bash
# Create both a model and migration
php elementary make:model User --table=users -m

# Create with default table naming
php elementary make:model Product -m  # Creates products table migration
```

### Migration File Naming Convention

Migration files follow this naming pattern:
```
YYYY_MM_DD_HHMMSS_description.php
```

For example:
- `2025_09_30_172032_create_users_table.php`
- `2025_09_30_172145_add_email_column_to_users.php`
- `2025_09_30_172300_create_posts_table.php`

## Migration Structure

Every migration extends the base `Migration` class and implements two methods:

```php
<?php

declare(strict_types=1);

use Elementary\Database\Migration\Migration;

class Migration_2025_09_30_172032_CreateUsersTable extends Migration
{
    /**
     * Run the migration (apply changes)
     */
    public function up(): void
    {
        $this->schema->create('users', function($table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    
    /**
     * Reverse the migration (rollback changes)
     */
    public function down(): void
    {
        $this->schema->dropIfExists('users');
    }
}
```

### Migration Class Properties

- `$this->schema`: Access to the schema builder for database operations
- Automatic timestamp extraction from class name
- Integration with DatabaseManager for multi-driver support

## Schema Builder

The migration system includes a powerful schema builder with a fluent API for defining database structures.

### Creating Tables

```php
$this->schema->create('table_name', function($table) {
    // Define table structure
});
```

### Available Column Types

```php
// Primary key (auto-incrementing big integer)
$table->id();
$table->id('custom_id'); // Custom primary key name

// String columns
$table->string('name');                    // VARCHAR(255)
$table->string('title', 100);            // VARCHAR(100)

// Text columns
$table->text('description');              // TEXT
$table->text('content');

// Numeric columns
$table->integer('count');                 // INT
$table->unsignedBigInteger('user_id');   // BIGINT UNSIGNED

// Boolean columns
$table->boolean('is_active');            // BOOLEAN
$table->boolean('is_published')->default(false);

// Timestamp columns
$table->timestamps();                     // created_at, updated_at
```

### Column Modifiers

```php
// Make column nullable
$table->string('middle_name')->nullable();

// Set default values
$table->string('status')->default('active');
$table->boolean('is_verified')->default(false);
$table->string('color')->default('#3B82F6');

// Make column unique
$table->string('email')->unique();
$table->string('slug')->unique();
```

### Indexes

```php
// Add index on single column
$table->string('email')->unique();

// Add composite index
$table->index(['user_id', 'created_at']);

// Add named index
$table->index(['status', 'type'], 'idx_status_type');
```

### Foreign Keys

```php
// Basic foreign key
$table->unsignedBigInteger('user_id');
$table->foreign('user_id')->references('id')->on('users');

// Foreign key with cascade delete
$table->unsignedBigInteger('category_id');
$table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
```

### Dropping Tables

```php
// Drop table if it exists
$this->schema->dropIfExists('table_name');

// Drop table (will error if doesn't exist)
$this->schema->dropTable('table_name');
```

## Running Migrations

### Run All Pending Migrations

```bash
php elementary migrate
```

Example output:
```
Running migrations...
✅ Migrated:
  Migration_2025_09_30_172032_CreateUsersTable
  Migration_2025_09_30_172145_CreatePostsTable
```

### What Happens During Migration

1. **Migration Table Check**: Creates `migrations` table if it doesn't exist
2. **File Discovery**: Scans `database/migrations/` for migration files
3. **Status Check**: Compares files against run migrations in database
4. **Execution**: Runs pending migrations in chronological order
5. **Tracking**: Records each migration with batch number for rollback

## Rolling Back Migrations

### Rollback Last Batch

```bash
php elementary migrate:rollback
```

Example output:
```
Rolling back migrations...
✅ Rolled back:
  Migration_2025_09_30_172145_CreatePostsTable
  Migration_2025_09_30_172032_CreateUsersTable
```

### How Rollback Works

1. **Batch Identification**: Finds the latest batch number
2. **Reverse Order**: Runs `down()` methods in reverse chronological order
3. **Record Removal**: Removes migration records from tracking table
4. **Error Handling**: Stops on first failure to maintain consistency

## Migration Status

### Check Migration Status

```bash
php elementary migrate:status
```

Example output:
```
Migration status:

Migration                                          Status
------------------------------------------------------------
Migration_2024_01_01_000001_CreateUsersTable       ✅ Ran
Migration_2024_01_01_000002_CreatePostsTable       ✅ Ran
Migration_2025_09_30_172032_CreateCategoriesTable  ⚠️  Pending
```

### Status Indicators

- **✅ Ran**: Migration has been executed
- **⚠️ Pending**: Migration exists but hasn't been run yet

## Best Practices

### 1. Descriptive Names

Use clear, descriptive migration names:

```bash
# Good
php elementary make:migration create_users_table
php elementary make:migration add_email_verification_to_users
php elementary make:migration create_posts_categories_pivot_table

# Avoid
php elementary make:migration update_stuff
php elementary make:migration fix_db
```

### 2. Atomic Changes

Keep migrations focused on a single, atomic change:

```php
// Good - Single responsibility
class Migration_2025_09_30_172032_CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('users', function($table) {
            // Only create users table
        });
    }
}

// Better to split into separate migrations
class Migration_2025_09_30_172100_AddEmailVerificationToUsers extends Migration
{
    public function up(): void
    {
        $this->schema->modifyTable('users', function($table) {
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verification_token')->nullable();
        });
    }
}
```

### 3. Always Implement down() Method

Every migration should have a proper rollback:

```php
public function down(): void
{
    // For table creation
    $this->schema->dropIfExists('users');
    
    // For column additions (would need column removal logic)
    // $this->schema->modifyTable('users', function($table) {
    //     $table->dropColumn('email_verified_at');
    // });
}
```

### 4. Use Appropriate Data Types

Choose the most appropriate column types:

```php
// Good
$table->string('email');                    // Emails are strings
$table->unsignedBigInteger('user_id');     // Foreign keys
$table->decimal('price', 8, 2);           // Money values
$table->timestamp('created_at');           // Timestamps

// Consider carefully
$table->string('phone', 20);              // Phone numbers vary in length
$table->text('description');              // Long text content
```

### 5. Foreign Key Constraints

Always define foreign key relationships:

```php
$table->unsignedBigInteger('user_id');
$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
```

### 6. Index Performance-Critical Columns

Add indexes for columns used in WHERE clauses:

```php
// Columns used for searching/filtering
$table->string('status');
$table->timestamp('created_at');
$table->index(['status', 'created_at']); // Composite index
```

## Advanced Usage

### Migration File Location

Migrations are stored in:
```
database/migrations/
├── 2024_01_01_000001_create_users_table.php
├── 2024_01_01_000002_create_posts_table.php
└── 2025_09_30_172032_create_categories_table.php
```

### Migration Tracking Table

The system uses a `migrations` table to track execution:

```sql
CREATE TABLE `migrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
);
```

### Batch System

Migrations run in batches for precise rollback control:

```sql
INSERT INTO migrations (migration, batch) VALUES
('Migration_2024_01_01_000001_CreateUsersTable', 1),
('Migration_2024_01_01_000002_CreatePostsTable', 1),
('Migration_2025_09_30_172032_CreateCategoriesTable', 2);
```

When rolling back, only the latest batch (batch 2) is rolled back.

### Integration with DatabaseManager

Migrations integrate seamlessly with Elementary's multi-driver database system:

- Automatic driver detection
- Connection pooling support
- Query execution tracking
- Error handling and transactions

### Schema Builder Drivers

The schema builder supports multiple database drivers:

- **MySQL**: Full featured implementation with Blueprint system
- **Redis**: Schema operations for Redis data structures
- **Extensible**: Easy to add support for PostgreSQL, MongoDB, etc.

### Example: Complex Migration

```php
class Migration_2025_09_30_172300_CreateBlogSchema extends Migration
{
    public function up(): void
    {
        // Create categories table
        $this->schema->create('categories', function($table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color')->default('#3B82F6');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        
        // Create posts table with foreign key
        $this->schema->create('posts', function($table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->text('content');
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('category_id');
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories');
            
            // Indexes for performance
            $table->index(['status', 'created_at']);
            $table->index('category_id');
        });
    }
    
    public function down(): void
    {
        $this->schema->dropIfExists('posts');
        $this->schema->dropIfExists('categories');
    }
}
```

## Troubleshooting

### Common Issues

1. **Migration Already Exists Error**
   ```
   SQLSTATE[42S01]: Base table or view already exists
   ```
   Solution: Check if table exists manually or add conditional logic

2. **Foreign Key Constraint Fails**
   ```
   SQLSTATE[23000]: Integrity constraint violation
   ```
   Solution: Ensure referenced tables exist and have correct column types

3. **Migration Not Found**
   ```
   Migration class Migration_2025_09_30_172032_CreateUsersTable not found
   ```
   Solution: Check file naming and class name match exactly

### Manual Migration Management

In rare cases, you may need to manually manage migrations:

```sql
-- Mark migration as run without executing
INSERT INTO migrations (migration, batch) VALUES 
('Migration_2025_09_30_172032_CreateUsersTable', 1);

-- Remove migration record
DELETE FROM migrations WHERE migration = 'Migration_2025_09_30_172032_CreateUsersTable';
```

## Conclusion

The Elementary Framework's migration system provides a robust, Laravel-inspired approach to database schema management. With its fluent API, automatic discovery, batch tracking, and rollback capabilities, it enables confident database schema evolution in development and production environments.

The system integrates seamlessly with Elementary's multi-driver database architecture, providing a consistent interface regardless of your chosen database backend.