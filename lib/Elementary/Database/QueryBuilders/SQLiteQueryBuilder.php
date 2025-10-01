<?php

declare(strict_types=1);

namespace Elementary\Database\QueryBuilders;

use Elementary\Database\Contracts\QueryBuilderInterface;
use Elementary\Database\Drivers\SQLiteDriver;
use PDO;

/**
 * SQLite Query Builder
 * 
 * Implements SQLite-specific query building and execution.
 * Builds SQL queries optimized for SQLite and handles SQLite-specific features.
 */
class SQLiteQueryBuilder implements QueryBuilderInterface
{
    protected SQLiteDriver $driver;
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
    protected bool $distinct = false;

    public function __construct(SQLiteDriver $driver)
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
     */
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Set the model class for result hydration
     */
    public function setModel(string $modelClass): self
    {
        $this->modelClass = $modelClass;
        return $this;
    }

    /**
     * Add SELECT columns
     */
    public function select(array $columns = ['*']): self
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Add WHERE clause
     */
    public function where(string $column, string $operator, $value): self
    {
        $this->wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value, 'type' => 'where'];
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Add WHERE IN clause
     */
    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = ['column' => $column, 'values' => $values, 'type' => 'whereIn'];
        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    /**
     * Add WHERE NOT IN clause
     */
    public function whereNotIn(string $column, array $values): self
    {
        $this->wheres[] = ['column' => $column, 'values' => $values, 'type' => 'whereNotIn'];
        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    /**
     * Add WHERE NULL clause
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = ['column' => $column, 'type' => 'whereNull'];
        return $this;
    }

    /**
     * Add WHERE NOT NULL clause
     */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = ['column' => $column, 'type' => 'whereNotNull'];
        return $this;
    }

    /**
     * Add ORDER BY clause
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy = $column . ' ' . strtoupper($direction);
        return $this;
    }

    /**
     * Add LIMIT clause
     */
    public function limit(int $number): self
    {
        $this->limit = $number;
        return $this;
    }

    /**
     * Add OFFSET clause
     */
    public function offset(int $number): self
    {
        $this->offset = $number;
        return $this;
    }

    /**
     * Get distinct values
     */
    public function distinct(): self
    {
        $this->distinct = true;
        return $this;
    }

    /**
     * Execute query and get all results
     */
    public function get(): array
    {
        $sql = $this->buildSelectQuery();
        $stmt = $this->executeQuery($sql, $this->bindings);
        
        $results = $stmt->fetchAll();
        
        if ($this->modelClass && !empty($results)) {
            return $this->hydrateModels($results);
        }
        
        return $results;
    }

    /**
     * Execute query and get first result
     */
    public function first()
    {
        $originalLimit = $this->limit;
        $this->limit = 1;
        
        $results = $this->get();
        
        $this->limit = $originalLimit;
        
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Find record by primary key
     */
    public function find($id)
    {
        return $this->where($this->primaryKey, '=', $id)->first();
    }

    /**
     * Insert data
     */
    public function insert(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->escapeIdentifier($this->table),
            implode(', ', array_map([$this, 'escapeIdentifier'], $columns)),
            implode(', ', $placeholders)
        );
        
        $stmt = $this->executeQuery($sql, array_values($data));
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Update records
     */
    public function update(array $data): int
    {
        if (empty($data)) {
            return 0;
        }

        $setParts = [];
        $bindings = [];
        
        foreach ($data as $column => $value) {
            $setParts[] = $this->escapeIdentifier($column) . ' = ?';
            $bindings[] = $value;
        }
        
        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $this->escapeIdentifier($this->table),
            implode(', ', $setParts),
            $this->buildWhereClause()
        );
        
        $bindings = array_merge($bindings, $this->bindings);
        $stmt = $this->executeQuery($sql, $bindings);
        
        return $stmt->rowCount();
    }

    /**
     * Delete records
     */
    public function delete(): int
    {
        $sql = sprintf(
            'DELETE FROM %s%s',
            $this->escapeIdentifier($this->table),
            $this->buildWhereClause()
        );
        
        $stmt = $this->executeQuery($sql, $this->bindings);
        
        return $stmt->rowCount();
    }

    /**
     * Get count of records
     */
    public function count(): int
    {
        $originalColumns = $this->columns;
        $originalLimit = $this->limit;
        $originalOffset = $this->offset;
        
        $this->columns = ['COUNT(*) as count'];
        $this->limit = null;
        $this->offset = null;
        
        $sql = $this->buildSelectQuery();
        $stmt = $this->executeQuery($sql, $this->bindings);
        $result = $stmt->fetch();
        
        // Restore original state
        $this->columns = $originalColumns;
        $this->limit = $originalLimit;
        $this->offset = $originalOffset;
        
        return (int) $result['count'];
    }

