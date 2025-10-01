<?php

declare(strict_types=1);

namespace Elementary\Database\Schema;

use Elementary\Database\Contracts\SchemaBuilderInterface;
use Elementary\Database\Drivers\RedisDriver;
use Redis;
use RedisException;

/**
 * Redis Schema Builder
 * 
 * Implements Redis-specific schema operations for key management and introspection.
 * Redis is schema-less, but we provide utilities for key pattern management
 * and namespace operations.
 */
class RedisSchemaBuilder implements SchemaBuilderInterface
{
    protected RedisDriver $driver;

    public function __construct(RedisDriver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Check if a "table" (key pattern) exists
     */
    public function hasTable(string $table): bool
    {
        $connection = $this->driver->getConnection();
        
        try {
            $pattern = $table . ':*';
            $iterator = null;
            
            // Check if any keys match the pattern
            $result = $connection->scan($iterator, $pattern, 1);
            return !empty($result);
            
        } catch (RedisException $e) {
            throw new \RuntimeException("Redis schema check failed: " . $e->getMessage(), 0, $e);
        } finally {
            if ($this->driver->supportsPooling()) {
                $this->driver->returnConnection($connection);
            }
        }
    }

    /**
     * Create a new "table" (key namespace) - alias for createTable
     */
    public function create(string $table, callable $callback): void
    {
        $this->createTable($table, $callback);
    }

    /**
     * Create a new "table" (key namespace)
     */
    public function createTable(string $table, callable $callback): void
    {
        // Redis is schema-less, but we can create a metadata key
        $connection = $this->driver->getConnection();
        
        try {
            $metadataKey = "_schema:{$table}:metadata";
            $metadata = [
                'created_at' => date('Y-m-d H:i:s'),
                'table_name' => $table,
                'type' => 'key_value_store',
                'description' => "Key-value store for {$table}",
            ];
            
            $connection->set($metadataKey, $metadata);
            $this->driver->recordQueryExecution();
            
            // Call the callback for additional setup if needed
            if ($callback) {
                $callback($this);
            }
            
        } catch (RedisException $e) {
            throw new \RuntimeException("Redis table creation failed: " . $e->getMessage(), 0, $e);
        } finally {
            if ($this->driver->supportsPooling()) {
                $this->driver->returnConnection($connection);
            }
        }
    }

    /**
     * Drop a "table" if it exists (delete all keys matching pattern)
     */
    public function dropIfExists(string $table): void
    {
        if ($this->hasTable($table)) {
            $this->dropTable($table);
        }
    }

    /**
     * Drop a "table" (delete all keys matching pattern)
     */
    public function dropTable(string $table): void
    {
        $connection = $this->driver->getConnection();
        
        try {
            $pattern = $table . ':*';
            $keys = [];
            $iterator = null;
            
            // Collect all keys matching the pattern
            while (false !== ($result = $connection->scan($iterator, $pattern))) {
                $keys = array_merge($keys, $result);
            }
            
            // Delete metadata key as well
            $metadataKey = "_schema:{$table}:metadata";
            if ($connection->exists($metadataKey)) {
                $keys[] = $metadataKey;
            }
            
            // Delete all keys in batches
            if (!empty($keys)) {
                $batchSize = 1000;
                $batches = array_chunk($keys, $batchSize);
                
                foreach ($batches as $batch) {
                    $connection->del($batch);
                }
            }
            
            $this->driver->recordQueryExecution();
            
        } catch (RedisException $e) {
            throw new \RuntimeException("Redis table drop failed: " . $e->getMessage(), 0, $e);
        } finally {
            if ($this->driver->supportsPooling()) {
                $this->driver->returnConnection($connection);
            }
        }
    }

    /**
     * Rename a "table" (change key prefix)
     */
    public function renameTable(string $from, string $to): void
    {
        $connection = $this->driver->getConnection();
        
        try {
            $pattern = $from . ':*';
            $keys = [];
            $iterator = null;
            
            // Get all keys with old prefix
            while (false !== ($result = $connection->scan($iterator, $pattern))) {
                $keys = array_merge($keys, $result);
            }
            
            // Rename each key
            foreach ($keys as $oldKey) {
                $newKey = str_replace($from . ':', $to . ':', $oldKey);
                $value = $connection->get($oldKey);
                
                if ($value !== false) {
                    $connection->set($newKey, $value);
                    $connection->del($oldKey);
                }
            }
            
            // Update metadata key
            $oldMetadata = "_schema:{$from}:metadata";
            $newMetadata = "_schema:{$to}:metadata";
            
            if ($connection->exists($oldMetadata)) {
                $metadata = $connection->get($oldMetadata);
                if (is_array($metadata)) {
                    $metadata['table_name'] = $to;
                    $metadata['renamed_at'] = date('Y-m-d H:i:s');
                    $connection->set($newMetadata, $metadata);
                    $connection->del($oldMetadata);
                }
            }
            
            $this->driver->recordQueryExecution();
            
        } catch (RedisException $e) {
            throw new \RuntimeException("Redis table rename failed: " . $e->getMessage(), 0, $e);
        } finally {
            if ($this->driver->supportsPooling()) {
                $this->driver->returnConnection($connection);
            }
        }
    }

    /**
     * Modify an existing "table" (update metadata)
     */
    public function modifyTable(string $table, callable $callback): void
    {
        $connection = $this->driver->getConnection();
        
        try {
            $metadataKey = "_schema:{$table}:metadata";
            $metadata = $connection->get($metadataKey) ?: [];
            
            if (is_array($metadata)) {
                $metadata['modified_at'] = date('Y-m-d H:i:s');
                $connection->set($metadataKey, $metadata);
            }
            
            // Call the callback for modifications
            if ($callback) {
                $callback($this);
            }
            
            $this->driver->recordQueryExecution();
            
        } catch (RedisException $e) {
            throw new \RuntimeException("Redis table modification failed: " . $e->getMessage(), 0, $e);
        } finally {
            if ($this->driver->supportsPooling()) {
                $this->driver->returnConnection($connection);
            }
        }
    }

    /**
     * Get list of all "tables" (key prefixes)
     */
    public function getTables(): array
    {
        $connection = $this->driver->getConnection();
        
        try {
            $pattern = '_schema:*:metadata';
            $keys = [];
            $iterator = null;
            
            // Get all metadata keys
            while (false !== ($result = $connection->scan($iterator, $pattern))) {
                $keys = array_merge($keys, $result);
            }
            
            $tables = [];
            foreach ($keys as $key) {
                // Extract table name from metadata key
                if (preg_match('/^_schema:(.+):metadata$/', $key, $matches)) {
                    $tables[] = $matches[1];
                }
            }
            
            $this->driver->recordQueryExecution();
            return $tables;
            
        } catch (RedisException $e) {
            throw new \RuntimeException("Redis table listing failed: " . $e->getMessage(), 0, $e);
        } finally {
            if ($this->driver->supportsPooling()) {
                $this->driver->returnConnection($connection);
            }
        }
    }

    /**
     * Get "columns" (sample key structure)
     */
    public function getColumns(string $table): array
    {
        $connection = $this->driver->getConnection();
        
        try {
            $pattern = $table . ':*';
            $iterator = null;
            $columns = [];
            
            // Sample a few keys to determine structure
            $sampleKeys = [];
            $count = 0;
            
            while (false !== ($result = $connection->scan($iterator, $pattern, 10))) {
                $sampleKeys = array_merge($sampleKeys, $result);
                $count++;
                if ($count >= 5) break; // Sample first 5 batches
            }
            
            // Analyze structure from sample keys
            foreach (array_slice($sampleKeys, 0, 10) as $key) {
                $data = $connection->get($key);
                if (is_array($data)) {
                    foreach ($data as $field => $value) {
                        if (!isset($columns[$field])) {
                            $columns[$field] = [
                                'name' => $field,
                                'type' => $this->getRedisType($value),
                                'sample_value' => $value,
                            ];
                        }
                    }
                }
            }
            
            $this->driver->recordQueryExecution();
            return array_values($columns);
            
        } catch (RedisException $e) {
            throw new \RuntimeException("Redis column analysis failed: " . $e->getMessage(), 0, $e);
        } finally {
            if ($this->driver->supportsPooling()) {
                $this->driver->returnConnection($connection);
            }
        }
    }

    /**
     * Check if a "column" (field) exists in sample data
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
     * Get indexes (Redis doesn't have traditional indexes)
     */
    public function getIndexes(string $table): array
    {
        $connection = $this->driver->getConnection();
        
        try {
            // Check for Redis-specific indexes (sorted sets, etc.)
            $indexes = [];
            
            // Check for sorted set indexes
            $sortedSetPattern = "_index:{$table}:*";
            $iterator = null;
            
            while (false !== ($result = $connection->scan($iterator, $sortedSetPattern))) {
                foreach ($result as $indexKey) {
                    if (preg_match("/^_index:{$table}:(.+)$/", $indexKey, $matches)) {
                        $indexes[] = [
                            'name' => $matches[1],
                            'type' => 'sorted_set',
                            'key' => $indexKey,
                        ];
                    }
                }
            }
            
            $this->driver->recordQueryExecution();
            return $indexes;
            
        } catch (RedisException $e) {
            throw new \RuntimeException("Redis index listing failed: " . $e->getMessage(), 0, $e);
        } finally {
            if ($this->driver->supportsPooling()) {
                $this->driver->returnConnection($connection);
            }
        }
    }

    /**
     * Check if an index exists
     */
    public function hasIndex(string $table, string $index): bool
    {
        $connection = $this->driver->getConnection();
        
        try {
            $indexKey = "_index:{$table}:{$index}";
            return $connection->exists($indexKey);
            
        } catch (RedisException $e) {
            throw new \RuntimeException("Redis index check failed: " . $e->getMessage(), 0, $e);
        } finally {
            if ($this->driver->supportsPooling()) {
                $this->driver->returnConnection($connection);
            }
        }
    }

    /**
     * Create a sorted set index for a field
     */
    public function createIndex(string $table, string $field): void
    {
        $connection = $this->driver->getConnection();
        
        try {
            $indexKey = "_index:{$table}:{$field}";
            $pattern = $table . ':*';
            $iterator = null;
            
            // Build index from existing data
            while (false !== ($result = $connection->scan($iterator, $pattern))) {
                foreach ($result as $key) {
                    $data = $connection->get($key);
                    if (is_array($data) && isset($data[$field])) {
                        $score = is_numeric($data[$field]) ? (float) $data[$field] : 0;
                        $connection->zadd($indexKey, $score, $key);
                    }
                }
            }
            
            $this->driver->recordQueryExecution();
            
        } catch (RedisException $e) {
            throw new \RuntimeException("Redis index creation failed: " . $e->getMessage(), 0, $e);
        } finally {
            if ($this->driver->supportsPooling()) {
                $this->driver->returnConnection($connection);
            }
        }
    }

    /**
     * Get Redis data type for PHP value
     */
    private function getRedisType($value): string
    {
        return match (gettype($value)) {
            'integer' => 'integer',
            'double' => 'float',
            'string' => 'string',
            'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'object',
            'NULL' => 'null',
            default => 'mixed',
        };
    }
}