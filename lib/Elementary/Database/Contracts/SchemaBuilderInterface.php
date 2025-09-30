<?php

declare(strict_types=1);

namespace Elementary\Database\Contracts;

/**
 * Schema Builder Interface
 * 
 * Defines the contract for database schema operations.
 * Each driver implements this interface to provide database-specific
 * schema management capabilities.
 */
interface SchemaBuilderInterface
{
    /**
     * Check if a table/collection exists
     */
    public function hasTable(string $table): bool;

    /**
     * Create a new table/collection
     */
    public function createTable(string $table, callable $callback): void;

    /**
     * Drop a table/collection
     */
    public function dropTable(string $table): void;

    /**
     * Rename a table/collection
     */
    public function renameTable(string $from, string $to): void;

    /**
     * Modify an existing table/collection
     */
    public function modifyTable(string $table, callable $callback): void;

    /**
     * Get list of all tables/collections
     */
    public function getTables(): array;

    /**
     * Get columns/fields for a table/collection
     */
    public function getColumns(string $table): array;

    /**
     * Check if a column/field exists
     */
    public function hasColumn(string $table, string $column): bool;

    /**
     * Get indexes for a table/collection
     */
    public function getIndexes(string $table): array;

    /**
     * Check if an index exists
     */
    public function hasIndex(string $table, string $index): bool;
}