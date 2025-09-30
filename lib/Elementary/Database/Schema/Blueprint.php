<?php

declare(strict_types=1);

namespace Elementary\Database\Schema;

/**
 * Table Blueprint
 * 
 * Provides a fluent interface for defining table structure in migrations.
 */
class Blueprint
{
    protected string $table;
    protected array $columns = [];
    protected array $indexes = [];
    protected array $foreignKeys = [];
    protected string $engine = 'InnoDB';
    protected string $charset = 'utf8mb4';
    protected string $collation = 'utf8mb4_unicode_ci';

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Add an auto-incrementing ID column
     */
    public function id(string $column = 'id'): static
    {
        $this->columns[] = [
            'name' => $column,
            'type' => 'BIGINT UNSIGNED',
            'auto_increment' => true,
            'primary' => true,
            'nullable' => false
        ];
        return $this;
    }

    /**
     * Add a string column
     */
    public function string(string $column, int $length = 255): static
    {
        $this->columns[] = [
            'name' => $column,
            'type' => "VARCHAR({$length})",
            'nullable' => false
        ];
        return $this;
    }

    /**
     * Add a text column
     */
    public function text(string $column): static
    {
        $this->columns[] = [
            'name' => $column,
            'type' => 'TEXT',
            'nullable' => false
        ];
        return $this;
    }

    /**
     * Add a boolean column
     */
    public function boolean(string $column): static
    {
        $this->columns[] = [
            'name' => $column,
            'type' => 'BOOLEAN',
            'nullable' => false,
            'default' => false
        ];
        return $this;
    }

    /**
     * Add an integer column
     */
    public function integer(string $column): static
    {
        $this->columns[] = [
            'name' => $column,
            'type' => 'INT',
            'nullable' => false
        ];
        return $this;
    }

    /**
     * Add an unsigned big integer column
     */
    public function unsignedBigInteger(string $column): static
    {
        $this->columns[] = [
            'name' => $column,
            'type' => 'BIGINT UNSIGNED',
            'nullable' => false
        ];
        return $this;
    }

    /**
     * Add timestamp columns (created_at, updated_at)
     */
    public function timestamps(): static
    {
        $this->columns[] = [
            'name' => 'created_at',
            'type' => 'TIMESTAMP',
            'default' => [
                'value' => 'CURRENT_TIMESTAMP' ,
                'raw' => true
            ]
        ];
        
        $this->columns[] = [
            'name' => 'updated_at',
            'type' => 'TIMESTAMP',
            'default' => [
                'value' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'  ,
                'raw' => true
            ]
        ];

        return $this;
    }

    /**
     * Make the last added column nullable
     */
    public function nullable(): static
    {
        if (!empty($this->columns)) {
            $this->columns[array_key_last($this->columns)]['nullable'] = true;
        }
        return $this;
    }

    /**
     * Set default value for the last added column
     */
    public function default($value, bool $raw = false): static
    {
        if (!empty($this->columns)) {
            $this->columns[array_key_last($this->columns)]['default'] = [ 
                'value' => $value,
                'raw' => $raw
            ];
        }
        return $this;
    }

    /**
     * Make the last added column unique
     */
    public function unique(): static
    {
        if (!empty($this->columns)) {
            $columnName = $this->columns[array_key_last($this->columns)]['name'];
            $this->indexes[] = [
                'type' => 'unique',
                'columns' => [$columnName],
                'name' => "idx_{$this->table}_{$columnName}_unique"
            ];
        }
        return $this;
    }

    /**
     * Add an index
     */
    public function index(array $columns, string $name = null): static
    {
        $indexName = $name ?: 'idx_' . $this->table . '_' . implode('_', $columns);
        
        $this->indexes[] = [
            'type' => 'index',
            'columns' => $columns,
            'name' => $indexName
        ];
        
        return $this;
    }

    /**
     * Add a foreign key constraint
     */
    public function foreign(string $column): ForeignKeyDefinition
    {
        return new ForeignKeyDefinition($this, $column);
    }

