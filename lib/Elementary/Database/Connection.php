<?php

declare(strict_types=1);

namespace Elementary\Database;

use Elementary\Config\ConfigBag;
use PDO;
use PDOException;

/**
 * Connection manager with built-in connection pooling
 * 
 * Provides high-performance pooled connections by default
 * with automatic connection management and resource optimization
 */
final class Connection
{
    private ConfigBag $config;
    private static ?ConnectionPool $pool = null;
    private static ?PDO $singletonInstance = null;

    public function __construct(ConfigBag $config)
    {
        $this->config = $config;
    }

    /**
     * Gets a database connection (uses pooling by default)
     * 
     * @return PDO The PDO instance
     */
    public function getInstance(): PDO
    {
        $usePool = $this->config->get('database.pool.enabled', true);
        
        if ($usePool) {
            // Return the underlying PDO from a pooled connection
            return $this->getPooledConnection()->getPdo();
        }
        
        // Fallback to singleton connection if pooling is disabled
        return $this->getSingletonConnection();
    }

    /**
     * Get pooled connection with automatic resource management
     * 
     * Returns a PooledConnection that automatically returns to pool
     * when it goes out of scope or is manually released.
     */
    public function getPooledConnection(): PooledConnection
    {
        if (self::$pool === null) {
            self::$pool = ConnectionPool::getInstance($this->config);
        }
        
        return self::$pool->getConnection();
    }

    /**
     * Get connection pool statistics (for monitoring)
     */
    public function getPoolStats(): array
    {
        if (self::$pool === null) {
            return [
                'pool_enabled' => false,
                'total_connections' => 1,
                'available_connections' => 1,
                'used_connections' => 0,
                'max_connections' => 1,
                'min_connections' => 1,
            ];
        }
        
        $stats = self::$pool->getStats();
        $stats['pool_enabled'] = true;
        
        return $stats;
    }

    /**
     * Original singleton connection (for backward compatibility)
     * 
     * This maintains the exact behavior of the original Connection class
     */
    private function getSingletonConnection(): PDO
    {
        if (self::$singletonInstance === null) {
            $host = $this->config->get('database.host');
            $db = $this->config->get('database.database');
            $user = $this->config->get('database.username');
            $pass = $this->config->get('database.password');
            $charset = $this->config->get('database.charset', 'utf8mb4');
            $port = $this->config->get('database.port', 3306);

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                self::$singletonInstance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }

        return self::$singletonInstance;
    }

    /**
     * Execute a statement using pooled connection
     * 
     * This is a convenience method that properly manages pooled connections
     */
    public function execute(string $sql, array $bindings = []): \PDOStatement
    {
        $pooledConn = $this->getPooledConnection();
        
        try {
            $stmt = $pooledConn->prepare($sql);
            $stmt->execute($bindings);
            return $stmt;
        } finally {
            $pooledConn->release();
        }
    }

    /**
     * Run a transaction using pooled connection
     */
    public function transaction(callable $callback)
    {
        $pooledConn = $this->getPooledConnection();
        
        try {
            $pooledConn->beginTransaction();
            
            $result = $callback($pooledConn);
            
            $pooledConn->commit();
            
            return $result;
        } catch (\Throwable $e) {
            $pooledConn->rollback();
            throw $e;
        } finally {
            $pooledConn->release();
        }
    }

    /**
     * Close the connection pool (for graceful shutdown)
     */
    public function closePool(): void
    {
        if (self::$pool !== null) {
            self::$pool->closeAllConnections();
        }
    }

    /**
     * Reset connections (for testing)
     */
    public static function reset(): void
    {
        self::$pool = null;
        self::$singletonInstance = null;
    }

    /**
     * Check if pooling is enabled
     */
    public function isPoolingEnabled(): bool
    {
        return $this->config->get('database.pool.enabled', false);
    }
}
