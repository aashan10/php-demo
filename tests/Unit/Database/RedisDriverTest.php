<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Elementary\Database\Drivers\RedisDriver;
use Elementary\Database\Contracts\QueryBuilderInterface;
use Elementary\Database\Contracts\SchemaBuilderInterface;
use Tests\Support\TestCase;
use Redis;

class RedisDriverTest extends TestCase
{
    private RedisDriver $driver;
    private array $testConfig;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testConfig = [
            'driver' => 'redis',
            'host' => 'redis',
            'port' => 6379,
            'database' => 1, // Use database 1 for testing
            'password' => null,
            'prefix' => 'test:',
            'pool' => [
                'enabled' => true,
                'min_connections' => 1,
                'max_connections' => 5,
                'connection_timeout' => 10,
                'idle_timeout' => 300,
            ],
        ];
        
        $this->driver = new RedisDriver($this->testConfig);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        try {
            $redis = $this->driver->getConnection();
            $redis->flushDb(); // Clear test database
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
        
        $this->driver->disconnect();
        parent::tearDown();
    }

    public function testDriverCreation(): void
    {
        $this->assertInstanceOf(RedisDriver::class, $this->driver);
        $this->assertEquals('redis', $this->driver->getName());
    }

    public function testConnection(): void
    {
        $connection = $this->driver->getConnection();
        $this->assertInstanceOf(Redis::class, $connection);
        
        // Test basic Redis operation
        $connection->set('test_key', 'test_value');
        $this->assertEquals('test_value', $connection->get('test_key'));
    }

    public function testQueryBuilder(): void
    {
        $queryBuilder = $this->driver->getQueryBuilder();
        $this->assertInstanceOf(QueryBuilderInterface::class, $queryBuilder);
    }

    public function testSchemaBuilder(): void
    {
        $schemaBuilder = $this->driver->getSchemaBuilder();
        $this->assertInstanceOf(SchemaBuilderInterface::class, $schemaBuilder);
    }

    public function testTransactionSupport(): void
    {
        $this->assertTrue($this->driver->supportsTransactions());
    }

    public function testPoolingSupport(): void
    {
        $this->assertTrue($this->driver->supportsPooling());
    }

    public function testTransactionCommit(): void
    {
        $redis = $this->driver->getConnection();
        
        $this->driver->beginTransaction();
        $redis->set('tx_key1', 'value1');
        $redis->set('tx_key2', 'value2');
        $this->driver->commit();
        
        // Verify data was committed
        $this->assertEquals('value1', $redis->get('tx_key1'));
        $this->assertEquals('value2', $redis->get('tx_key2'));
    }

    public function testTransactionRollback(): void
    {
        $redis = $this->driver->getConnection();
        
        $this->driver->beginTransaction();
        $redis->set('rollback_key', 'should_not_exist');
        $this->driver->rollback();
        
        // Verify data was not committed
        $this->assertFalse($redis->get('rollback_key'));
    }

    public function testTransactionCallback(): void
    {
        $result = $this->driver->transaction(function() {
            $redis = $this->driver->getConnection();
            $redis->set('callback_key', 'callback_value');
            return 'transaction_result';
        });
        
        $this->assertEquals('transaction_result', $result);
        
        // Verify data was committed
        $redis = $this->driver->getConnection();
        $this->assertEquals('callback_value', $redis->get('callback_key'));
    }

    public function testTransactionCallbackRollback(): void
    {
        $redis = $this->driver->getConnection();
        
        try {
            $this->driver->transaction(function() use ($redis) {
                $redis->set('error_key', 'should_not_exist');
                throw new \Exception('Transaction should rollback');
            });
        } catch (\Exception $e) {
            $this->assertEquals('Transaction should rollback', $e->getMessage());
        }
        
        // Verify data was rolled back
        $this->assertFalse($redis->get('error_key'));
    }

    public function testStats(): void
    {
        $stats = $this->driver->getStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('queries_executed', $stats);
        $this->assertArrayHasKey('connections_created', $stats);
        $this->assertArrayHasKey('pool', $stats);
        
        // Pool stats
        $this->assertArrayHasKey('current_connections', $stats['pool']);
        $this->assertArrayHasKey('max_connections', $stats['pool']);
        $this->assertEquals(5, $stats['pool']['max_connections']);
    }

    public function testConnectionPooling(): void
    {
        // Get multiple connections
        $conn1 = $this->driver->getPooledConnection();
        $conn2 = $this->driver->getPooledConnection();
        
        $this->assertInstanceOf(Redis::class, $conn1);
        $this->assertInstanceOf(Redis::class, $conn2);
        
        // Return connections to pool
        $this->driver->returnConnection($conn1);
        $this->driver->returnConnection($conn2);
        
        $stats = $this->driver->getStats();
        $this->assertGreaterThan(0, $stats['pool']['current_connections']);
    }

    public function testDirectCommand(): void
    {
        $result = $this->driver->executeCommand('SET', ['direct_key', 'direct_value']);
        $this->assertEquals('OK', $result);
        
        $value = $this->driver->executeCommand('GET', ['direct_key']);
        $this->assertEquals('direct_value', $value);
    }

    public function testConnectionRecovery(): void
    {
        // Get connection
        $redis = $this->driver->getConnection();
        $redis->set('recovery_test', 'value');
        
        // Simulate connection loss by creating new driver
        $newDriver = new RedisDriver($this->testConfig);
        $newRedis = $newDriver->getConnection();
        
        // Should be able to read the value
        $this->assertEquals('value', $newRedis->get('recovery_test'));
        
        $newDriver->disconnect();
    }
}