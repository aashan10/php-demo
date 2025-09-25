<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Elementary\Database\Model;
use Elementary\Database\QueryBuilder;
use Elementary\DI\Container;
use Tests\Support\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ModelTest extends TestCase
{
    private QueryBuilder|MockObject $mockQueryBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockQueryBuilder = $this->createMock(QueryBuilder::class);
        
        $this->container->bind(QueryBuilder::class, fn() => $this->mockQueryBuilder);
        
        TestModel::setContainer($this->container);
    }

    public function testSetContainer(): void
    {
        $container = new Container();
        TestModel::setContainer($container);
        
        $this->assertInstanceOf(Container::class, TestModel::getContainerForTesting());
    }

    public function testQuery(): void
    {
        $this->mockQueryBuilder->expects($this->once())
            ->method('table')
            ->with('test_models')
            ->willReturnSelf();
            
        $this->mockQueryBuilder->expects($this->once())
            ->method('setModel')
            ->with(TestModel::class)
            ->willReturnSelf();
            
        $this->mockQueryBuilder->expects($this->once())
            ->method('setPrimaryKey')
            ->with('id')
            ->willReturnSelf();

        $result = TestModel::query();
        
        $this->assertSame($this->mockQueryBuilder, $result);
    }

    public function testFind(): void
    {
        $expectedModel = new TestModel();
        
        $this->mockQueryBuilder->expects($this->once())
            ->method('table')
            ->willReturnSelf();
            
        $this->mockQueryBuilder->expects($this->once())
            ->method('setModel')
            ->willReturnSelf();
            
        $this->mockQueryBuilder->expects($this->once())
            ->method('setPrimaryKey')
            ->willReturnSelf();
            
        $this->mockQueryBuilder->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($expectedModel);

        $result = TestModel::find(1);
        
        $this->assertSame($expectedModel, $result);
    }

    public function testAll(): void
    {
        $expectedModels = [new TestModel(), new TestModel()];
        
        $this->mockQueryBuilder->expects($this->once())
            ->method('table')
            ->willReturnSelf();
            
        $this->mockQueryBuilder->expects($this->once())
            ->method('setModel')
            ->willReturnSelf();
            
        $this->mockQueryBuilder->expects($this->once())
            ->method('setPrimaryKey')
            ->willReturnSelf();
            
        $this->mockQueryBuilder->expects($this->once())
            ->method('get')
            ->willReturn($expectedModels);

        $result = TestModel::all();
        
        $this->assertSame($expectedModels, $result);
    }

    public function testCreate(): void
    {
        $data = ['name' => 'Test', 'email' => 'test@example.com'];
        
        $this->mockQueryBuilder->expects($this->once())
            ->method('table')
            ->willReturnSelf();
            
        $this->mockQueryBuilder->expects($this->once())
            ->method('setModel')
            ->willReturnSelf();
            
        $this->mockQueryBuilder->expects($this->once())
            ->method('setPrimaryKey')
            ->willReturnSelf();
            
        $this->mockQueryBuilder->expects($this->once())
            ->method('insert')
            ->with($data)
            ->willReturn(true);

        $result = TestModel::create($data);
        
        $this->assertTrue($result);
    }

    public function testUpdateThrowsExceptionWithoutPrimaryKey(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot update a model without a primary key value');
        
        $model = new TestModel();
        $model->update(['name' => 'Updated']);
    }

    public function testDeleteThrowsExceptionWithoutPrimaryKey(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot delete a model without a primary key value');
        
        $model = new TestModel();
        $model->delete();
    }

    public function testGetContainerThrowsExceptionWhenNotSet(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Container not set on AbstractModel');
        
        // Reset container to null to test the exception
        $reflection = new \ReflectionClass(EmptyModel::class);
        $containerProperty = $reflection->getProperty('container');
        $containerProperty->setValue(null);
        
        EmptyModel::getContainerForTesting();
    }
}

class TestModel extends Model
{
    protected static string $table = 'test_models';
    protected static string $primaryKey = 'id';
    
    public int $id;
    public string $name;
    public string $email;

    public static function getContainerForTesting(): Container
    {
        return self::getContainer();
    }
}

class EmptyModel extends Model
{
    protected static string $table = 'empty_models';
    
    public static function getContainerForTesting(): Container
    {
        return self::getContainer();
    }
}
