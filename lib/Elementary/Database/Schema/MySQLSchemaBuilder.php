<?php

declare(strict_types=1);

namespace Elementary\Database\Schema;

use Elementary\Database\Contracts\SchemaBuilderInterface;
use Elementary\Database\Drivers\MySQLDriver;

/**
 * MySQL Schema Builder
 * 
 * Implements MySQL-specific schema operations for database structure management.
 * Handles table creation, modification, and introspection operations.
 */
class MySQLSchemaBuilder implements SchemaBuilderInterface
{
    protected MySQLDriver $driver;

    public function __construct(MySQLDriver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Check if a table exists
     */
    public function hasTable(string $table): bool
    {
        $sql = "SELECT COUNT(*) as count FROM information_schema.tables 
                WHERE table_schema = DATABASE() AND table_name = ?";
        
        $connection = $this->driver->getConnection();
        $stmt = $connection->prepare($sql);
        $stmt->execute([$table]);
        
        $result = $stmt->fetch();
        return (int) $result['count'] > 0;
    }

    /**
     * Create a new table (basic implementation)
     */
    public function createTable(string $table, callable $callback): void
    {
        // For now, this is a basic implementation
        // In a full implementation, you'd have a table blueprint class
        // that the callback would configure
        
        $sql = "CREATE TABLE `{$table}` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->driver->getConnection()->exec($sql);
        $this->driver->recordQueryExecution();
    }

    /**
     * Drop a table
     */
    public function dropTable(string $table): void
    {
        $sql = "DROP TABLE IF EXISTS `{$table}`";
        $this->driver->getConnection()->exec($sql);
        $this->driver->recordQueryExecution();
    }

    /**
     * Rename a table
     */
    public function renameTable(string $from, string $to): void
    {
        $sql = "RENAME TABLE `{$from}` TO `{$to}`";
        $this->driver->getConnection()->exec($sql);
        $this->driver->recordQueryExecution();
    }

    /**
     * Modify an existing table (basic implementation)
     */
    public function modifyTable(string $table, callable $callback): void
    {
        // Basic implementation - would need table blueprint for full functionality
        // For now, just call the callback which should handle the modifications
        $callback($this);
    }

    /**
     * Get list of all tables
     */
    public function getTables(): array
    {
        $sql = "SELECT table_name FROM information_schema.tables 
                WHERE table_schema = DATABASE()";
        
        $connection = $this->driver->getConnection();
        $stmt = $connection->prepare($sql);
        $stmt->execute();
        
        return array_column($stmt->fetchAll(), 'table_name');
    }

    /**
     * Get columns for a table
     */
    public function getColumns(string $table): array
    {
        $sql = "SELECT column_name, data_type, is_nullable, column_default 
                FROM information_schema.columns 
                WHERE table_schema = DATABASE() AND table_name = ?
                ORDER BY ordinal_position";
        
        $connection = $this->driver->getConnection();
        $stmt = $connection->prepare($sql);
        $stmt->execute([$table]);
        
        return $stmt->fetchAll();
    }

    /**
     * Check if a column exists
     */
    public function hasColumn(string $table, string $column): bool
    {
        $sql = "SELECT COUNT(*) as count FROM information_schema.columns 
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?";
        
        $connection = $this->driver->getConnection();
        $stmt = $connection->prepare($sql);
        $stmt->execute([$table, $column]);
        
        $result = $stmt->fetch();
        return (int) $result['count'] > 0;
    }

    /**
     * Get indexes for a table
     */
    public function getIndexes(string $table): array
    {
        $sql = "SELECT index_name, column_name, non_unique 
                FROM information_schema.statistics 
                WHERE table_schema = DATABASE() AND table_name = ?
                ORDER BY index_name, seq_in_index";
        
        $connection = $this->driver->getConnection();
        $stmt = $connection->prepare($sql);
        $stmt->execute([$table]);
        
        return $stmt->fetchAll();
    }

    /**
     * Check if an index exists
     */
    public function hasIndex(string $table, string $index): bool
    {
        $sql = "SELECT COUNT(*) as count FROM information_schema.statistics 
                WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?";
        
        $connection = $this->driver->getConnection();
        $stmt = $connection->prepare($sql);
        $stmt->execute([$table, $index]);
        
        $result = $stmt->fetch();
        return (int) $result['count'] > 0;
    }
}