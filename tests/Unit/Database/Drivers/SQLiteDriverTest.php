<?php

declare(strict_types=1);

namespace Tests\Unit\Database\Drivers;

use Elementary\Database\Drivers\SQLiteDriver;
use Elementary\Database\Contracts\DatabaseDriverInterface;
use Elementary\Database\Contracts\QueryBuilderInterface;
use Elementary\Database\Contracts\SchemaBuilderInterface;
use Elementary\Database\QueryBuilders\SQLiteQueryBuilder;
use Elementary\Database\Schema\SQLiteSchemaBuilder;
use PHPUnit\Framework\TestCase;
use PDO;

class SQLiteDriverTest extends TestCase
{
    private SQLiteDriver $driver;
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();
        
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite extension not loaded');
        }
        
        $this->config = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_keys' => true,
            'journal_mode' => 'WAL',
            'synchronous' => 'NORMAL',
        ];
        
        $this->driver = new SQLiteDriver($this->config);
    }

    public function testImplementsDriverInterface(): void
    {
        $this->assertInstanceOf(DatabaseDriverInterface::class, $this->driver);
    }

    public function testGetName(): void
    {
        $this->assertSame('sqlite', $this->driver->getName());
    }

    public function testSupportsTransactions(): void
    {
        $this->assertTrue($this->driver->supportsTransactions());
    }

    public function testDoesNotSupportPooling(): void
    {
        $this->assertFalse($this->driver->supportsPooling());
    }

    public function testGetConnection(): void
    {
        $connection = $this->driver->getConnection();
        
        $this->assertInstanceOf(PDO::class, $connection);
        
        // Test connection is functional
        $result = $connection->query('SELECT 1 as test')->fetch();
        $this->assertSame(1, (int) $result['test']);
    }

    public function testConnectionCaching(): void
    {
        $connection1 = $this->driver->getConnection();
        $connection2 = $this->driver->getConnection();
        
        // Should return the same cached connection
        $this->assertSame($connection1, $connection2);
    }

    public function testMemoryDatabaseConnection(): void
    {
        $connection = $this->driver->getConnection();
        
        // Create a test table
        $connection->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
        $connection->exec("INSERT INTO test (name) VALUES ('test')");
        
        $result = $connection->query('SELECT COUNT(*) as count FROM test')->fetch();
        $this->assertSame(1, (int) $result['count']);
    }

    public function testFileDatabaseConnection(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'sqlite_test_');
        $fileConfig = [
            'driver' => 'sqlite',
            'database' => $tempFile,
        ];
        
        $fileDriver = new SQLiteDriver($fileConfig);
        $connection = $fileDriver->getConnection();
        
        $this->assertInstanceOf(PDO::class, $connection);
        
        // Test database file was created
        $this->assertFileExists($tempFile);
        
        // Cleanup
        $fileDriver->disconnect();
        unlink($tempFile);
    }

    public function testForeignKeyConstraintsEnabled(): void
    {
        $connection = $this->driver->getConnection();
        
        // Check foreign keys are enabled
        $result = $connection->query('PRAGMA foreign_keys')->fetch();
        $this->assertSame(1, (int) $result['foreign_keys']);
    }

    public function testJournalModeConfiguration(): void
    {
        $connection = $this->driver->getConnection();
        
        // Check journal mode is set (may be 'memory' for :memory: databases)
        $result = $connection->query('PRAGMA journal_mode')->fetch();
        $journalMode = strtolower($result['journal_mode']);
        $this->assertContains($journalMode, ['wal', 'memory']); // memory databases use memory journal mode
    }

    public function testSynchronousModeConfiguration(): void
    {
        $connection = $this->driver->getConnection();
        
        // Check synchronous mode is set
        $result = $connection->query('PRAGMA synchronous')->fetch();
        $this->assertSame(1, (int) $result['synchronous']); // NORMAL = 1
    }

    public function testGetQueryBuilder(): void
    {
        $queryBuilder = $this->driver->getQueryBuilder();
        
        $this->assertInstanceOf(QueryBuilderInterface::class, $queryBuilder);
        $this->assertInstanceOf(SQLiteQueryBuilder::class, $queryBuilder);
    }

    public function testGetSchemaBuilder(): void
    {
        $schemaBuilder = $this->driver->getSchemaBuilder();
        
        $this->assertInstanceOf(SchemaBuilderInterface::class, $schemaBuilder);
        $this->assertInstanceOf(SQLiteSchemaBuilder::class, $schemaBuilder);
    }

    public function testTransactionOperations(): void
    {
        $connection = $this->driver->getConnection();
        
        // Create test table
        $connection->exec('CREATE TABLE test_transactions (id INTEGER PRIMARY KEY, value TEXT)');
        
        // Test begin transaction
        $this->driver->beginTransaction();
        $connection->exec("INSERT INTO test_transactions (value) VALUES ('test1')");
        
        // Test commit
        $this->driver->commit();
        
        $result = $connection->query('SELECT COUNT(*) as count FROM test_transactions')->fetch();
        $this->assertSame(1, (int) $result['count']);
    }

    public function testTransactionRollback(): void
    {
        $connection = $this->driver->getConnection();
        
        // Create test table
        $connection->exec('CREATE TABLE test_rollback (id INTEGER PRIMARY KEY, value TEXT)');
        
        // Insert initial data
        $connection->exec("INSERT INTO test_rollback (value) VALUES ('initial')");
        
        // Start transaction and insert data
        $this->driver->beginTransaction();
        $connection->exec("INSERT INTO test_rollback (value) VALUES ('test')");
        
        // Rollback transaction
        $this->driver->rollback();
        
        // Should only have initial data
        $result = $connection->query('SELECT COUNT(*) as count FROM test_rollback')->fetch();
        $this->assertSame(1, (int) $result['count']);
    }

    public function testTransactionCallbackSuccess(): void
    {
        $connection = $this->driver->getConnection();
        $connection->exec('CREATE TABLE test_callback (id INTEGER PRIMARY KEY, value TEXT)');
        
        $result = $this->driver->transaction(function () use ($connection) {
            $connection->exec("INSERT INTO test_callback (value) VALUES ('success')");
            return 'transaction_result';
        });
        
        $this->assertSame('transaction_result', $result);
        
        // Verify data was committed
        $count = $connection->query('SELECT COUNT(*) as count FROM test_callback')->fetch();
        $this->assertSame(1, (int) $count['count']);
    }

    public function testTransactionCallbackRollback(): void
    {
        $connection = $this->driver->getConnection();
        $connection->exec('CREATE TABLE test_callback_rollback (id INTEGER PRIMARY KEY, value TEXT)');
        
        try {
            $this->driver->transaction(function () use ($connection) {
                $connection->exec("INSERT INTO test_callback_rollback (value) VALUES ('rollback')");
                throw new \Exception('Force rollback');
            });
        } catch (\Exception $e) {
            $this->assertSame('Force rollback', $e->getMessage());
        }
        
        // Verify data was rolled back
        $count = $connection->query('SELECT COUNT(*) as count FROM test_callback_rollback')->fetch();
        $this->assertSame(0, (int) $count['count']);
    }

    public function testGetStats(): void
    {
        $stats = $this->driver->getStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('queries_executed', $stats);
        $this->assertArrayHasKey('connections_created', $stats);
        $this->assertArrayHasKey('transactions_started', $stats);
        $this->assertArrayHasKey('last_query_time', $stats);
    }

    public function testStatsUpdateOnQuery(): void
    {
        $initialStats = $this->driver->getStats();
        $initialQueries = $initialStats['queries_executed'];
        
        // Execute a query that should record stats
        $this->driver->recordQueryExecution();
        
        $updatedStats = $this->driver->getStats();
        $this->assertSame($initialQueries + 1, $updatedStats['queries_executed']);
        $this->assertNotNull($updatedStats['last_query_time']);
    }

    public function testDisconnect(): void
    {
        // Get initial connection
        $connection = $this->driver->getConnection();
        $this->assertInstanceOf(PDO::class, $connection);
        
        // Disconnect
        $this->driver->disconnect();
        
        // Should get a new connection after disconnect
        $newConnection = $this->driver->getConnection();
        $this->assertInstanceOf(PDO::class, $newConnection);
        $this->assertNotSame($connection, $newConnection);
    }

    public function testGetDatabaseInfo(): void
    {
        $info = $this->driver->getDatabaseInfo();
        
        $this->assertIsArray($info);
        $this->assertArrayHasKey('sqlite_version', $info);
        $this->assertArrayHasKey('database_file', $info);
        $this->assertSame(':memory:', $info['database_file']);
    }

    public function testGetDatabaseInfoForFileDatabase(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'sqlite_test_');
        $fileConfig = [
            'driver' => 'sqlite',
            'database' => $tempFile,
        ];
        
        $fileDriver = new SQLiteDriver($fileConfig);
        $fileDriver->getConnection(); // Create the file
        
        $info = $fileDriver->getDatabaseInfo();
        
        $this->assertArrayHasKey('file_size', $info);
        $this->assertIsInt($info['file_size']);
        $this->assertGreaterThan(0, $info['file_size']);
        
        // Cleanup
        $fileDriver->disconnect();
        unlink($tempFile);
    }

    public function testVacuumOperation(): void
    {
        $connection = $this->driver->getConnection();
        
        // Create and populate a test table
        $connection->exec('CREATE TABLE test_vacuum (id INTEGER PRIMARY KEY, data TEXT)');
        $connection->exec("INSERT INTO test_vacuum (data) VALUES ('test')");
        
        // Vacuum should not throw any errors
        $this->driver->vacuum();
        
        // Verify table still exists and has data
        $result = $connection->query('SELECT COUNT(*) as count FROM test_vacuum')->fetch();
        $this->assertSame(1, (int) $result['count']);
    }

    public function testAnalyzeOperation(): void
    {
        $connection = $this->driver->getConnection();
        
        // Create and populate a test table
        $connection->exec('CREATE TABLE test_analyze (id INTEGER PRIMARY KEY, data TEXT)');
        $connection->exec("INSERT INTO test_analyze (data) VALUES ('test')");
        
        // Analyze should not throw any errors
        $this->driver->analyze();
        
        // Verify table still exists and has data
        $result = $connection->query('SELECT COUNT(*) as count FROM test_analyze')->fetch();
        $this->assertSame(1, (int) $result['count']);
    }

    public function testCustomOptionsConfiguration(): void
    {
        $customConfig = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'timeout' => 60,
            'options' => [
                PDO::ATTR_CASE => PDO::CASE_UPPER,
            ],
        ];
        
        $customDriver = new SQLiteDriver($customConfig);
        $connection = $customDriver->getConnection();
        
        $this->assertInstanceOf(PDO::class, $connection);
        
        // Test custom option was applied
        $this->assertSame(PDO::CASE_UPPER, $connection->getAttribute(PDO::ATTR_CASE));
    }

    public function testConnectionErrorHandling(): void
    {
        // Test with invalid database path (read-only filesystem)
        $invalidConfig = [
            'driver' => 'sqlite',
            'database' => '/dev/null/impossible.sqlite', // This should fail
        ];
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not connect to SQLite database');
        
        $invalidDriver = new SQLiteDriver($invalidConfig);
        $invalidDriver->getConnection();
    }

    public function testDirectoryCreationForFileDatabase(): void
    {
        $tempDir = sys_get_temp_dir() . '/sqlite_test_dir_' . uniqid();
        $dbFile = $tempDir . '/test.sqlite';
        
        $fileConfig = [
            'driver' => 'sqlite',
            'database' => $dbFile,
        ];
        
        // Directory should not exist initially
        $this->assertDirectoryDoesNotExist($tempDir);
        
        $fileDriver = new SQLiteDriver($fileConfig);
        $connection = $fileDriver->getConnection();
        
        // Directory should be created automatically
        $this->assertDirectoryExists($tempDir);
        $this->assertFileExists($dbFile);
        
        // Cleanup
        $fileDriver->disconnect();
        unlink($dbFile);
        rmdir($tempDir);
    }
}