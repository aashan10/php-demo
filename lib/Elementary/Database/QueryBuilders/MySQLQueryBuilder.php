<?php

declare(strict_types=1);

namespace Elementary\Database\QueryBuilders;

use Elementary\Database\Contracts\QueryBuilderInterface;
use Elementary\Database\Drivers\MySQLDriver;
use PDO;

/**
 * MySQL Query Builder
 * 
 * Implements MySQL-specific query building and execution.
 * Builds SQL queries optimized for MySQL and handles MySQL-specific features.
 */
class MySQLQueryBuilder implements QueryBuilderInterface
{
    protected MySQLDriver $driver;
    protected string $table = '';
    protected ?string $modelClass = null;
    protected string $primaryKey = 'id';

    protected array $columns = ['*'];
    protected array $wheres = [];
    protected array $bindings = [];
    protected ?string $orderBy = null;
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $joins = [];
    protected array $groupBy = [];
    protected array $having = [];
    protected array $with = [];

    public function __construct(MySQLDriver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Set the relationships to be eager loaded.
     */
    public function with(string|array $relations): self
    {
        $this->with = is_array($relations) ? $relations : func_get_args();
        return $this;
    }

    /**
     * Set the table for the query
     */    public function table(string $table): QueryBuilderInterface
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Set the model class for result hydration
     */
    public function setModel(string $modelClass): QueryBuilderInterface
    {
        $this->modelClass = $modelClass;
        return $this;
    }

    /**
     * Set primary key (for backward compatibility)
     */
    public function setPrimaryKey(string $key): self
    {
        $this->primaryKey = $key;
        return $this;
    }

    /**
     * Add SELECT columns
     */
    public function select(array $columns = ['*']): QueryBuilderInterface
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Add WHERE clause
     */
    public function where(string $column, string $operator, $value): QueryBuilderInterface
    {
        $this->wheres[] = "`{$column}` {$operator} ?";
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Add WHERE IN clause
     */
    public function whereIn(string $column, array $values): QueryBuilderInterface
    {
        $placeholders = str_repeat('?,', count($values) - 1) . '?';
        $this->wheres[] = "`{$column}` IN ({$placeholders})";
        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    /**
     * Add WHERE NOT IN clause
     */
    public function whereNotIn(string $column, array $values): QueryBuilderInterface
    {
        $placeholders = str_repeat('?,', count($values) - 1) . '?';
        $this->wheres[] = "`{$column}` NOT IN ({$placeholders})";
        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    /**
     * Add WHERE NULL clause
     */
    public function whereNull(string $column): QueryBuilderInterface
    {
        $this->wheres[] = "`{$column}` IS NULL";
        return $this;
    }

    /**
     * Add WHERE NOT NULL clause
     */
    public function whereNotNull(string $column): QueryBuilderInterface
    {
        $this->wheres[] = "`{$column}` IS NOT NULL";
        return $this;
    }

    /**
     * Add ORDER BY clause
     */
    public function orderBy(string $column, string $direction = 'ASC'): QueryBuilderInterface
    {
        $this->orderBy = "ORDER BY `{$column}` {$direction}";
        return $this;
    }

    /**
     * Add LIMIT clause
     */
    public function limit(int $number): QueryBuilderInterface
    {
        $this->limit = $number;
        return $this;
    }

    /**
     * Add OFFSET clause
     */
    public function offset(int $number): QueryBuilderInterface
    {
        $this->offset = $number;
        return $this;
    }

    /**
     * Execute query and get all results
     */
    public function get(): array
    {
        $pooledConn = $this->driver->getPooledConnection();
        
        try {
            $stmt = $pooledConn->prepare($this->toSelectSql());
            $stmt->execute($this->bindings);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->driver->recordQueryExecution();

            if ($this->modelClass) {
                $results = array_map(function ($row) {
                    $model = new $this->modelClass();
                    $model->hydrate($row);
                    $model->setExists(true); // Mark as existing in database
                    return $model;
                }, $results);

                if (!empty($this->with)) {
                    $this->loadRelationships($results);
                }
            }

            return $results;
        } finally {
            $pooledConn->release();
        }
    }

    private function loadRelationships(array &$models): void
    {
        if (empty($models)) {
            return;
        }

        foreach ($this->with as $relationName) {
            $relation = (new $this->modelClass)->{$relationName}();
            $foreignKey = $relation->getForeignKeyName();
            $localKey = $relation->getLocalKeyName();

            $keys = array_unique(array_map(fn($model) => $model->{$localKey}, $models));

            $relatedModels = $relation->whereIn($foreignKey, $keys)->get();

            $isSingular = $relation instanceof \Elementary\Database\Relations\HasOne || $relation instanceof \Elementary\Database\Relations\BelongsTo;

            $grouped = [];
            foreach ($relatedModels as $relatedModelInstance) {
                if ($isSingular) {
                    $grouped[$relatedModelInstance->{$foreignKey}] = $relatedModelInstance;
                } else {
                    $grouped[$relatedModelInstance->{$foreignKey}][] = $relatedModelInstance;
                }
            }

            foreach ($models as $model) {
                $model->setRelation(
                    $relationName, 
                    $grouped[$model->{$localKey}] ?? ($isSingular ? null : [])
                );
            }
        }
    }

    /**
     * Execute query and get first result
     */
    public function first()
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * Find record by primary key
     */
    public function find($id)
    {
        return $this->where($this->primaryKey, '=', $id)->first();
    }

    /**
     * Insert data into table
     */
    public function insert(array $data): bool
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $this->table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        $pooledConn = $this->driver->getPooledConnection();
        try {
            $stmt = $pooledConn->prepare($sql);
            $result = $stmt->execute(array_values($data));
            $this->driver->recordQueryExecution();
            return $result;
        } finally {
            $pooledConn->release();
        }
    }

    /**
     * Update records
     */
    public function update(array $data): int
    {
        if (empty($this->wheres)) {
            throw new \RuntimeException('Attempting to update without a WHERE clause.');
        }

        $columns = array_keys($data);
        $setPlaceholders = array_map(fn($col) => "`{$col}` = ?", $columns);

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $this->table,
            implode(', ', $setPlaceholders),
            implode(' AND ', $this->wheres)
        );

        $bindings = array_merge(array_values($data), $this->bindings);
        
        $pooledConn = $this->driver->getPooledConnection();
        try {
            $stmt = $pooledConn->prepare($sql);
            $stmt->execute($bindings);
            $this->driver->recordQueryExecution();
            return $stmt->rowCount();
        } finally {
            $pooledConn->release();
        }
    }

    /**
     * Delete records
     */
    public function delete(): int
    {
        if (empty($this->wheres)) {
            throw new \RuntimeException('Attempting to delete without a WHERE clause.');
        }

        $sql = sprintf(
            'DELETE FROM `%s` WHERE %s',
            $this->table,
            implode(' AND ', $this->wheres)
        );

        $pooledConn = $this->driver->getPooledConnection();
        try {
            $stmt = $pooledConn->prepare($sql);
            $stmt->execute($this->bindings);
            $this->driver->recordQueryExecution();
            return $stmt->rowCount();
        } finally {
            $pooledConn->release();
        }
    }

    /**
     * Get count of records
     */
    public function count(): int
    {
        $originalColumns = $this->columns;
        $this->columns = ['COUNT(*) as count'];
        
        $result = $this->first();
        
        $this->columns = $originalColumns; // Restore original columns
        
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Check if any records exist
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Get distinct values
     */
    public function distinct(): QueryBuilderInterface
    {
        $this->columns = array_map(function($column) {
            return $column === '*' ? 'DISTINCT *' : "DISTINCT {$column}";
        }, $this->columns);
        
        return $this;
    }

    /**
     * Paginate results
     */
    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $offset = ($page - 1) * $perPage;
        
        // Get total count
        $totalQuery = clone $this;
        $total = $totalQuery->count();
        
        // Get paginated results
        $results = $this->limit($perPage)->offset($offset)->get();
        
        return [
            'data' => $results,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total),
        ];
    }

    /**
     * Generate SELECT SQL
     */
    public function toSelectSql(): string
    {
        $sql = "SELECT " . implode(', ', $this->columns) . " FROM `{$this->table}`";

        // Add JOINs
        if (!empty($this->joins)) {
            $sql .= " " . implode(' ', $this->joins);
        }

        // Add WHERE clauses
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }

        // Add GROUP BY
        if (!empty($this->groupBy)) {
            $sql .= " GROUP BY " . implode(', ', $this->groupBy);
        }

        // Add HAVING
        if (!empty($this->having)) {
            $sql .= " HAVING " . implode(' AND ', $this->having);
        }

        // Add ORDER BY
        if ($this->orderBy) {
            $sql .= " " . $this->orderBy;
        }

        // Add LIMIT
        if ($this->limit) {
            $sql .= " LIMIT " . $this->limit;
        }

        // Add OFFSET
        if ($this->offset) {
            $sql .= " OFFSET " . $this->offset;
        }

        return $sql;
    }

    /**
     * Debug method to see generated SQL and bindings
     */
    public function toDebugSql(): array
    {
        return [
            'sql' => $this->toSelectSql(),
            'bindings' => $this->bindings,
        ];
    }

    /**
     * Clone the query builder for complex operations
     */
    public function __clone()
    {
        // Ensure arrays are properly cloned
        $this->columns = [...$this->columns];
        $this->wheres = [...$this->wheres];
        $this->bindings = [...$this->bindings];
        $this->joins = [...$this->joins];
        $this->groupBy = [...$this->groupBy];
        $this->having = [...$this->having];
    }
}