    /**
     * Add a foreign key constraint (internal)
     */
    public function addForeignKey(string $column, string $references, string $on, string $onDelete = null): static
    {
        $this->foreignKeys[] = [
            'column' => $column,
            'references' => $references,
            'on' => $on,
            'onDelete' => $onDelete
        ];
        
        return $this;
    }

    /**
     * Generate the CREATE TABLE SQL
     */
    public function toSql(): string
    {
        $sql = "CREATE TABLE `{$this->table}` (\n";
        
        $columnDefinitions = [];
        $primaryKeys = [];
        
        foreach ($this->columns as $column) {
            $definition = "  `{$column['name']}` {$column['type']}";
            
            if (!($column['nullable'] ?? false)) {
                $definition .= ' NOT NULL';
            } else {
                $definition .= ' NULL';
            }
            
            if (isset($column['default'])) {
                ['value' => $value, 'raw' => $raw] = $column['default'];
                if ($value === null) {
                    $definition .= ' DEFAULT NULL';
                } elseif (is_bool($value)) {
                    $definition .= ' DEFAULT ' . ($value ? '1' : '0');
                } elseif (is_string($value)) {
                    $definition .= $raw ? " DEFAULT {$value}" : " DEFAULT '" . addslashes($value) . "'";
                } else {
                    $definition .= ' DEFAULT ' . $value;
                }
            }
            
            if ($column['auto_increment'] ?? false) {
                $definition .= ' AUTO_INCREMENT';
            }
            
            $columnDefinitions[] = $definition;
            
            if ($column['primary'] ?? false) {
                $primaryKeys[] = $column['name'];
            }
        }
        
        $sql .= implode(",\n", $columnDefinitions);
        
        // Add primary key
        if (!empty($primaryKeys)) {
            $sql .= ",\n  PRIMARY KEY (`" . implode('`, `', $primaryKeys) . "`)";
        }
        
        // Add indexes
        foreach ($this->indexes as $index) {
            if ($index['type'] === 'unique') {
                $sql .= ",\n  UNIQUE KEY `{$index['name']}` (`" . implode('`, `', $index['columns']) . "`)";
            } else {
                $sql .= ",\n  KEY `{$index['name']}` (`" . implode('`, `', $index['columns']) . "`)";
            }
        }
        
        // Add foreign keys
        foreach ($this->foreignKeys as $fk) {
            $sql .= ",\n  CONSTRAINT `fk_{$this->table}_{$fk['column']}` FOREIGN KEY (`{$fk['column']}`) REFERENCES `{$fk['on']}` (`{$fk['references']}`)";
            if ($fk['onDelete']) {
                $sql .= " ON DELETE " . strtoupper($fk['onDelete']);
            }
        }
        
        $sql .= "\n) ENGINE={$this->engine} DEFAULT CHARSET={$this->charset} COLLATE={$this->collation}";
        
        return $sql;
    }

    /**
     * Get the table name
     */
    public function getTable(): string
    {
        return $this->table;
    }
}

/**
 * Foreign Key Definition Helper
 */
class ForeignKeyDefinition
{
    private Blueprint $blueprint;
    private string $column;
    private ?string $referencedColumn = null;

    public function __construct(Blueprint $blueprint, string $column)
    {
        $this->blueprint = $blueprint;
        $this->column = $column;
    }

    /**
     * Set the referenced column and table
     */
    public function references(string $column): static
    {
        $this->referencedColumn = $column;
        return $this;
    }

    /**
     * Set the referenced table
     */
    public function on(string $table): static
    {
        $this->blueprint->addForeignKey(
            $this->column,
            $this->referencedColumn ?? 'id',
            $table
        );
        return $this;
    }

    /**
     * Set the ON DELETE action
     */
    public function onDelete(string $action): static
    {
        // This would need to be implemented by storing the action and applying it when on() is called
        return $this;
    }
}
