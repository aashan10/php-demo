<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Drivers;

use Elementary\Database\DatabaseManager;
use Elementary\Database\Contracts\QueryBuilderInterface;
use Elementary\Session\Drivers\DatabaseSessionDriver;
use Elementary\Session\Drivers\SessionDriverInterface;
use Elementary\Config\ConfigBag;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class DatabaseSessionDriverTest extends TestCase
{
    private DatabaseSessionDriver $driver;
    private DatabaseManager $databaseManager;
    private string $tableName = 'test_sessions';
    private string $sessionId;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->sessionId = 'test_session_' . uniqid();
        
        // Skip tests if we don't have SQLite support
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite extension not loaded');
            return;
        }
        
        // Initialize DatabaseManager with SQLite configuration for testing
        $dbConfig = $this->createMock(ConfigBag::class);
        $dbConfig->method('get')->willReturnMap([
            ['database.default', 'mysql', 'sqlite'],
            ['database.pool.enabled', false, false],
            ['database.connections.sqlite.driver', null, 'sqlite'],
            ['database.connections.sqlite.database', null, ':memory:'],
            ['database.connections.sqlite.foreign_keys', true, true],
            ['database.connections', [], [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                    'foreign_keys' => true,
                ]
            ]],
        ]);
        
        // Reset and initialize DatabaseManager for testing
        DatabaseManager::reset();
        DatabaseManager::initialize($dbConfig);
        $this->databaseManager = DatabaseManager::getInstance();
        
        // Create the test sessions table
        $this->createSessionsTable();
        
        // Clear any existing session data to ensure clean test state
        $this->clearSessionsTable();
        
        // Create the real DatabaseSessionDriver with SQLite
        $this->driver = new DatabaseSessionDriver($this->databaseManager, $this->tableName);
    }

    protected function tearDown(): void
    {
        // Clean up any test session data but don't drop table between tests
        try {
            if (isset($this->driver)) {
                $this->driver->destroy($this->sessionId);
            }
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
        
        parent::tearDown();
    }
    
    public static function tearDownAfterClass(): void
    {
        // Reset DatabaseManager after all tests in this class
        try {
            DatabaseManager::reset();
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
        parent::tearDownAfterClass();
    }

    public function testImplementsSessionDriverInterface(): void
    {
        $this->assertInstanceOf(SessionDriverInterface::class, $this->driver);
        $this->assertInstanceOf(\SessionHandlerInterface::class, $this->driver);
    }

    public function testOpenAlwaysReturnsTrue(): void
    {
        $result = $this->driver->open('', '');
        
        $this->assertTrue($result);
    }

    public function testCloseAlwaysReturnsTrue(): void
    {
        $result = $this->driver->close();
        
        $this->assertTrue($result);
    }

    public function testConstructorCreatesDriverSuccessfully(): void
    {
        $customTable = 'custom_sessions';
        $driver = new DatabaseSessionDriver($this->databaseManager, $customTable);
        
        $this->assertInstanceOf(DatabaseSessionDriver::class, $driver);
        $this->assertInstanceOf(SessionDriverInterface::class, $driver);
    }

    public function testReadReturnsEmptyStringForNonExistentSession(): void
    {
        // Test with a non-existent session ID
        $nonExistentId = 'non_existent_' . uniqid();
        $result = $this->driver->read($nonExistentId);
        
        $this->assertSame('', $result);
    }

    public function testWriteAndReadSessionData(): void
    {
        $sessionData = 'test_session_data_' . uniqid();
        
        // Write session data
        $writeResult = $this->driver->write($this->sessionId, $sessionData);
        $this->assertTrue($writeResult);
        
        // Read session data back
        $readResult = $this->driver->read($this->sessionId);
        $this->assertSame($sessionData, $readResult);
    }

    public function testWriteOverwritesExistingSessionData(): void
    {
        $firstData = 'first_data_' . uniqid();
        $secondData = 'second_data_' . uniqid();
        
        // Write first data
        $this->driver->write($this->sessionId, $firstData);
        $this->assertSame($firstData, $this->driver->read($this->sessionId));
        
        // Write second data (overwrite)
        $this->driver->write($this->sessionId, $secondData);
        $this->assertSame($secondData, $this->driver->read($this->sessionId));
    }

    public function testDestroyRemovesSessionData(): void
    {
        $sessionData = 'test_data_' . uniqid();
        
        // Write session data
        $this->driver->write($this->sessionId, $sessionData);
        $this->assertSame($sessionData, $this->driver->read($this->sessionId));
        
        // Destroy session
        $result = $this->driver->destroy($this->sessionId);
        $this->assertTrue($result);
        
        // Verify session no longer exists
        $this->assertSame('', $this->driver->read($this->sessionId));
    }

    public function testDestroyReturnsTrueForNonExistentSession(): void
    {
        $result = $this->driver->destroy('non_existent_session_' . uniqid());
        
        $this->assertTrue($result);
    }

    public function testGarbageCollectionRemovesExpiredSessions(): void
    {
        // Insert multiple sessions with different timestamps
        $connection = $this->databaseManager->connection();
        
        $oldSessionId = 'old_session_' . uniqid();
        $newSessionId = 'new_session_' . uniqid();
        
        $oldTimestamp = time() - 3600; // 1 hour ago
        $newTimestamp = time(); // Now
        
        // Insert sessions directly into database with custom timestamps
        $sql = "INSERT INTO `{$this->tableName}` (id, payload, last_activity) VALUES (?, ?, ?)";
        $stmt = $connection->getConnection()->prepare($sql);
        $stmt->execute([$oldSessionId, base64_encode('old_data'), $oldTimestamp]);
        $stmt->execute([$newSessionId, base64_encode('new_data'), $newTimestamp]);
        
        // Run garbage collection with 30 minute max lifetime
        $maxLifetime = 1800; // 30 minutes
        $removedCount = $this->driver->gc($maxLifetime);
        
        // Should have removed 1 expired session
        $this->assertSame(1, $removedCount);
        
        // Verify old session is gone, new session remains
        $this->assertSame('', $this->driver->read($oldSessionId));
        $this->assertSame('new_data', $this->driver->read($newSessionId));
        
        // Clean up
        $this->driver->destroy($newSessionId);
    }

    public function testWriteHandlesSpecialCharacters(): void
    {
        // Test with special characters that need proper encoding
        $sessionData = "Special chars: àáâãäåæçèéêë\n\r\t\0";
        
        $writeResult = $this->driver->write($this->sessionId, $sessionData);
        $this->assertTrue($writeResult);
        
        $readResult = $this->driver->read($this->sessionId);
        $this->assertSame($sessionData, $readResult);
    }

    public function testWriteHandlesEmptyData(): void
    {
        $emptyData = '';
        
        $writeResult = $this->driver->write($this->sessionId, $emptyData);
        $this->assertTrue($writeResult);
        
        $readResult = $this->driver->read($this->sessionId);
        $this->assertSame($emptyData, $readResult);
    }

    public function testWriteHandlesLargeData(): void
    {
        // Test with larger session data
        $largeData = str_repeat('Large session data chunk. ', 100);
        
        $writeResult = $this->driver->write($this->sessionId, $largeData);
        $this->assertTrue($writeResult);
        
        $readResult = $this->driver->read($this->sessionId);
        $this->assertSame($largeData, $readResult);
    }

    public function testMultipleSessionsCanBeHandled(): void
    {
        $sessions = [
            'session1_' . uniqid() => 'data1',
            'session2_' . uniqid() => 'data2',
            'session3_' . uniqid() => 'data3',
        ];
        
        // Write all sessions
        foreach ($sessions as $id => $data) {
            $this->assertTrue($this->driver->write($id, $data));
        }
        
        // Read all sessions
        foreach ($sessions as $id => $expectedData) {
            $this->assertSame($expectedData, $this->driver->read($id));
        }
        
        // Destroy one session
        $firstSessionId = array_key_first($sessions);
        $this->assertTrue($this->driver->destroy($firstSessionId));
        
        // Verify destroyed session is gone, others remain
        $this->assertSame('', $this->driver->read($firstSessionId));
        
        unset($sessions[$firstSessionId]);
        foreach ($sessions as $id => $expectedData) {
            $this->assertSame($expectedData, $this->driver->read($id));
        }
        
        // Clean up remaining sessions
        foreach (array_keys($sessions) as $id) {
            $this->driver->destroy($id);
        }
    }

    public function testBase64EncodingDecodingWorksCorrectly(): void
    {
        // Test data with various characters that need base64 encoding
        $testData = "Test data with: 中文, emojis 🚀🎉, newlines\n\r, tabs\t, nulls\0, quotes'\"";
        
        $this->driver->write($this->sessionId, $testData);
        $result = $this->driver->read($this->sessionId);
        
        $this->assertSame($testData, $result);
    }

    public function testDirectDatabaseInteraction(): void
    {
        // Test direct database interaction to debug
        $connection = $this->databaseManager->connection();
        $pdo = $connection->getConnection();
        
        // Insert data directly
        $stmt = $pdo->prepare("INSERT INTO \"{$this->tableName}\" (id, payload, last_activity) VALUES (?, ?, ?)");
        $testId = 'direct_test_' . uniqid();
        $payload = base64_encode('direct_test_data');
        $timestamp = time();
        
        $insertResult = $stmt->execute([$testId, $payload, $timestamp]);
        $this->assertTrue($insertResult);
        
        // Read data directly
        $stmt = $pdo->prepare("SELECT * FROM \"{$this->tableName}\" WHERE id = ?");
        $stmt->execute([$testId]);
        $result = $stmt->fetch();
        
        $this->assertNotNull($result);
        $this->assertSame($testId, $result['id']);
        $this->assertSame('direct_test_data', base64_decode($result['payload']));
        
        // Test using driver
        $driverResult = $this->driver->read($testId);
        $this->assertSame('direct_test_data', $driverResult);
    }

    public function testQueryBuilderDebugging(): void
    {
        // Test what SQL the QueryBuilder is generating
        $queryBuilder = $this->databaseManager->newQuery();
        $debug = $queryBuilder->table($this->tableName)->where('id', '=', 'test_id')->toDebugSql();
        
        // Let's see what SQL is being generated
        $expectedSql = 'SELECT "id", "payload", "last_activity" FROM "test_sessions" WHERE "id" = ?';
        $this->assertStringContainsString('SELECT', $debug['sql']);
        $this->assertStringContainsString('WHERE', $debug['sql']);
        $this->assertSame(['test_id'], $debug['bindings']);
    }

    public function testQueryBuilderFirstMethod(): void
    {
        // Insert test data directly
        $connection = $this->databaseManager->connection();
        $pdo = $connection->getConnection();
        
        $testId = 'qb_test_' . uniqid();
        $stmt = $pdo->prepare("INSERT INTO \"{$this->tableName}\" (id, payload, last_activity) VALUES (?, ?, ?)");
        $stmt->execute([$testId, base64_encode('qb_test_data'), time()]);
        
        // Test QueryBuilder first() method
        $queryBuilder = $this->databaseManager->newQuery();
        $result = $queryBuilder->table($this->tableName)->where('id', '=', $testId)->first();
        
        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertSame($testId, $result['id']);
        $this->assertSame('qb_test_data', base64_decode($result['payload']));
    }

    /**
     * Create the sessions table for testing
     */
    private function createSessionsTable(): void
    {
        $schema = $this->databaseManager->getSchemaBuilder();
        
        if (!$schema->hasTable($this->tableName)) {
            $schema->create($this->tableName, function ($table) {
                $table->string('id', 255);
                $table->text('payload');
                $table->integer('last_activity');
                $table->primary('id');
            });
        }
    }

    /**
     * Clear all data from the sessions table
     */
    private function clearSessionsTable(): void
    {
        $connection = $this->databaseManager->connection();
        $sql = "DELETE FROM \"{$this->tableName}\"";
        $connection->getConnection()->exec($sql);
    }

    /**
     * Drop the sessions table after testing
     */
    private function dropSessionsTable(): void
    {
        $schema = $this->databaseManager->getSchemaBuilder();
        
        if ($schema->hasTable($this->tableName)) {
            $schema->dropTable($this->tableName);
        }
    }
}
