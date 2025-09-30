<?php

declare(strict_types=1);

namespace Elementary\Database\Migration;

use Elementary\Database\DatabaseManager;
use Elementary\Database\Contracts\QueryBuilderInterface;

/**
 * Migration Runner Service
 * 
 * Handles running, tracking, and managing database migrations.
 */
class MigrationRunner
{
    private DatabaseManager $db;
    private string $migrationsPath;
    private string $migrationsTable = 'migrations';
    
    public function __construct(string $migrationsPath = null)
    {
        $this->db = DatabaseManager::getInstance();
        $this->migrationsPath = $migrationsPath ?: BASE_PATH . '/database/migrations';
    }
    
    /**
     * Run all pending migrations
     */
    public function migrate(): array
    {
        $this->ensureMigrationsTableExists();
        
        $pendingMigrations = $this->getPendingMigrations();
        $executed = [];
        
        foreach ($pendingMigrations as $migration) {
            $this->runMigration($migration);
            $executed[] = $migration->getName();
        }
        
        return $executed;
    }
    
    /**
     * Rollback the last batch of migrations
     */
    public function rollback(): array
    {
        $this->ensureMigrationsTableExists();
        
        $lastBatch = $this->getLastBatchNumber();
        if ($lastBatch === 0) {
            return [];
        }
        
        $migrations = $this->getMigrationsByBatch($lastBatch);
        $rolledBack = [];
        
        // Rollback in reverse order
        foreach (array_reverse($migrations) as $migrationRecord) {
            $migration = $this->loadMigration($migrationRecord['migration']);
            $migration->down();
            
            $this->removeMigrationRecord($migrationRecord['migration']);
            $rolledBack[] = $migrationRecord['migration'];
        }
        
        return $rolledBack;
    }
    
    /**
     * Get migration status
     */
    public function status(): array
    {
        $this->ensureMigrationsTableExists();
        
        $allMigrations = $this->getAllMigrationFiles();
        $runMigrations = $this->getRunMigrations();
        
        $status = [];
        
        foreach ($allMigrations as $migrationFile) {
            $migrationName = $this->getMigrationNameFromFile($migrationFile);
            $status[] = [
                'migration' => $migrationName,
                'status' => in_array($migrationName, $runMigrations) ? 'Ran' : 'Pending'
            ];
        }
        
        return $status;
    }
    
    /**
     * Create a new migration file
     */
    public function makeMigration(string $name): string
    {
        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }
        
        $timestamp = date('Y_m_d_His');
        $className = 'Migration_' . $timestamp . '_' . $this->studlyCase($name);
        $filename = $timestamp . '_' . $this->snakeCase($name) . '.php';
        $filepath = $this->migrationsPath . '/' . $filename;
        
        $stub = $this->getMigrationStub($className, $name);
        file_put_contents($filepath, $stub);
        
        return $filepath;
    }
    
    /**
     * Get all pending migrations
     */
    private function getPendingMigrations(): array
    {
        $allMigrations = $this->getAllMigrationFiles();
        $runMigrations = $this->getRunMigrations();
        
        $pending = [];
        
        foreach ($allMigrations as $migrationFile) {
            $migrationName = $this->getMigrationNameFromFile($migrationFile);
            
            if (!in_array($migrationName, $runMigrations)) {
                $pending[] = $this->loadMigration($migrationName);
            }
        }
        
        return $pending;
    }
    
    /**
     * Run a single migration
     */
    private function runMigration(Migration $migration): void
    {
        $migration->up();
        
        $batchNumber = $this->getNextBatchNumber();
        
        $this->db->newQuery()
            ->table($this->migrationsTable)
            ->insert([
                'migration' => $migration->getName(),
                'batch' => $batchNumber
            ]);
    }
    
    /**
     * Load migration instance from class name
     */
    private function loadMigration(string $migrationName): Migration
    {
        // Find the file containing this migration
        $files = $this->getAllMigrationFiles();
        
        foreach ($files as $file) {
            if ($this->getMigrationNameFromFile($file) === $migrationName) {
                require_once $file;
                return new $migrationName();
            }
        }
        
        throw new \RuntimeException("Migration class {$migrationName} not found");
    }
    
    /**
     * Ensure migrations table exists
     */
    private function ensureMigrationsTableExists(): void
    {
        $schema = $this->db->getSchemaBuilder();
        
        if (!$schema->hasTable($this->migrationsTable)) {
            $schema->create($this->migrationsTable, function($table) {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
            });
        } else {
            // Check if the table has the correct columns
            if (!$schema->hasColumn($this->migrationsTable, 'migration')) {
                // Recreate the table with the correct structure
                $schema->dropIfExists($this->migrationsTable);
                $schema->create($this->migrationsTable, function($table) {
                    $table->id();
                    $table->string('migration');
                    $table->integer('batch');
                });
            }
        }
    }
    
    /**
     * Get all migration files
     */
    private function getAllMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }
        
        $files = glob($this->migrationsPath . '/*.php');
        sort($files);
        
        return $files;
    }
    
    /**
     * Get migration class name from file
     */
    private function getMigrationNameFromFile(string $file): string
    {
        $content = file_get_contents($file);
        
        if (preg_match('/class\s+([A-Za-z_][A-Za-z0-9_]*)\s+extends\s+Migration/', $content, $matches)) {
            return $matches[1];
        }
        
        throw new \RuntimeException("Could not find migration class in file: {$file}");
    }
    
    /**
     * Get already run migrations
     */
    private function getRunMigrations(): array
    {
        try {
            $results = $this->db->newQuery()
                ->table($this->migrationsTable)
                ->select(['migration'])
                ->get();
                
            return array_column($results, 'migration');
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Get migrations by batch number
     */
    private function getMigrationsByBatch(int $batch): array
    {
        return $this->db->newQuery()
            ->table($this->migrationsTable)
            ->where('batch', '=', $batch)
            ->get();
    }
    
    /**
     * Get the last batch number
     */
    private function getLastBatchNumber(): int
    {
        $result = $this->db->newQuery()
            ->table($this->migrationsTable)
            ->select(['MAX(batch) as max_batch'])
            ->first();
            
        return (int) ($result['max_batch'] ?? 0);
    }
    
    /**
     * Get the next batch number
     */
    private function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }
    
    /**
     * Remove migration record
     */
    private function removeMigrationRecord(string $migration): void
    {
        $this->db->newQuery()
            ->table($this->migrationsTable)
            ->where('migration', '=', $migration)
            ->delete();
    }
    
    /**
     * Convert string to StudlyCase
     */
    private function studlyCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $string)));
    }
    
    /**
     * Convert string to snake_case
     */
    private function snakeCase(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', str_replace([' ', '-'], '_', $string)));
    }
    
    /**
     * Get migration stub template
     */
    private function getMigrationStub(string $className, string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

use Elementary\Database\Migration\Migration;

/**
 * Migration: {$name}
 */
class {$className} extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        // TODO: Implement your migration logic here
        // Example:
        // \$this->schema->create('table_name', function(\$table) {
        //     \$table->id();
        //     \$table->string('name');
        //     \$table->timestamps();
        // });
    }
    
    /**
     * Reverse the migration
     */
    public function down(): void
    {
        // TODO: Implement rollback logic here
        // Example:
        // \$this->schema->dropIfExists('table_name');
    }
}

PHP;
    }
}