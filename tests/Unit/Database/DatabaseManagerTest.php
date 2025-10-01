<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Elementary\Config\ConfigBag;
use Elementary\Database\DatabaseManager;
use Elementary\Database\Drivers\SQLiteDriver;
use Elementary\Database\Drivers\MySQLDriver;
use Elementary\Database\Drivers\RedisDriver;
use Elementary\Database\Contracts\DatabaseDriverInterface;
use Elementary\Database\Contracts\QueryBuilderInterface;
use Elementary\Database\Contracts\SchemaBuilderInterface;
use PHPUnit\Framework\TestCase;

class DatabaseManagerTest extends TestCase
{
    private ConfigBag $config;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset DatabaseManager singleton before each test
        DatabaseManager::reset();
        
        $this->config = $this->createMock(ConfigBag::class);
    }

    protected function tearDown(): void
    {
        // Reset DatabaseManager singleton after each test
        DatabaseManager::reset();
        parent::tearDown();
    }

    public function testSingletonInitializationAndAccess(): void
    {
        $this->config->method('get')->willReturnMap([
            ['database.default', 'mysql', 'sqlite'],
            ['database.pool.enabled', false, false],
            ['database.connections', [], [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ]
            ]],
        ]);

        // Test initialization
        DatabaseManager::initialize($this->config);
        $manager = DatabaseManager::getInstance();
        
        $this->assertInstanceOf(DatabaseManager::class, $manager);
        
        // Test singleton behavior - should return same instance
        $manager2 = DatabaseManager::getInstance();
        $this->assertSame($manager, $manager2);
    }

    public function testGetInstanceThrowsExceptionWhenNotInitialized(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DatabaseManager not initialized');
        
        DatabaseManager::getInstance();
    }

    public function testMultipleInitializationCallsDoNothing(): void
    {
        $this->config->method('get')->willReturnMap([
            ['database.default', 'mysql', 'sqlite'],
            ['database.pool.enabled', false, false],
            ['database.connections', [], [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ]
            ]],
        ]);

        DatabaseManager::initialize($this->config);
        $manager1 = DatabaseManager::getInstance();
        
        // Second initialization should not create new instance
        DatabaseManager::initialize($this->config);
        $manager2 = DatabaseManager::getInstance();
        
        $this->assertSame($manager1, $manager2);
    }

    public function testDefaultConnectionRetrieval(): void
    {
        $this->config->method('get')->willReturnMap([
            ['database.default', 'mysql', 'sqlite'],
            ['database.pool.enabled', false, false],
            ['database.connections', [], [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ]
            ]],
        ]);

        DatabaseManager::initialize($this->config);
        $manager = DatabaseManager::getInstance();
        
        $connection = $manager->connection();
        
        $this->assertInstanceOf(DatabaseDriverInterface::class, $connection);
        $this->assertInstanceOf(SQLiteDriver::class, $connection);
    }

    public function testNamedConnectionRetrieval(): void
    {
        $this->config->method('get')->willReturnMap([
            ['database.default', 'mysql', 'sqlite'],
            ['database.pool.enabled', false, false],
            ['database.connections', [], [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ],
                'sqlite2' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ]
            ]],
        ]);

        DatabaseManager::initialize($this->config);
        $manager = DatabaseManager::getInstance();
        
        $connection1 = $manager->connection('sqlite');
        $connection2 = $manager->connection('sqlite2');
        
        $this->assertInstanceOf(SQLiteDriver::class, $connection1);
        $this->assertInstanceOf(SQLiteDriver::class, $connection2);
        $this->assertNotSame($connection1, $connection2);
    }

    public function testConnectionCaching(): void
    {
        $this->config->method('get')->willReturnMap([
            ['database.default', 'mysql', 'sqlite'],
            ['database.pool.enabled', false, false],
            ['database.connections', [], [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ]
            ]],
        ]);

        DatabaseManager::initialize($this->config);
        $manager = DatabaseManager::getInstance();
        
        $connection1 = $manager->connection('sqlite');
        $connection2 = $manager->connection('sqlite');
        
        // Should return the same cached instance
        $this->assertSame($connection1, $connection2);
    }

    public function testInvalidConnectionThrowsException(): void
    {
        $this->config->method('get')->willReturnMap([
            ['database.default', 'mysql', 'sqlite'],
            ['database.pool.enabled', false, false],
            ['database.connections', [], []],
        ]);

        DatabaseManager::initialize($this->config);
        $manager = DatabaseManager::getInstance();
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Database connection [nonexistent] not configured');
        
        $manager->connection('nonexistent');
    }

    public function testInvalidDriverThrowsException(): void
    {
        $this->config->method('get')->willReturnMap([
            ['database.default', 'mysql', 'invalid'],
            ['database.pool.enabled', false, false],
            ['database.connections', [], [
                'invalid' => [
                    'driver' => 'invalid_driver',
                    'database' => ':memory:',
                ]
            ]],
        ]);

        DatabaseManager::initialize($this->config);
        $manager = DatabaseManager::getInstance();
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Database driver [invalid_driver] not supported');
        
        $manager->connection('invalid');
    }

    public function testNewQueryReturnsQueryBuilder(): void
    {
        $this->config->method('get')->willReturnMap([
            ['database.default', 'mysql', 'sqlite'],
            ['database.pool.enabled', false, false],
            ['database.connections', [], [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ]
            ]],
        ]);

        DatabaseManager::initialize($this->config);
        $manager = DatabaseManager::getInstance();
        
        $queryBuilder = $manager->newQuery();
        
        $this->assertInstanceOf(QueryBuilderInterface::class, $queryBuilder);
    }

    public function testGetSchemaBuilderForDifferentDrivers(): void
    {
        $this->config->method('get')->willReturnMap([
            ['database.default', 'mysql', 'sqlite'],
            ['database.pool.enabled', false, false],
            ['database.connections', [], [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ]
            ]],
        ]);

        DatabaseManager::initialize($this->config);
        $manager = DatabaseManager::getInstance();
        
        $schemaBuilder = $manager->getSchemaBuilder('sqlite');
        
        $this->assertInstanceOf(SchemaBuilderInterface::class, $schemaBuilder);
    }

    public function testCustomDriverRegistration(): void
    {
        $this->config->method('get')->willReturnMap([
            ['database.default', 'mysql', 'sqlite'],
            ['database.pool.enabled', false, false],
            ['database.connections', [], []],
        ]);

        DatabaseManager::initialize($this->config);
        $manager = DatabaseManager::getInstance();
        
        // Register a custom driver
        $manager->registerDriver('custom', SQLiteDriver::class);
        
        // This should work now (though the connection config would still be missing)
        // We can't test the actual connection creation without proper config
        $this->assertTrue(true); // Just ensure no exception is thrown during registration
    }

    public function testGetConnectionNames(): void
    {
        $this->config->method('get')->willReturnMap([
            ['database.default', 'mysql', 'sqlite'],
            ['database.pool.enabled', false, false],
            ['database.connections', [], [
                'sqlite' => ['driver' => 'sqlite'],
                'sqlite2' => ['driver' => 'sqlite'],
                'redis' => ['driver' => 'redis'],
            ]],
        ]);

        DatabaseManager::initialize($this->config);
        $manager = DatabaseManager::getInstance();
        
        $connectionNames = $manager->getConnectionNames();
        
        $this->assertIsArray($connectionNames);
        $this->assertContains('sqlite', $connectionNames);
        $this->assertContains('sqlite2', $connectionNames);
        $this->assertContains('redis', $connectionNames);
    }

    public function testDefaultConnectionGetterAndSetter(): void
    {
        $this->config->method('get')->willReturnMap([
            ['database.default', 'mysql', 'sqlite'],
            ['database.pool.enabled', false, false],
            ['database.connections', [], [
                'sqlite' => ['driver' => 'sqlite'],
                'redis' => ['driver' => 'redis'],
            ]],
        ]);

        DatabaseManager::initialize($this->config);
        $manager = DatabaseManager::getInstance();
        
        // Test getting default connection
        $this->assertSame('sqlite', $manager->getDefaultConnection());
        
        // Test setting default connection
        $manager->setDefaultConnection('redis');
        $this->assertSame('redis', $manager->getDefaultConnection());
    }

    public function testGetConfigReturnsConfigBag(): void
    {
        $this->config->method('get')->willReturnMap([
            ['database.default', 'mysql', 'sqlite'],
            ['database.pool.enabled', false, false],
            ['database.connections', [], []],
        ]);

        DatabaseManager::initialize($this->config);
        $manager = DatabaseManager::getInstance();
        
        $config = $manager->getConfig();
        
        $this->assertSame($this->config, $config);
    }

    public function testTransactionExecutionOnSpecificConnection(): void
    {
        $this->config->method('get')->willReturnMap([
            ['database.default', 'mysql', 'sqlite'],
            ['database.pool.enabled', false, false],
            ['database.connections', [], [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ]
            ]],
        ]);

        DatabaseManager::initialize($this->config);
        $manager = DatabaseManager::getInstance();
        
        // Create a simple test table for transaction testing
        $schema = $manager->getSchemaBuilder('sqlite');
        $schema->create('test_table', function ($table) {
            $table->integer('id', true);
            $table->string('name');
        });
        
        // Test successful transaction
        $result = $manager->transaction(function () use ($manager) {
            $connection = $manager->connection('sqlite');
            $pdo = $connection->getConnection();
            $pdo->exec("INSERT INTO test_table (name) VALUES ('test')");
            return 'success';
        }, 'sqlite');
        
        $this->assertSame('success', $result);
        
        // Verify data was inserted using the same connection
        $connection = $manager->connection('sqlite');
        $pdo = $connection->getConnection();
        $result = $pdo->query("SELECT COUNT(*) as count FROM test_table")->fetch();
        $this->assertSame(1, (int) $result['count']);
    }

    public function testTransactionRollbackOnException(): void
    {
        $this->config->method('get')->willReturnMap([
            ['database.default', 'mysql', 'sqlite'],
            ['database.pool.enabled', false, false],
            ['database.connections', [], [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ]
            ]],
        ]);

        DatabaseManager::initialize($this->config);
        $manager = DatabaseManager::getInstance();
        
        // Create a simple test table
        $schema = $manager->getSchemaBuilder('sqlite');
        $schema->create('test_table', function ($table) {
            $table->integer('id', true);
            $table->string('name');
        });
        
        // Test transaction rollback on exception
        try {
            $manager->transaction(function () use ($manager) {
                $connection = $manager->connection('sqlite');
                $pdo = $connection->getConnection();
                $pdo->exec("INSERT INTO test_table (name) VALUES ('test')");
                throw new \Exception('Force rollback');
            }, 'sqlite');
        } catch (\Exception $e) {
            $this->assertSame('Force rollback', $e->getMessage());
        }
        
        // Verify data was not inserted (transaction rolled back)
        $connection = $manager->connection('sqlite');
        $pdo = $connection->getConnection();
        $result = $pdo->query("SELECT COUNT(*) as count FROM test_table")->fetch();
        $this->assertSame(0, (int) $result['count']);
    }

    public function testGetConnectionStatsReturnsDriverStats(): void
    {
        $this->config->method('get')->willReturnMap([
            ['database.default', 'mysql', 'sqlite'],
            ['database.pool.enabled', false, false],
            ['database.connections', [], [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ]
            ]],
        ]);

        DatabaseManager::initialize($this->config);
        $manager = DatabaseManager::getInstance();
        
        $stats = $manager->getConnectionStats('sqlite');
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('queries_executed', $stats);
        $this->assertArrayHasKey('connections_created', $stats);
        $this->assertArrayHasKey('transactions_started', $stats);
    }

    public function testLegacyConnectionRetrieval(): void
    {
        $this->config->method('get')->willReturnMap([
            ['database.default', 'mysql', 'sqlite'],
            ['database.pool.enabled', false, false],
            ['database.connections', [], []],
        ]);

        DatabaseManager::initialize($this->config);
        $manager = DatabaseManager::getInstance();
        
        $legacyConnection = $manager->getConnection();
        
        $this->assertInstanceOf(\Elementary\Database\Connection::class, $legacyConnection);
    }
}