    /**
     * Check if any records exist
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Paginate results
     */
    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $totalCount = $this->count();
        $totalPages = ceil($totalCount / $perPage);
        $currentPage = max(1, $page);
        
        $this->limit = $perPage;
        $this->offset = ($currentPage - 1) * $perPage;
        
        $data = $this->get();
        
        return [
            'data' => $data,
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => $totalCount,
            'total_pages' => $totalPages,
            'has_next' => $currentPage < $totalPages,
            'has_prev' => $currentPage > 1,
        ];
    }

    /**
     * Debug method to see generated query and bindings
     */
    public function toDebugSql(): array
    {
        return [
            'sql' => $this->buildSelectQuery(),
            'bindings' => $this->bindings,
        ];
    }

    /**
     * Build SELECT query
     */
    protected function buildSelectQuery(): string
    {
        $distinct = $this->distinct ? 'DISTINCT ' : '';
        
        // Handle columns - escape identifiers but not functions like COUNT(*)
        $escapedColumns = [];
        foreach ($this->columns as $column) {
            if (strpos($column, '(') !== false || strpos($column, ' as ') !== false) {
                // Don't escape function calls or aliased columns
                $escapedColumns[] = $column;
            } else {
                $escapedColumns[] = $this->escapeIdentifier($column);
            }
        }
        $columns = implode(', ', $escapedColumns);
        
        $sql = sprintf(
            'SELECT %s%s FROM %s%s%s%s%s',
            $distinct,
            $columns,
            $this->escapeIdentifier($this->table),
            $this->buildJoinClause(),
            $this->buildWhereClause(),
            $this->buildGroupByClause(),
            $this->buildOrderByClause()
        );
        
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
            
            if ($this->offset !== null) {
                $sql .= ' OFFSET ' . $this->offset;
            }
        }
        
        return $sql;
    }

    /**
     * Build WHERE clause
     */
    protected function buildWhereClause(): string
    {
        if (empty($this->wheres)) {
            return '';
        }
        
        $conditions = [];
        
        foreach ($this->wheres as $where) {
            switch ($where['type']) {
                case 'where':
                    $conditions[] = $this->escapeIdentifier($where['column']) . ' ' . $where['operator'] . ' ?';
                    break;
                    
                case 'whereIn':
                    $placeholders = str_repeat('?,', count($where['values']) - 1) . '?';
                    $conditions[] = $this->escapeIdentifier($where['column']) . " IN ({$placeholders})";
                    break;
                    
                case 'whereNotIn':
                    $placeholders = str_repeat('?,', count($where['values']) - 1) . '?';
                    $conditions[] = $this->escapeIdentifier($where['column']) . " NOT IN ({$placeholders})";
                    break;
                    
                case 'whereNull':
                    $conditions[] = $this->escapeIdentifier($where['column']) . ' IS NULL';
                    break;
                    
                case 'whereNotNull':
                    $conditions[] = $this->escapeIdentifier($where['column']) . ' IS NOT NULL';
                    break;
            }
        }
        
        return ' WHERE ' . implode(' AND ', $conditions);
    }

    /**
     * Build JOIN clause
     */
    protected function buildJoinClause(): string
    {
        // JOIN implementation can be added here when needed
        return '';
    }

    /**
     * Build GROUP BY clause
     */
    protected function buildGroupByClause(): string
    {
        if (empty($this->groupBy)) {
            return '';
        }
        
        return ' GROUP BY ' . implode(', ', array_map([$this, 'escapeIdentifier'], $this->groupBy));
    }

    /**
     * Build ORDER BY clause
     */
    protected function buildOrderByClause(): string
    {
        if ($this->orderBy === null) {
            return '';
        }
        
        return ' ORDER BY ' . $this->orderBy;
    }

    /**
     * Execute a query
     */
    protected function executeQuery(string $sql, array $bindings = []): \PDOStatement
    {
        $connection = $this->driver->getConnection();
        $stmt = $connection->prepare($sql);
        $stmt->execute($bindings);
        
        $this->driver->recordQueryExecution();
        
        return $stmt;
    }

    /**
     * Escape database identifiers for SQLite
     */
    protected function escapeIdentifier(string $identifier): string
    {
        if ($identifier === '*') {
            return '*';
        }
        
        // SQLite uses double quotes for identifiers
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * Hydrate models from raw data
     */
    protected function hydrateModels(array $results): array
    {
        $models = [];
        
        foreach ($results as $result) {
            $model = new $this->modelClass();
            
            if (method_exists($model, 'setAttributes')) {
                $model->setAttributes($result);
            }
            
            $models[] = $model;
        }
        
        return $models;
    }

    /**
     * Reset query builder state
     */
    public function newQuery(): self
    {
        return new static($this->driver);
    }
}