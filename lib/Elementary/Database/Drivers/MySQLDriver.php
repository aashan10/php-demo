<?php

declare(strict_types=1);

namespace Elementary\Database\Drivers;

use Elementary\Database\Contracts\QueryBuilderInterface;
use Elementary\Database\Contracts\SchemaBuilderInterface;
use Elementary\Database\QueryBuilders\MySQLQueryBuilder;
use Elementary\Database\Schema\MySQLSchemaBuilder;
use Elementary\Database\ConnectionPool;
use Elementary\Database\PooledConnection;
use PDO;
use PDOException;

/**
 * MySQL Database Driver
 * 
 * Implements MySQL-specific database operations including connection
 * management, query building, and schema operations with support
 * for connection pooling.
 */
class MySQLDriver extends AbstractDriver
{
    private ?ConnectionPool $pool = null;
    private ?PDO $singletonConnection = null;

    /**
     * Get a query builder instance for MySQL
     */
    public function getQueryBuilder(): QueryBuilderInterface
    {
        return new MySQLQueryBuilder($this);
    }

    /**
     * Get a schema builder instance for MySQL
     */
    public function getSchemaBuilder(): SchemaBuilderInterface
    {
        return new MySQLSchemaBuilder($this);
    }

    /**
     * Get the raw database connection
     */
    public function getConnection(): mixed
    {
        if ($this->supportsPooling()) {
            return $this->getPooledConnection()->getPdo();
        }
        
        return $this->getSingletonConnection();
    }

    /**
     * Get a pooled connection
     */
    public function getPooledConnection(): PooledConnection
    {
        if ($this->pool === null) {
            $this->pool = ConnectionPool::getInstance();
        }
        
        return $this->pool->getConnection();
    }

    /**
     * Get singleton connection (fallback)
     */
    private function getSingletonConnection(): PDO
    {
        if ($this->singletonConnection === null) {
            $this->singletonConnection = $this->createPDOConnection();
            $this->recordConnection();
        }
        
        return $this->singletonConnection;
    }

    /**
     * Create a new PDO connection
     */
    public function createPDOConnection(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->getConfig('host', 'localhost'),
            $this->getConfig('port', 3306),
            $this->getConfig('database'),
            $this->getConfig('charset', 'utf8mb4')
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // Add custom options from config
        $customOptions = $this->getConfig('options', []);
        foreach ($customOptions as $key => $value) {
            $options[$key] = $value;
        }

        try {
            return new PDO(
                $dsn,
                $this->getConfig('username'),
                $this->getConfig('password'),
                $options
            );
        } catch (PDOException $e) {
            throw new \RuntimeException("Could not connect to MySQL database: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Begin a database transaction
     */
    public function beginTransaction(): void
    {
        $this->getConnection()->beginTransaction();
    }

    /**
     * Commit the current transaction
     */
    public function commit(): void
    {
        $this->getConnection()->commit();
    }

    /**
     * Rollback the current transaction
     */
    public function rollback(): void
    {
        $this->getConnection()->rollback();
    }

    /**
     * Get the driver name
     */
    public function getName(): string
    {
        return 'mysql';
    }

    /**
     * MySQL supports transactions
     */
    public function supportsTransactions(): bool
    {
        return true;
    }

    /**
     * MySQL supports connection pooling
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
        
        if ($this->pool !== null) {
            $stats['pool'] = $this->pool->getStats();
        }
        
        return $stats;
    }

    /**
     * Disconnect and cleanup resources
     */
    public function disconnect(): void
    {
        $this->singletonConnection = null;
        
        if ($this->pool !== null) {
            // Connection pool will handle cleanup when instances are destroyed
            $this->pool = null;
        }
    }

    /**
     * Record query execution for statistics
     */
    public function recordQueryExecution(): void
    {
        $this->recordQuery();
    }
}