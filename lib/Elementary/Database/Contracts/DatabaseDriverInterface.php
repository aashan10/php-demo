<?php

declare(strict_types=1);

namespace Elementary\Database\Contracts;

/**
 * Database Driver Interface
 * 
 * Defines the contract that all database drivers must implement.
 * Each driver handles connection management, query building, and 
 * database-specific operations for its respective database system.
 */
interface DatabaseDriverInterface
{
    /**
     * Initialize the driver with configuration
     */
    public function __construct(array $config);

    /**
     * Get a query builder instance for this driver
     */
    public function getQueryBuilder(): QueryBuilderInterface;

    /**
     * Get a schema builder instance for this driver
     */
    public function getSchemaBuilder(): SchemaBuilderInterface;

    /**
     * Get the raw database connection
     */
    public function getConnection(): mixed;

    /**
     * Check if the driver supports transactions
     */
    public function supportsTransactions(): bool;

    /**
     * Begin a database transaction
     */
    public function beginTransaction(): void;

    /**
     * Commit the current transaction
     */
    public function commit(): void;

    /**
     * Rollback the current transaction
     */
    public function rollback(): void;

    /**
     * Execute a transaction with automatic rollback on failure
     */
    public function transaction(callable $callback): mixed;

    /**
     * Get driver statistics (connections, queries, etc.)
     */
    public function getStats(): array;

    /**
     * Get the driver name
     */
    public function getName(): string;

    /**
     * Check if the driver supports connection pooling
     */
    public function supportsPooling(): bool;

    /**
     * Disconnect and cleanup resources
     */
    public function disconnect(): void;
}