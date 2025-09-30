# Migration Quick Reference

## Commands

| Command | Description |
|---------|-------------|
| `make:migration create_table` | Create a new migration |
| `make:model User -m` | Create model with migration |
| `migrate` | Run all pending migrations |
| `migrate:status` | Show migration status |
| `migrate:rollback` | Rollback last batch |

## Schema Builder API

### Table Operations
```php
$this->schema->create('table', $callback);
$this->schema->dropIfExists('table');
$this->schema->hasTable('table');
```

### Column Types
```php
$table->id();                           // Primary key
$table->string('name');                 // VARCHAR(255)
$table->string('title', 100);          // VARCHAR(100)
$table->text('content');               // TEXT
$table->integer('count');              // INT
$table->unsignedBigInteger('user_id'); // BIGINT UNSIGNED
$table->boolean('is_active');          // BOOLEAN
$table->timestamps();                  // created_at, updated_at
```

### Column Modifiers
```php
->nullable()                           // Allow NULL
->default('value')                     // Set default
->unique()                            // Add unique constraint
```

### Indexes & Foreign Keys
```php
$table->index(['col1', 'col2']);
$table->foreign('user_id')->references('id')->on('users');
$table->foreign('cat_id')->references('id')->on('categories')->onDelete('cascade');
```

## Migration Template
```php
<?php

declare(strict_types=1);

use Elementary\Database\Migration\Migration;

class Migration_YYYY_MM_DD_HHMMSS_DescriptiveName extends Migration
{
    public function up(): void
    {
        $this->schema->create('table_name', function($table) {
            $table->id();
            // Add columns here
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        $this->schema->dropIfExists('table_name');
    }
}
```

## Common Patterns

### User Table
```php
$this->schema->create('users', function($table) {
    $table->id();
    $table->string('first_name');
    $table->string('last_name');
    $table->string('email')->unique();
    $table->string('password');
    $table->boolean('is_active')->default(true);
    $table->string('remember_token')->nullable();
    $table->timestamps();
});
```

### Posts with Foreign Key
```php
$this->schema->create('posts', function($table) {
    $table->id();
    $table->string('title');
    $table->string('slug')->unique();
    $table->text('content');
    $table->string('status')->default('draft');
    $table->unsignedBigInteger('user_id');
    $table->timestamps();
    
    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    $table->index(['status', 'created_at']);
});
```

### Pivot Table
```php
$this->schema->create('post_tags', function($table) {
    $table->id();
    $table->unsignedBigInteger('post_id');
    $table->unsignedBigInteger('tag_id');
    $table->timestamps();
    
    $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
    $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
    $table->index(['post_id', 'tag_id']);
});
```