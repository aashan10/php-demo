<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Elementary\Database\Model;
use Elementary\Database\DatabaseManager;
use Elementary\Config\ConfigBag;
use Tests\Support\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ModelTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset DatabaseManager instance for clean tests
        $reflection = new \ReflectionClass(DatabaseManager::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setValue(null);
        
        // Initialize DatabaseManager with test config
        $config = new ConfigBag(__DIR__ . '/../../../config');
        DatabaseManager::initialize($config);
    }

    public function testDatabaseManagerIsInitialized(): void
    {
        $this->assertInstanceOf(DatabaseManager::class, DatabaseManager::getInstance());
    }

    public function testQueryReturnsQueryBuilder(): void
    {
        $result = TestModel::query();
        $this->assertInstanceOf(\Elementary\Database\Contracts\QueryBuilderInterface::class, $result);
    }

    public function testModelTableName(): void
    {
        $model = new TestModel();
        $this->assertEquals('test_models', $model->getTable());
    }

    public function testModelPrimaryKey(): void
    {
        $model = new TestModel();
        $this->assertEquals('id', $model->getKeyName());
    }

    public function testModelExists(): void
    {
        $model = new TestModel();
        $this->assertFalse($model->exists());
        
        // Simulate existing model
        $reflection = new \ReflectionClass($model);
        $existsProperty = $reflection->getProperty('exists');
        $existsProperty->setValue($model, true);
        
        $this->assertTrue($model->exists());
    }

    public function testUpdateReturnsBool(): void
    {
        $model = new TestModel();
        
        // Mock the save method to avoid database interaction
        $modelMock = $this->createPartialMock(TestModel::class, ['save']);
        $modelMock->expects($this->once())
            ->method('save')
            ->willReturn(true);
        
        $result = $modelMock->update(['name' => 'Updated']);
        
        // Update now returns boolean
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testDeleteReturnsBool(): void
    {
        $model = new TestModel();
        $result = $model->delete();
        
        // Delete now returns boolean  
        $this->assertIsBool($result);
        $this->assertFalse($result); // False because model doesn't exist
    }

    public function testModelAttributeManagement(): void
    {
        $model = new TestModel(['name' => 'Test', 'email' => 'test@example.com']);
        
        $this->assertEquals('Test', $model->name);
        $this->assertEquals('test@example.com', $model->email);
        
        $model->name = 'Updated';
        $this->assertEquals('Updated', $model->name);
    }
}

class TestModel extends Model
{
    protected static string $table = 'test_models';
    protected static string $primaryKey = 'id';
    protected static array $fillable = ['name', 'email'];
}
