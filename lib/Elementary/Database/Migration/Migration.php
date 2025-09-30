<?php

declare(strict_types=1);

namespace Elementary\Database\Migration;

use Elementary\Database\Contracts\SchemaBuilderInterface;
use Elementary\Database\DatabaseManager;

/**
 * Base Migration class
 * 
 * Provides a clean interface for defining database schema changes.
 * Each migration should extend this class and implement up() and down() methods.
 */
abstract class Migration
{
    protected SchemaBuilderInterface $schema;
    
    public function __construct()
    {
        $this->schema = DatabaseManager::getInstance()->getSchemaBuilder();
    }
    
    /**
     * Run the migration (create tables, add columns, etc.)
     */
    abstract public function up(): void;
    
    /**
     * Reverse the migration (drop tables, remove columns, etc.)
     */
    abstract public function down(): void;
    
    /**
     * Get the migration name from class name
     */
    public function getName(): string
    {
        return static::class;
    }
    
    /**
     * Get migration timestamp from filename convention
     */
    public function getTimestamp(): string
    {
        $className = class_basename(static::class);
        
        // Extract timestamp from class name (e.g., "Migration_2024_01_15_123456_CreateUsersTable" -> "2024_01_15_123456")
        if (preg_match('/^Migration_(\d{4}_\d{2}_\d{2}_\d{6})_/', $className, $matches)) {
            return $matches[1];
        }
        
        // Fallback: use current timestamp
        return date('Y_m_d_His');
    }
}