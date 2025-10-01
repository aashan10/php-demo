<?php

declare(strict_types=1);

namespace Elementary\Database\Drivers;

use Elementary\Database\Contracts\QueryBuilderInterface;
use Elementary\Database\Contracts\SchemaBuilderInterface;
use Elementary\Database\QueryBuilders\SQLiteQueryBuilder;
use Elementary\Database\Schema\SQLiteSchemaBuilder;
use PDO;
use PDOException;

/**
 * SQLite Database Driver
 * 
 * Implements SQLite-specific database operations including connection
 * management, query building, and schema operations. Perfect for testing
 * environments and development with support for in-memory databases.
 */
class SQLiteDriver extends AbstractDriver
{
    private ?PDO $connection = null;

    /**
     * Get a query builder instance for SQLite
     */
    public function getQueryBuilder(): QueryBuilderInterface
    {
        return new SQLiteQueryBuilder($this);
    }

    /**
     * Get a schema builder instance for SQLite
     */
    public function getSchemaBuilder(): SchemaBuilderInterface
    {
        return new SQLiteSchemaBuilder($this);
    }

    /**
     * Get the raw database connection
     */
    public function getConnection(): mixed
    {
        if ($this->connection === null) {
            $this->connection = $this->createPDOConnection();
            $this->recordConnection();
        }
        
        return $this->connection;
    }

    /**
     * Create a new PDO connection for SQLite
     */
    public function createPDOConnection(): PDO
    {
        $database = $this->getConfig('database', ':memory:');
        
        // Build DSN - support both file and in-memory databases
        if ($database === ':memory:') {
            $dsn = 'sqlite::memory:';
        } else {
            // Ensure directory exists if using file database
            $directory = dirname($database);
            if (!is_dir($directory) && $directory !== '.') {
                mkdir($directory, 0755, true);
            }
            $dsn = 'sqlite:' . $database;
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => $this->getConfig('timeout', 30),
        ];

        // Add custom options from config
        $customOptions = $this->getConfig('options', []);
        foreach ($customOptions as $key => $value) {
            $options[$key] = $value;
        }

        try {
            $pdo = new PDO($dsn, null, null, $options);
            
            // Enable foreign key constraints (disabled by default in SQLite)
            if ($this->getConfig('foreign_keys', true)) {
                $pdo->exec('PRAGMA foreign_keys = ON');
            }
            
            // Set journal mode for better performance
            $journalMode = $this->getConfig('journal_mode', 'WAL');
            $pdo->exec("PRAGMA journal_mode = {$journalMode}");
            
            // Set synchronous mode
            $synchronous = $this->getConfig('synchronous', 'NORMAL');
            $pdo->exec("PRAGMA synchronous = {$synchronous}");
            
            return $pdo;
        } catch (PDOException $e) {
            throw new \RuntimeException("Could not connect to SQLite database: " . $e->getMessage(), 0, $e);
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
        return 'sqlite';
    }

    /**
     * SQLite supports transactions
     */
    public function supportsTransactions(): bool
    {
        return true;
    }

    /**
     * SQLite doesn't support connection pooling (single-file database)
     */
    public function supportsPooling(): bool
    {
        return false;
    }

    /**
     * Disconnect and cleanup resources
     */
    public function disconnect(): void
    {
        $this->connection = null;
    }

    /**
     * Record query execution for statistics
     */
    public function recordQueryExecution(): void
    {
        $this->recordQuery();
    }

    /**
     * Get SQLite-specific database information
     */
    public function getDatabaseInfo(): array
    {
        $connection = $this->getConnection();
        
        $info = [
            'sqlite_version' => $connection->query('SELECT sqlite_version()')->fetchColumn(),
            'database_file' => $this->getConfig('database', ':memory:'),
        ];
        
        // Get database size if it's a file database
        $database = $this->getConfig('database', ':memory:');
        if ($database !== ':memory:' && file_exists($database)) {
            $info['file_size'] = filesize($database);
        }
        
        return $info;
    }

    /**
     * Vacuum the database (SQLite-specific optimization)
     */
    public function vacuum(): void
    {
        $this->getConnection()->exec('VACUUM');
    }

    /**
     * Analyze the database for query optimization
     */
    public function analyze(): void
    {
        $this->getConnection()->exec('ANALYZE');
    }
}