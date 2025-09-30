<?php

declare(strict_types=1);

namespace Elementary\Database;

use PDO;

/**
 * Wrapper for pooled PDO connections that automatically returns to pool
 * 
 * This class acts as a proxy to the underlying PDO connection while
 * ensuring that the connection is automatically returned to the pool
 * when it's no longer needed.
 */
final class PooledConnection
{
    private PDO $pdo;
    private ConnectionPool $pool;
    private bool $returned = false;

    public function __construct(PDO $pdo, ConnectionPool $pool)
    {
        $this->pdo = $pdo;
        $this->pool = $pool;
    }

    /**
     * Get the underlying PDO connection
     * 
     * Use this when you need direct access to PDO methods not proxied here
     */
    public function getPdo(): PDO
    {
        if ($this->returned) {
            throw new \RuntimeException('Cannot use connection that has been returned to pool');
        }
        return $this->pdo;
    }

    /**
     * Manually return connection to pool
     * 
     * Normally you don't need to call this as it happens automatically
     * in the destructor, but you can call it explicitly for immediate cleanup
     */
    public function release(): void
    {
        if (!$this->returned) {
            $this->pool->returnConnection($this->pdo);
            $this->returned = true;
        }
    }

    /**
     * Auto-return connection to pool when object is destroyed
     * 
     * This ensures connections are always returned even if release() 
     * is not called explicitly
     */
    public function __destruct()
    {
        $this->release();
    }

    /**
     * Check if connection has been returned to pool
     */
    public function isReturned(): bool
    {
        return $this->returned;
    }

    // ===========================================
    // PDO Method Proxies for Convenience
    // ===========================================

    /**
     * Prepare a statement for execution
     */
    public function prepare($statement, $options = [])
    {
        if ($this->returned) {
            throw new \RuntimeException('Cannot use connection that has been returned to pool');
        }
        return $this->pdo->prepare($statement, $options);
    }

    /**
     * Execute a query and return a result set
     */
    public function query($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, ...$args)
    {
        if ($this->returned) {
            throw new \RuntimeException('Cannot use connection that has been returned to pool');
        }
        return $this->pdo->query($statement, $mode, ...$args);
    }

    /**
     * Execute an SQL statement and return the number of affected rows
     */
    public function exec($statement)
    {
        if ($this->returned) {
            throw new \RuntimeException('Cannot use connection that has been returned to pool');
        }
        return $this->pdo->exec($statement);
    }

    /**
     * Return the ID of the last inserted row
     */
    public function lastInsertId($name = null)
    {
        if ($this->returned) {
            throw new \RuntimeException('Cannot use connection that has been returned to pool');
        }
        return $this->pdo->lastInsertId($name);
    }

    /**
     * Start a transaction
     */
    public function beginTransaction()
    {
        if ($this->returned) {
            throw new \RuntimeException('Cannot use connection that has been returned to pool');
        }
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit()
    {
        if ($this->returned) {
            throw new \RuntimeException('Cannot use connection that has been returned to pool');
        }
        return $this->pdo->commit();
    }

    /**
     * Roll back a transaction
     */
    public function rollback()
    {
        if ($this->returned) {
            throw new \RuntimeException('Cannot use connection that has been returned to pool');
        }
        return $this->pdo->rollback();
    }

    /**
     * Check if inside a transaction
     */
    public function inTransaction()
    {
        if ($this->returned) {
            throw new \RuntimeException('Cannot use connection that has been returned to pool');
        }
        return $this->pdo->inTransaction();
    }

    /**
     * Get PDO attribute
     */
    public function getAttribute($attribute)
    {
        if ($this->returned) {
            throw new \RuntimeException('Cannot use connection that has been returned to pool');
        }
        return $this->pdo->getAttribute($attribute);
    }

    /**
     * Set PDO attribute
     */
    public function setAttribute($attribute, $value)
    {
        if ($this->returned) {
            throw new \RuntimeException('Cannot use connection that has been returned to pool');
        }
        return $this->pdo->setAttribute($attribute, $value);
    }

    /**
     * Quote a string for use in a query
     */
    public function quote($string, $parameter_type = PDO::PARAM_STR)
    {
        if ($this->returned) {
            throw new \RuntimeException('Cannot use connection that has been returned to pool');
        }
        return $this->pdo->quote($string, $parameter_type);
    }

    /**
     * Get error code
     */
    public function errorCode()
    {
        if ($this->returned) {
            throw new \RuntimeException('Cannot use connection that has been returned to pool');
        }
        return $this->pdo->errorCode();
    }

    /**
     * Get error info
     */
    public function errorInfo()
    {
        if ($this->returned) {
            throw new \RuntimeException('Cannot use connection that has been returned to pool');
        }
        return $this->pdo->errorInfo();
    }

    /**
     * Get available drivers
     */
    public static function getAvailableDrivers()
    {
        return PDO::getAvailableDrivers();
    }
}