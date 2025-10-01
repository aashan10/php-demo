<?php

declare(strict_types=1);

namespace Elementary\Database\Schema;

use Elementary\Database\Contracts\SchemaBuilderInterface;
use Elementary\Database\Drivers\SQLiteDriver;
use PDO;

/**
 * SQLite Schema Builder
 * 
 * Implements SQLite-specific schema operations for database structure management.
 * Handles table creation, modification, and introspection operations tailored for SQLite.
 */
class SQLiteSchemaBuilder implements SchemaBuilderInterface
{
    protected SQLiteDriver $driver;

    public function __construct(SQLiteDriver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Check if a table exists
     */
    public function hasTable(string $table): bool
    {
        $sql = "SELECT COUNT(*) as count FROM sqlite_master 
                WHERE type = 'table' AND name = ?";
        
        $connection = $this->driver->getConnection();
        $stmt = $connection->prepare($sql);
        $stmt->execute([$table]);
        
        $result = $stmt->fetch();
        return (int) $result['count'] > 0;
    }

    /**
     * Create a new table using a simple callback approach
     */
    public function createTable(string $table, callable $callback): void
    {
        $builder = new SQLiteTableBuilder($table);
        $callback($builder);
        
        $sql = $builder->toSql();
        
        $this->driver->getConnection()->exec($sql);
        $this->driver->recordQueryExecution();
    }

    /**
     * Create a new table (alias for createTable)
     */
    public function create(string $table, callable $callback): void
    {
        $this->createTable($table, $callback);
    }

    /**
     * Drop a table
     */
    public function dropTable(string $table): void
    {
        $sql = "DROP TABLE \"{$table}\"";
        $this->driver->getConnection()->exec($sql);
        $this->driver->recordQueryExecution();
    }

    /**
     * Drop a table if it exists
     */
    public function dropIfExists(string $table): void
    {
        $sql = "DROP TABLE IF EXISTS \"{$table}\"";
        $this->driver->getConnection()->exec($sql);
        $this->driver->recordQueryExecution();
    }

    /**
     * Rename a table
     */
    public function renameTable(string $from, string $to): void
    {
        $sql = "ALTER TABLE \"{$from}\" RENAME TO \"{$to}\"";
        $this->driver->getConnection()->exec($sql);
        $this->driver->recordQueryExecution();
    }

    /**
     * Modify an existing table (SQLite has limited ALTER TABLE support)
     */
    public function modifyTable(string $table, callable $callback): void
    {
        // SQLite has limited ALTER TABLE capabilities
        // For complex modifications, we would need to:
        // 1. Create a new table with the desired structure
        // 2. Copy data from the old table
        // 3. Drop the old table
        // 4. Rename the new table
        
        // For now, implement basic column addition
        $modifier = new SQLiteTableModifier($table, $this->driver);
        $callback($modifier);
        $modifier->execute();
    }

    /**
     * Get list of all tables
     */
    public function getTables(): array
    {
        $sql = "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'";
        
        $connection = $this->driver->getConnection();
        $stmt = $connection->query($sql);
        
        $tables = [];
        while ($row = $stmt->fetch()) {
            $tables[] = $row['name'];
        }
        
        return $tables;
    }

    /**
     * Get columns for a table
     */
    public function getColumns(string $table): array
    {
        $sql = "PRAGMA table_info(\"{$table}\")";
        
        $connection = $this->driver->getConnection();
        $stmt = $connection->query($sql);
        
        $columns = [];
        while ($row = $stmt->fetch()) {
            $columns[] = [
                'name' => $row['name'],
                'type' => $row['type'],
                'nullable' => !$row['notnull'],
                'default' => $row['dflt_value'],
                'primary_key' => (bool) $row['pk'],
            ];
        }
        
        return $columns;
    }

    /**
     * Check if a column exists
     */
    public function hasColumn(string $table, string $column): bool
    {
        $columns = $this->getColumns($table);
        
        foreach ($columns as $col) {
            if ($col['name'] === $column) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get indexes for a table
     */
    public function getIndexes(string $table): array
    {
        $sql = "PRAGMA index_list(\"{$table}\")";
        
        $connection = $this->driver->getConnection();
        $stmt = $connection->query($sql);
        
        $indexes = [];
        while ($row = $stmt->fetch()) {
            $indexes[] = [
                'name' => $row['name'],
                'unique' => (bool) $row['unique'],
                'partial' => isset($row['partial']) ? (bool) $row['partial'] : false,
            ];
        }
        
        return $indexes;
    }

    /**
     * Check if an index exists
     */
    public function hasIndex(string $table, string $index): bool
    {
        $indexes = $this->getIndexes($table);
        
        foreach ($indexes as $idx) {
            if ($idx['name'] === $index) {
                return true;
            }
        }
        
        return false;
    }
}

/**
 * SQLite Table Builder
 * 
 * Helper class for building SQLite CREATE TABLE statements
 */
class SQLiteTableBuilder
{
    protected string $tableName;
    protected array $columns = [];
    protected array $constraints = [];

    public function __construct(string $tableName)
    {
        $this->tableName = $tableName;
    }

    /**
     * Add an integer column
     */
    public function integer(string $name, bool $autoIncrement = false): self
    {
        $type = 'INTEGER';
        if ($autoIncrement) {
            $type .= ' PRIMARY KEY AUTOINCREMENT';
        }
        
        $this->columns[] = "\"{$name}\" {$type}";
        return $this;
    }

    /**
     * Add a string/varchar column
     */
    public function string(string $name, int $length = 255): self
    {
        $this->columns[] = "\"{$name}\" VARCHAR({$length})";
        return $this;
    }

    /**
     * Add a text column
     */
    public function text(string $name): self
    {
        $this->columns[] = "\"{$name}\" TEXT";
        return $this;
    }

    /**
     * Add a timestamp column
     */
    public function timestamp(string $name): self
    {
        $this->columns[] = "\"{$name}\" DATETIME";
        return $this;
    }

    /**
     * Add timestamps (created_at, updated_at)
     */
    public function timestamps(): self
    {
        $this->timestamp('created_at');
        $this->timestamp('updated_at');
        return $this;
    }

    /**
     * Add a primary key
     */
    public function primary(string|array $columns): self
    {
        if (is_array($columns)) {
            $columnList = implode(', ', array_map(fn($col) => "\"{$col}\"", $columns));
        } else {
            $columnList = "\"{$columns}\"";
        }
        
        $this->constraints[] = "PRIMARY KEY ({$columnList})";
        return $this;
    }

    /**
     * Add a unique constraint
     */
    public function unique(string|array $columns): self
    {
        if (is_array($columns)) {
            $columnList = implode(', ', array_map(fn($col) => "\"{$col}\"", $columns));
        } else {
            $columnList = "\"{$columns}\"";
        }
        
        $this->constraints[] = "UNIQUE ({$columnList})";
        return $this;
    }

    /**
     * Build the CREATE TABLE SQL
     */
    public function toSql(): string
    {
        $parts = array_merge($this->columns, $this->constraints);
        $columnDefinitions = implode(",\n    ", $parts);
        
        return "CREATE TABLE \"{$this->tableName}\" (\n    {$columnDefinitions}\n)";
    }
}

/**
 * SQLite Table Modifier
 * 
 * Helper class for modifying existing SQLite tables
 */
class SQLiteTableModifier
{
    protected string $tableName;
    protected SQLiteDriver $driver;
    protected array $operations = [];

    public function __construct(string $tableName, SQLiteDriver $driver)
    {
        $this->tableName = $tableName;
        $this->driver = $driver;
    }

    /**
     * Add a column (one of the few ALTER TABLE operations SQLite supports)
     */
    public function addColumn(string $name, string $type): self
    {
        $this->operations[] = "ALTER TABLE \"{$this->tableName}\" ADD COLUMN \"{$name}\" {$type}";
        return $this;
    }

    /**
     * Execute all modifications
     */
    public function execute(): void
    {
        foreach ($this->operations as $sql) {
            $this->driver->getConnection()->exec($sql);
            $this->driver->recordQueryExecution();
        }
    }
}