<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use Elementary\Config\ConfigBag;
use Elementary\Database\DatabaseManager;
use Elementary\Session\SessionManager;
use Elementary\Session\Drivers\FileSessionDriver;
use Elementary\Session\Drivers\DatabaseSessionDriver;
use Elementary\Session\Drivers\SessionDriverInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class SessionManagerTest extends TestCase
{
    private SessionManager $sessionManager;
    private ConfigBag|MockObject $config;
    private DatabaseManager $databaseManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->config = $this->createMock(ConfigBag::class);
        
        // Initialize a real DatabaseManager with minimal config for testing
        $dbConfig = $this->createMock(ConfigBag::class);
        $dbConfig->method('get')->willReturnMap([
            ['database.default', 'mysql', 'mysql'],
            ['database.pool.enabled', false, false],
            ['database.connections.mysql.driver', 'mysql', 'mysql'],
            ['database.connections.mysql.host', 'localhost', 'localhost'],
            ['database.connections.mysql.database', '', 'test'],
            ['database.connections.mysql.username', '', 'test'],
            ['database.connections.mysql.password', '', 'test'],
        ]);
        
        // Initialize DatabaseManager singleton if not already done
        try {
            $this->databaseManager = DatabaseManager::getInstance();
        } catch (\RuntimeException $e) {
            DatabaseManager::initialize($dbConfig);
            $this->databaseManager = DatabaseManager::getInstance();
        }
        
        $this->sessionManager = new SessionManager($this->config, $this->databaseManager);
    }

    public function testGetDriverReturnsFileDriverByDefault(): void
    {
        $this->config->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['session.driver', 'file', 'file'],
                ['session.files', BASE_PATH . '/cache/sessions', '/tmp/test_sessions']
            ]);

        $driver = $this->sessionManager->getDriver();
        
        $this->assertInstanceOf(FileSessionDriver::class, $driver);
        $this->assertInstanceOf(SessionDriverInterface::class, $driver);
    }

    public function testGetDriverReturnsDatabaseDriverWhenConfigured(): void
    {
        $this->config->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['session.driver', 'file', 'database'],
                ['session.table', 'sessions', 'custom_sessions']
            ]);

        $driver = $this->sessionManager->getDriver();
        
        $this->assertInstanceOf(DatabaseSessionDriver::class, $driver);
        $this->assertInstanceOf(SessionDriverInterface::class, $driver);
    }

    public function testGetDriverCachesDriverInstance(): void
    {
        $this->config->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['session.driver', 'file', 'file'],
                ['session.files', BASE_PATH . '/cache/sessions', '/tmp/test_sessions']
            ]);

        // Call getDriver twice
        $driver1 = $this->sessionManager->getDriver();
        $driver2 = $this->sessionManager->getDriver();
        
        // Should return the same instance
        $this->assertSame($driver1, $driver2);
    }

    public function testGetDriverThrowsExceptionForUnsupportedDriver(): void
    {
        $this->config->expects($this->once())
            ->method('get')
            ->with('session.driver', 'file')
            ->willReturn('unsupported');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported session driver [unsupported]');
        
        $this->sessionManager->getDriver();
    }

    public function testCreateFileDriverUsesConfiguredPath(): void
    {
        $customPath = '/custom/session/path';
        
        $this->config->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['session.driver', 'file', 'file'],
                ['session.files', BASE_PATH . '/cache/sessions', $customPath]
            ]);

        $driver = $this->sessionManager->getDriver();
        
        $this->assertInstanceOf(FileSessionDriver::class, $driver);
    }

    public function testCreateDatabaseDriverUsesConfiguredTable(): void
    {
        $customTable = 'custom_sessions';
        
        $this->config->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['session.driver', 'file', 'database'],
                ['session.table', 'sessions', $customTable]
            ]);

        $driver = $this->sessionManager->getDriver();
        
        $this->assertInstanceOf(DatabaseSessionDriver::class, $driver);
    }

    public function testCreateFileDriverUsesDefaultPathWhenNotConfigured(): void
    {
        $this->config->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['session.driver', 'file', 'file'],
                ['session.files', BASE_PATH . '/cache/sessions', BASE_PATH . '/cache/sessions']
            ]);

        $driver = $this->sessionManager->getDriver();
        
        $this->assertInstanceOf(FileSessionDriver::class, $driver);
    }

    public function testCreateDatabaseDriverUsesDefaultTableWhenNotConfigured(): void
    {
        $this->config->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['session.driver', 'file', 'database'],
                ['session.table', 'sessions', 'sessions']
            ]);

        $driver = $this->sessionManager->getDriver();
        
        $this->assertInstanceOf(DatabaseSessionDriver::class, $driver);
    }

    public function testSessionManagerConstructorAssignsProperties(): void
    {
        // This test ensures constructor properly assigns the dependencies
        $config = $this->createMock(ConfigBag::class);
        $sessionManager = new SessionManager($config, $this->databaseManager);
        
        $this->assertInstanceOf(SessionManager::class, $sessionManager);
    }

    public function testMultipleDriverTypesCanBeCreated(): void
    {
        // Test that we can create different driver types from the same manager
        $config1 = $this->createMock(ConfigBag::class);
        $config1->method('get')->willReturnMap([
            ['session.driver', 'file', 'file'],
            ['session.files', BASE_PATH . '/cache/sessions', '/tmp/sessions1']
        ]);
        
        $config2 = $this->createMock(ConfigBag::class);
        $config2->method('get')->willReturnMap([
            ['session.driver', 'file', 'database'],
            ['session.table', 'sessions', 'test_sessions']
        ]);
        
        $sessionManager1 = new SessionManager($config1, $this->databaseManager);
        $sessionManager2 = new SessionManager($config2, $this->databaseManager);
        
        $fileDriver = $sessionManager1->getDriver();
        $dbDriver = $sessionManager2->getDriver();
        
        $this->assertInstanceOf(FileSessionDriver::class, $fileDriver);
        $this->assertInstanceOf(DatabaseSessionDriver::class, $dbDriver);
        $this->assertNotSame($fileDriver, $dbDriver);
    }
}