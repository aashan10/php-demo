<?php

declare(strict_types=1);

namespace Tests\Unit\DI;

use Elementary\DI\Container;
use Elementary\DI\Exceptions\ContainerException;
use Elementary\DI\Exceptions\NotFoundException;
use Tests\Support\TestCase;

class ContainerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testCanBindAndResolveSimpleClass(): void
    {
        $this->container->bind(SimpleClass::class);
        
        $instance = $this->container->get(SimpleClass::class);
        
        $this->assertInstanceOf(SimpleClass::class, $instance);
    }

    public function testCanBindWithClosure(): void
    {
        $this->container->bind('test', fn() => new SimpleClass());
        
        $instance = $this->container->get('test');
        
        $this->assertInstanceOf(SimpleClass::class, $instance);
    }

    public function testReturnsExistingInstance(): void
    {
        $this->container->bind(SimpleClass::class);
        
        $instance1 = $this->container->get(SimpleClass::class);
        $instance2 = $this->container->get(SimpleClass::class);
        
        $this->assertSame($instance1, $instance2);
    }

    public function testMakeCreatesNewInstance(): void
    {
        $this->container->bind(SimpleClass::class);
        
        $instance1 = $this->container->make(SimpleClass::class);
        $instance2 = $this->container->make(SimpleClass::class);
        
        $this->assertNotSame($instance1, $instance2);
        $this->assertInstanceOf(SimpleClass::class, $instance1);
        $this->assertInstanceOf(SimpleClass::class, $instance2);
    }

    public function testCanResolveClassWithDependencies(): void
    {
        $this->container->bind(SimpleClass::class);
        $this->container->bind(ClassWithDependency::class);
        
        $instance = $this->container->get(ClassWithDependency::class);
        
        $this->assertInstanceOf(ClassWithDependency::class, $instance);
        $this->assertInstanceOf(SimpleClass::class, $instance->dependency);
    }

    public function testHasReturnsTrueForBoundServices(): void
    {
        $this->container->bind(SimpleClass::class);
        
        $this->assertTrue($this->container->has(SimpleClass::class));
        $this->assertFalse($this->container->has('nonexistent'));
    }

    public function testThrowsExceptionForNonexistentClass(): void
    {
        $this->expectException(ContainerException::class);
        
        $this->container->get('NonexistentClass');
    }

    public function testThrowsExceptionForUninstantiableClass(): void
    {
        $this->expectException(ContainerException::class);
        
        $this->container->get(AbstractClass::class);
    }

    public function testThrowsExceptionForMissingTypeHint(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('missing type hint');
        
        $this->container->get(ClassWithoutTypeHint::class);
    }

    public function testThrowsExceptionForBuiltinParameter(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Cannot resolve built-in parameter');
        
        $this->container->get(ClassWithBuiltinParameter::class);
    }

    public function testResolvesBuiltinParameterWithDefault(): void
    {
        $this->container->bind(SimpleClass::class);
        
        $instance = $this->container->get(ClassWithDefaultParameter::class);
        
        $this->assertInstanceOf(ClassWithDefaultParameter::class, $instance);
        $this->assertEquals('default', $instance->value);
    }
}

class SimpleClass
{
}

class ClassWithDependency
{
    public function __construct(public SimpleClass $dependency)
    {
    }
}

abstract class AbstractClass
{
}

class ClassWithoutTypeHint
{
    public function __construct($parameter)
    {
    }
}

class ClassWithBuiltinParameter
{
    public function __construct(string $value)
    {
    }
}

class ClassWithDefaultParameter
{
    public function __construct(public string $value = 'default')
    {
    }
}
