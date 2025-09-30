<?php

declare(strict_types=1);

namespace Elementary\Database;

use Elementary\Config\ConfigBag;
use PDO;
use PDOException;
use SplQueue;

/**
 * Database connection pool for high-performance applications
 * 
 * Manages a pool of PDO connections to avoid the overhead of creating
 * new connections for each database operation under high load.
 */
final class ConnectionPool
{
    private SplQueue $availableConnections;
    private SplQueue $usedConnections;
    private ConfigBag $config;
    private int $maxConnections;
    private int $minConnections;
    private int $currentConnections = 0;
    private static ?ConnectionPool $instance = null;
    private int $connectionTimeout;
    private int $idleTimeout;

    private function __construct(ConfigBag $config)
    {
        $this->config = $config;
        $this->maxConnections = $config->get('database.pool.max_connections', 20);
        $this->minConnections = $config->get('database.pool.min_connections', 5);
        $this->connectionTimeout = $config->get('database.pool.connection_timeout', 30);
        $this->idleTimeout = $config->get('database.pool.idle_timeout', 300);
        $this->availableConnections = new SplQueue();
        $this->usedConnections = new SplQueue();
        
        // Initialize minimum connections
        $this->initializePool();
    }

    public static function initialize(ConfigBag $config): void
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
    }

    public static function getInstance(): ConnectionPool
    {
        if (self::$instance === null) {
            throw new \RuntimeException('ConnectionPool has not been initialized. Call initialize() first.');
        }
        return self::$instance;
    }

    /**
     * Get connection from pool
     * 
     * This method implements a simple connection pool algorithm:
     * 1. Try to reuse an available connection
     * 2. Create a new connection if under the limit
     * 3. Wait and retry if at maximum capacity
     */
    public function getConnection(): PooledConnection
    {
        // Try to get available connection
        if (!$this->availableConnections->isEmpty()) {
            $pdo = $this->availableConnections->dequeue();
            $this->usedConnections->enqueue($pdo);
            return new PooledConnection($pdo, $this);
        }

        // Create new connection if under limit
        if ($this->currentConnections < $this->maxConnections) {
            $pdo = $this->createConnection();
            $this->usedConnections->enqueue($pdo);
            $this->currentConnections++;
            return new PooledConnection($pdo, $this);
        }

        // Pool is at capacity - wait for available connection
        // In production, you might want to implement a proper queue with timeouts
        $retries = 0;
        $maxRetries = $this->connectionTimeout * 100; // 10ms sleeps
        
        while ($retries < $maxRetries) {
            usleep(10000); // Wait 10ms
            
            if (!$this->availableConnections->isEmpty()) {
                $pdo = $this->availableConnections->dequeue();
                $this->usedConnections->enqueue($pdo);
                return new PooledConnection($pdo, $this);
            }
            
            $retries++;
        }

        throw new \RuntimeException('Connection pool exhausted. Unable to get connection within timeout period.');
    }

    /**
     * Return connection to pool
     * 
     * This method is called automatically when a PooledConnection
     * is destroyed or manually released.
     */
    public function returnConnection(PDO $pdo): void
    {
        // Remove from used connections
        $used = new SplQueue();
        while (!$this->usedConnections->isEmpty()) {
            $conn = $this->usedConnections->dequeue();
            if ($conn !== $pdo) {
                $used->enqueue($conn);
            }
        }
        $this->usedConnections = $used;

        // Validate connection is still alive
        if ($this->isConnectionAlive($pdo)) {
            // Add back to available connections
            $this->availableConnections->enqueue($pdo);
        } else {
            // Connection is dead, create a new one to maintain pool size
            $this->currentConnections--;
            if ($this->currentConnections < $this->minConnections) {
                try {
                    $newConnection = $this->createConnection();
                    $this->availableConnections->enqueue($newConnection);
                    $this->currentConnections++;
                } catch (PDOException $e) {
                    // Log error but don't throw - pool can operate with fewer connections
                    error_log("Failed to create replacement connection: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Initialize pool with minimum connections
     */
    private function initializePool(): void
    {
        for ($i = 0; $i < $this->minConnections; $i++) {
            try {
                $this->availableConnections->enqueue($this->createConnection());
                $this->currentConnections++;
            } catch (PDOException $e) {
                error_log("Failed to initialize connection pool: " . $e->getMessage());
                break; // Stop trying if we can't connect
            }
        }
    }

    /**
     * Create new PDO connection
     */
    private function createConnection(): PDO
    {
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
            PDO::ATTR_PERSISTENT => false, // Important: no persistent connections in pool
            PDO::ATTR_TIMEOUT => $this->connectionTimeout,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}",
        ];

        try {
            return new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            throw new PDOException("Pool connection failed: " . $e->getMessage(), (int)$e->getCode());
        }
    }

    /**
     * Check if connection is still alive
     */
    private function isConnectionAlive(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get pool statistics for monitoring
     */
    public function getStats(): array
    {
        return [
            'total_connections' => $this->currentConnections,
            'available_connections' => $this->availableConnections->count(),
            'used_connections' => $this->usedConnections->count(),
            'max_connections' => $this->maxConnections,
            'min_connections' => $this->minConnections,
            'connection_timeout' => $this->connectionTimeout,
            'idle_timeout' => $this->idleTimeout,
        ];
    }

    /**
     * Cleanup idle connections (called periodically)
     */
    public function cleanupIdleConnections(): void
    {
        // This would be called by a background process or cron job
        // For now, we keep it simple and don't implement idle timeout
        // In production, you'd want to track connection creation time
        // and close connections that have been idle too long
    }

    /**
     * Close all connections (for graceful shutdown)
     */
    public function closeAllConnections(): void
    {
        // Close available connections
        while (!$this->availableConnections->isEmpty()) {
            $this->availableConnections->dequeue();
        }

        // Note: We can't force-close used connections as they might be in use
        // They will be closed when they're returned to the pool
        
        $this->currentConnections = $this->usedConnections->count();
    }
}