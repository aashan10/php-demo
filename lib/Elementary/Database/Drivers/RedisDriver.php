<?php

declare(strict_types=1);

namespace Elementary\Database\Drivers;

use Elementary\Database\Contracts\QueryBuilderInterface;
use Elementary\Database\Contracts\SchemaBuilderInterface;
use Elementary\Database\QueryBuilders\RedisQueryBuilder;
use Elementary\Database\Schema\RedisSchemaBuilder;
use Redis;
use RedisException;

/**
 * Redis Database Driver
 * 
 * Implements Redis-specific database operations using the phpredis extension.
 * Provides high-performance key-value operations with connection pooling
 * and automatic serialization/deserialization.
 */
class RedisDriver extends AbstractDriver
{
    private ?Redis $connection = null;
    private array $connectionPool = [];
    private int $currentConnections = 0;

    /**
     * Get a query builder instance for Redis
     */
    public function getQueryBuilder(): QueryBuilderInterface
    {
        return new RedisQueryBuilder($this);
    }

    /**
     * Get a schema builder instance for Redis
     */
    public function getSchemaBuilder(): SchemaBuilderInterface
    {
        return new RedisSchemaBuilder($this);
    }

    /**
     * Get the raw Redis connection
     */
    public function getConnection(): Redis
    {
        if ($this->supportsPooling()) {
            return $this->getPooledConnection();
        }
        
        return $this->getSingletonConnection();
    }

    /**
     * Get a pooled Redis connection
     */
    public function getPooledConnection(): Redis
    {
        $maxConnections = $this->getConfig('pool.max_connections', 10);
        $minConnections = $this->getConfig('pool.min_connections', 2);
        
        // Try to get connection from pool
        if (!empty($this->connectionPool)) {
            $connection = array_pop($this->connectionPool);
            if ($this->isConnectionAlive($connection)) {
                return $connection;
            }
        }
        
        // Create new connection if under limit
        if ($this->currentConnections < $maxConnections) {
            $connection = $this->createRedisConnection();
            $this->currentConnections++;
            $this->recordConnection();
            return $connection;
        }
        
        // Fallback to singleton if pool is full
        return $this->getSingletonConnection();
    }

    /**
     * Return connection to pool
     */
    public function returnConnection(Redis $connection): void
    {
        if ($this->supportsPooling() && $this->isConnectionAlive($connection)) {
            $this->connectionPool[] = $connection;
        }
    }

    /**
     * Get singleton connection (fallback)
     */
    private function getSingletonConnection(): Redis
    {
        if ($this->connection === null) {
            $this->connection = $this->createRedisConnection();
            $this->recordConnection();
        }
        
        return $this->connection;
    }

    /**
     * Create a new Redis connection
     */
    public function createRedisConnection(): Redis
    {
        $redis = new Redis();
        
        try {
            $host = $this->getConfig('host', 'localhost');
            $port = $this->getConfig('port', 6379);
            $timeout = $this->getConfig('pool.connection_timeout', 10);
            
            $connected = $redis->connect($host, $port, $timeout);
            
            if (!$connected) {
                throw new \RuntimeException("Could not connect to Redis server at {$host}:{$port}");
            }
            
            // Authenticate if password is provided
            $password = $this->getConfig('password');
            if ($password !== null) {
                $redis->auth($password);
            }
            
            // Select database
            $database = $this->getConfig('database', 0);
            $redis->select($database);
            
            // Set serialization options
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
            $redis->setOption(Redis::OPT_PREFIX, $this->getConfig('prefix', ''));
            
            return $redis;
            
        } catch (RedisException $e) {
            throw new \RuntimeException("Redis connection failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if Redis connection is alive
     */
    private function isConnectionAlive(Redis $connection): bool
    {
        try {
            return $connection->ping() === '+PONG';
        } catch (RedisException) {
            return false;
        }
    }

    /**
     * Begin a database transaction
     */
    public function beginTransaction(): void
    {
        $this->getConnection()->multi();
    }

    /**
     * Commit the current transaction
     */
    public function commit(): void
    {
        $this->getConnection()->exec();
    }

    /**
     * Rollback the current transaction
     */
    public function rollback(): void
    {
        $this->getConnection()->discard();
    }

    /**
     * Get the driver name
     */
    public function getName(): string
    {
        return 'redis';
    }

    /**
     * Redis supports transactions (via MULTI/EXEC)
     */
    public function supportsTransactions(): bool
    {
        return true;
    }

    /**
     * Redis supports connection pooling
     */
    public function supportsPooling(): bool
    {
        return true;
    }

    /**
     * Get enhanced stats including pool information
     */
    public function getStats(): array
    {
        $stats = parent::getStats();
        
        $stats['pool'] = [
            'current_connections' => $this->currentConnections,
            'pooled_connections' => count($this->connectionPool),
            'max_connections' => $this->getConfig('pool.max_connections', 10),
            'min_connections' => $this->getConfig('pool.min_connections', 2),
        ];
        
        // Get Redis info if connection is available
        try {
            if ($this->connection !== null) {
                $info = $this->connection->info();
                $stats['redis_info'] = [
                    'redis_version' => $info['redis_version'] ?? 'unknown',
                    'used_memory_human' => $info['used_memory_human'] ?? 'unknown',
                    'connected_clients' => $info['connected_clients'] ?? 'unknown',
                    'total_commands_processed' => $info['total_commands_processed'] ?? 'unknown',
                ];
            }
        } catch (RedisException) {
            // Ignore errors when getting stats
        }
        
        return $stats;
    }

    /**
     * Disconnect and cleanup resources
     */
    public function disconnect(): void
    {
        // Close all pooled connections
        foreach ($this->connectionPool as $connection) {
            try {
                $connection->close();
            } catch (RedisException) {
                // Ignore close errors
            }
        }
        $this->connectionPool = [];
        $this->currentConnections = 0;
        
        // Close singleton connection
        if ($this->connection !== null) {
            try {
                $this->connection->close();
            } catch (RedisException) {
                // Ignore close errors
            }
            $this->connection = null;
        }
    }

    /**
     * Record query execution for statistics
     */
    public function recordQueryExecution(): void
    {
        $this->recordQuery();
    }

    /**
     * Execute Redis command directly
     */
    public function executeCommand(string $command, array $args = []): mixed
    {
        $connection = $this->getConnection();
        
        try {
            $this->recordQueryExecution();
            return $connection->rawCommand($command, ...$args);
        } catch (RedisException $e) {
            throw new \RuntimeException("Redis command failed: " . $e->getMessage(), 0, $e);
        } finally {
            if ($this->supportsPooling()) {
                $this->returnConnection($connection);
            }
        }
    }
}