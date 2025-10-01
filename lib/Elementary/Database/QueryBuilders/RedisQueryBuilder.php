<?php

declare(strict_types=1);

namespace Elementary\Database\QueryBuilders;

use Elementary\Database\Contracts\QueryBuilderInterface;
use Elementary\Database\Drivers\RedisDriver;
use Redis;
use RedisException;

/**
 * Redis Query Builder
 * 
 * Implements Redis-specific query building and execution.
 * Translates common database operations to Redis commands while maintaining
 * the familiar QueryBuilder interface.
 */
class RedisQueryBuilder implements QueryBuilderInterface
{
    protected RedisDriver $driver;
    protected string $table = '';
    protected ?string $modelClass = null;
    protected string $primaryKey = 'id';

    protected array $wheres = [];
    protected ?string $orderBy = null;
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected string|array $keyPattern = '*';

    public function __construct(RedisDriver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Set the table/key prefix for the query
     */
    public function table(string $table): QueryBuilderInterface
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
     * Add SELECT columns (Redis stores complete objects)
     */
    public function select(array $columns = ['*']): QueryBuilderInterface
    {
        // Redis stores complete objects, so SELECT is mostly ignored
        // Could be used for field filtering in the future
        return $this;
    }

    /**
     * Add WHERE clause (translated to Redis key patterns)
     */
    public function where(string $column, string $operator, $value): QueryBuilderInterface
    {
        if ($column === $this->primaryKey) {
            // Primary key lookup - direct key access
            $this->keyPattern = $this->makeKey($value);
        } else {
            // For other fields, we'll need to scan and filter
            $this->wheres[] = [$column, $operator, $value];
        }
        return $this;
    }

    /**
     * Add WHERE IN clause
     */
    public function whereIn(string $column, array $values): QueryBuilderInterface
    {
        if ($column === $this->primaryKey) {
            // Multiple key lookup
            $this->keyPattern = array_map([$this, 'makeKey'], $values);
        } else {
            $this->wheres[] = [$column, 'IN', $values];
        }
        return $this;
    }

    /**
     * Add WHERE NOT IN clause
     */
    public function whereNotIn(string $column, array $values): QueryBuilderInterface
    {
        $this->wheres[] = [$column, 'NOT IN', $values];
        return $this;
    }

    /**
     * Add WHERE NULL clause
     */
    public function whereNull(string $column): QueryBuilderInterface
    {
        $this->wheres[] = [$column, 'IS', null];
        return $this;
    }

    /**
     * Add WHERE NOT NULL clause
     */
    public function whereNotNull(string $column): QueryBuilderInterface
    {
        $this->wheres[] = [$column, 'IS NOT', null];
        return $this;
    }

    /**
     * Add ORDER BY clause (limited Redis support)
     */
    public function orderBy(string $column, string $direction = 'ASC'): QueryBuilderInterface
    {
        $this->orderBy = [$column, $direction];
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
        $connection = $this->driver->getConnection();
        
        try {
            if (is_array($this->keyPattern)) {
                // Multiple key lookup
                $results = [];
                foreach ($this->keyPattern as $key) {
                    $data = $connection->get($key);
                    if ($data !== false) {
                        $results[] = $this->hydrateModel($data, $key);
                    }
                }
            } elseif ($this->keyPattern !== '*') {
                // Single key lookup
                $data = $connection->get($this->keyPattern);
                $results = $data !== false ? [$this->hydrateModel($data, $this->keyPattern)] : [];
            } else {
                // Scan for all keys matching table pattern
                $results = $this->scanAndFilter($connection);
            }

            $this->driver->recordQueryExecution();
            
            return $this->applyLimitOffset($this->applyOrdering($results));
            
        } catch (RedisException $e) {
            throw new \RuntimeException("Redis query failed: " . $e->getMessage(), 0, $e);
        } finally {
            if ($this->driver->supportsPooling()) {
                $this->driver->returnConnection($connection);
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
     * Insert data
     */
    public function insert(array $data): bool
    {
        $connection = $this->driver->getConnection();
        
        try {
            $key = $this->makeKey($data[$this->primaryKey] ?? null);
            
            // Handle TTL if specified
            $ttl = $data['_ttl'] ?? null;
            unset($data['_ttl']);
            
            if ($ttl) {
                $result = $connection->setex($key, $ttl, $data);
            } else {
                $result = $connection->set($key, $data);
            }
            
            $this->driver->recordQueryExecution();
            return $result;
            
        } catch (RedisException $e) {
            throw new \RuntimeException("Redis insert failed: " . $e->getMessage(), 0, $e);
        } finally {
            if ($this->driver->supportsPooling()) {
                $this->driver->returnConnection($connection);
            }
        }
    }

    /**
     * Update records
     */
    public function update(array $data): int
    {
        $connection = $this->driver->getConnection();
        $updated = 0;
        
        try {
            if (is_array($this->keyPattern)) {
                // Update multiple keys
                foreach ($this->keyPattern as $key) {
                    if ($connection->exists($key)) {
                        $existing = $connection->get($key);
                        if (is_array($existing)) {
                            $merged = array_merge($existing, $data);
                            if ($connection->set($key, $merged)) {
                                $updated++;
                            }
                        }
                    }
                }
            } elseif ($this->keyPattern !== '*') {
                // Update single key
                if ($connection->exists($this->keyPattern)) {
                    $existing = $connection->get($this->keyPattern);
                    if (is_array($existing)) {
                        $merged = array_merge($existing, $data);
                        if ($connection->set($this->keyPattern, $merged)) {
                            $updated = 1;
                        }
                    }
                }
            } else {
                // Update all matching keys (scan and update)
                $keys = $this->getMatchingKeys($connection);
                foreach ($keys as $key) {
                    $existing = $connection->get($key);
                    if (is_array($existing) && $this->matchesWhereConditions($existing)) {
                        $merged = array_merge($existing, $data);
                        if ($connection->set($key, $merged)) {
                            $updated++;
                        }
                    }
                }
            }
            
            $this->driver->recordQueryExecution();
            return $updated;
            
        } catch (RedisException $e) {
            throw new \RuntimeException("Redis update failed: " . $e->getMessage(), 0, $e);
        } finally {
            if ($this->driver->supportsPooling()) {
                $this->driver->returnConnection($connection);
            }
        }
    }

    /**
     * Delete records
     */
    public function delete(): int
    {
        $connection = $this->driver->getConnection();
        
        try {
            $deleted = 0;
            
            if (is_array($this->keyPattern)) {
                // Delete multiple specific keys
                $deleted = $connection->del($this->keyPattern);
            } elseif ($this->keyPattern !== '*') {
                // Delete single key
                $deleted = $connection->del($this->keyPattern);
            } else {
                // Delete all matching keys
                $keys = $this->getMatchingKeys($connection);
                $toDelete = [];
                
                foreach ($keys as $key) {
                    $data = $connection->get($key);
                    if (is_array($data) && $this->matchesWhereConditions($data)) {
                        $toDelete[] = $key;
                    }
                }
                
                if (!empty($toDelete)) {
                    $deleted = $connection->del($toDelete);
                }
            }
            
            $this->driver->recordQueryExecution();
            return $deleted;
            
        } catch (RedisException $e) {
            throw new \RuntimeException("Redis delete failed: " . $e->getMessage(), 0, $e);
        } finally {
            if ($this->driver->supportsPooling()) {
                $this->driver->returnConnection($connection);
            }
        }
    }

    /**
     * Get count of records
     */
    public function count(): int
    {
        $connection = $this->driver->getConnection();
        
        try {
            if (is_array($this->keyPattern)) {
                // Count specific keys
                $count = 0;
                foreach ($this->keyPattern as $key) {
                    if ($connection->exists($key)) {
                        $count++;
                    }
                }
                return $count;
            } elseif ($this->keyPattern !== '*') {
                // Count single key
                return $connection->exists($this->keyPattern) ? 1 : 0;
            } else {
                // Count all matching keys
                $keys = $this->getMatchingKeys($connection);
                $count = 0;
                
                foreach ($keys as $key) {
                    $data = $connection->get($key);
                    if (is_array($data) && $this->matchesWhereConditions($data)) {
                        $count++;
                    }
                }
                
                return $count;
            }
            
        } catch (RedisException $e) {
            throw new \RuntimeException("Redis count failed: " . $e->getMessage(), 0, $e);
        } finally {
            if ($this->driver->supportsPooling()) {
                $this->driver->returnConnection($connection);
            }
        }
    }

    /**
     * Check if any records exist
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Get distinct values (Redis limitation - returns unique values)
     */
    public function distinct(): QueryBuilderInterface
    {
        // Redis doesn't have built-in DISTINCT, but we can simulate it
        return $this;
    }

    /**
     * Paginate results
     */
    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $offset = ($page - 1) * $perPage;
        $total = $this->count();
        
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
     * Debug method to see generated Redis operations
     */
    public function toDebugSql(): array
    {
        return [
            'operation' => 'Redis Key Operations',
            'key_pattern' => $this->keyPattern,
            'table' => $this->table,
            'wheres' => $this->wheres,
        ];
    }

    /**
     * Create Redis key from table and ID
     */
    private function makeKey($id): string
    {
        return $this->table . ':' . $id;
    }

    /**
     * Get all keys matching table pattern
     */
    private function getMatchingKeys(Redis $connection): array
    {
        $pattern = $this->table . ':*';
        $keys = [];
        $iterator = null;
        
        // Use SCAN for memory-efficient key iteration
        while (false !== ($result = $connection->scan($iterator, $pattern))) {
            $keys = array_merge($keys, $result);
        }
        
        return $keys;
    }

    /**
     * Scan and filter results based on WHERE conditions
     */
    private function scanAndFilter(Redis $connection): array
    {
        $keys = $this->getMatchingKeys($connection);
        $results = [];
        
        foreach ($keys as $key) {
            $data = $connection->get($key);
            if (is_array($data) && $this->matchesWhereConditions($data)) {
                $results[] = $this->hydrateModel($data, $key);
            }
        }
        
        return $results;
    }

    /**
     * Check if data matches WHERE conditions
     */
    private function matchesWhereConditions(array $data): bool
    {
        foreach ($this->wheres as [$column, $operator, $value]) {
            $fieldValue = $data[$column] ?? null;
            
            if (!$this->evaluateCondition($fieldValue, $operator, $value)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Evaluate a single WHERE condition
     */
    private function evaluateCondition($fieldValue, string $operator, $value): bool
    {
        return match ($operator) {
            '=' => $fieldValue == $value,
            '!=' => $fieldValue != $value,
            '>' => $fieldValue > $value,
            '<' => $fieldValue < $value,
            '>=' => $fieldValue >= $value,
            '<=' => $fieldValue <= $value,
            'IN' => in_array($fieldValue, (array) $value),
            'NOT IN' => !in_array($fieldValue, (array) $value),
            'IS' => $value === null ? $fieldValue === null : $fieldValue == $value,
            'IS NOT' => $value === null ? $fieldValue !== null : $fieldValue != $value,
            default => false,
        };
    }

    /**
     * Hydrate model instance from Redis data
     */
    private function hydrateModel(array $data, string $key): mixed
    {
        if ($this->modelClass) {
            $model = new $this->modelClass($data);
            $model->setExists(true);
            return $model;
        }
        
        return $data;
    }

    /**
     * Apply ordering to results
     */
    private function applyOrdering(array $results): array
    {
        if ($this->orderBy) {
            [$column, $direction] = $this->orderBy;
            $ascending = strtoupper($direction) === 'ASC';
            
            usort($results, function ($a, $b) use ($column, $ascending) {
                $aVal = is_object($a) ? $a->$column : $a[$column];
                $bVal = is_object($b) ? $b->$column : $b[$column];
                
                $comparison = $aVal <=> $bVal;
                return $ascending ? $comparison : -$comparison;
            });
        }
        
        return $results;
    }

    /**
     * Apply limit and offset to results
     */
    private function applyLimitOffset(array $results): array
    {
        $offset = $this->offset ?? 0;
        $limit = $this->limit;
        
        if ($offset || $limit) {
            $results = array_slice($results, $offset, $limit);
        }
        
        return $results;
    }

    public function with(string|array $relations): QueryBuilderInterface
    {
        return $this;
    }
}
