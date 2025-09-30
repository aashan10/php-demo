<?php

declare(strict_types=1);

namespace Elementary\Database;

use Elementary\Config\ConfigBag;
use Elementary\Database\Contracts\DatabaseDriverInterface;
use Elementary\Database\Contracts\QueryBuilderInterface;
use Elementary\Database\Drivers\MySQLDriver;

/**
 * Multi-Driver Database Manager
 * 
 * Central coordinator for multiple database drivers and connections.
 * Supports per-model driver selection while maintaining clean architecture
 * and eliminating container dependencies from models.
 */
final class DatabaseManager
{
    private static ?DatabaseManager $instance = null;
    private ConfigBag $config;
    private array $drivers = [];
    private array $connections = [];
    private string $defaultConnection;

    private function __construct(ConfigBag $config)
    {
        $this->config = $config;
        $this->defaultConnection = $config->get('database.default', 'mysql');
        $this->registerDefaultDrivers();
    }

    /**
     * Initialize the global database manager
     * 
     * This should be called once during application bootstrap
     */
    public static function initialize(ConfigBag $config): void
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
            if ($config->get('database.pool.enabled', false)) {
                ConnectionPool::initialize($config);
            }
        }
    }

    /**
     * Get the singleton instance
     * 
     * Throws an exception if not initialized - fail fast for configuration errors
     */
    public static function getInstance(): DatabaseManager
    {
        if (self::$instance === null) {
            throw new \RuntimeException(
                'DatabaseManager not initialized. Call DatabaseManager::initialize($config) first in bootstrap.php'
            );
        }
        return self::$instance;
    }

    /**
     * Get a database connection by name
     * 
     * @param string|null $name Connection name (uses default if null)
     * @return DatabaseDriverInterface The database driver instance
     */
    public function connection(string $name = null): DatabaseDriverInterface
    {
        $name = $name ?: $this->defaultConnection;
        
        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }
        
        return $this->connections[$name];
    }

    /**
     * Create a new query builder for the default connection
     * 
     * This maintains backward compatibility with existing code
     */
    public function newQuery(): QueryBuilderInterface
    {
        return $this->connection()->getQueryBuilder();
    }

    /**
     * Get connection for direct access (backward compatibility)
     * 
     * Returns the legacy Connection object for the default MySQL connection
     */
    public function getConnection(): Connection
    {
        // Create legacy Connection object for backward compatibility
        return new Connection($this->config);
    }

    /**
     * Register default database drivers
     */
    private function registerDefaultDrivers(): void
    {
        $this->drivers['mysql'] = MySQLDriver::class;
        $this->drivers['redis'] = \Elementary\Database\Drivers\RedisDriver::class;
        // Additional drivers will be registered here as they're implemented
        // $this->drivers['mongodb'] = MongoDBDriver::class;
    }

    /**
     * Register a custom database driver
     */
    public function registerDriver(string $name, string $driverClass): void
    {
        $this->drivers[$name] = $driverClass;
    }

    /**
     * Create a new database connection
     */
    private function makeConnection(string $name): DatabaseDriverInterface
    {
        $config = $this->getConnectionConfig($name);
        $driver = $this->createDriver($config['driver']);
        
        return new $driver($config);
    }

    /**
     * Get configuration for a specific connection
     */
    private function getConnectionConfig(string $name): array
    {
        $connections = $this->config->get('database.connections', []);
        
        if (!isset($connections[$name])) {
            // If no specific connections are configured, use the legacy format
            if ($name === 'mysql' || $name === $this->defaultConnection) {
                return [
                    'driver' => 'mysql',
                    'host' => $this->config->get('database.host'),
                    'port' => $this->config->get('database.port', 3306),
                    'database' => $this->config->get('database.database'),
                    'username' => $this->config->get('database.username'),
                    'password' => $this->config->get('database.password'),
                    'charset' => $this->config->get('database.charset', 'utf8mb4'),
                    'collation' => $this->config->get('database.collation', 'utf8mb4_unicode_ci'),
                    'pool' => $this->config->get('database.pool', []),
                    'options' => $this->config->get('database.options', []),
                ];
            }
            
            throw new \InvalidArgumentException("Database connection [{$name}] not configured.");
        }
        
        return $connections[$name];
    }

    /**
     * Create a driver instance
     */
    private function createDriver(string $driver): string
    {
        if (!isset($this->drivers[$driver])) {
            throw new \InvalidArgumentException("Database driver [{$driver}] not supported.");
        }
        
        return $this->drivers[$driver];
    }

    /**
     * Get connection statistics for monitoring
     */
    public function getConnectionStats(string $connectionName = null): array
    {
        $connection = $this->connection($connectionName);
        return $connection->getStats();
    }

    /**
     * Execute a statement directly on a specific connection
     */
    public function statement(string $sql, array $bindings = [], string $connectionName = null): mixed
    {
        $connection = $this->connection($connectionName);
        
        // For now, this assumes SQL-based drivers
        // Non-SQL drivers would need different handling
        if ($connection instanceof MySQLDriver) {
            $pooledConn = $connection->getPooledConnection();
            
            try {
                $stmt = $pooledConn->prepare($sql);
                $stmt->execute($bindings);
                return $stmt;
            } finally {
                $pooledConn->release();
            }
        }
        
        throw new \RuntimeException("Statement execution not supported for this driver type.");
    }

    /**
     * Execute a transaction on a specific connection
     */
    public function transaction(callable $callback, string $connectionName = null): mixed
    {
        $connection = $this->connection($connectionName);
        return $connection->transaction($callback);
    }

    /**
     * Get configuration for debugging
     */
    public function getConfig(): ConfigBag
    {
        return $this->config;
    }

    /**
     * Get all available connection names
     */
    public function getConnectionNames(): array
    {
        return array_keys($this->config->get('database.connections', []));
    }

    /**
     * Get the default connection name
     */
    public function getDefaultConnection(): string
    {
        return $this->defaultConnection;
    }

    /**
     * Set the default connection
     */
    public function setDefaultConnection(string $name): void
    {
        $this->defaultConnection = $name;
    }

    /**
     * Get schema builder for the default connection
     */
    public function getSchemaBuilder(string $connectionName = null): \Elementary\Database\Contracts\SchemaBuilderInterface
    {
        $connection = $this->connection($connectionName);
        
        // Return appropriate schema builder based on driver type
        if ($connection instanceof \Elementary\Database\Drivers\MySQLDriver) {
            return new \Elementary\Database\Schema\MySQLSchemaBuilder($connection);
        }
        
        if ($connection instanceof \Elementary\Database\Drivers\RedisDriver) {
            return new \Elementary\Database\Schema\RedisSchemaBuilder($connection);
        }
        
        throw new \RuntimeException("Schema builder not available for this driver type.");
    }

    /**
     * Reset the singleton instance (for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}