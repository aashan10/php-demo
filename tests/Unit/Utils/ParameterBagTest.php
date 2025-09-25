<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use Elementary\Utils\ParameterBag;
use PHPUnit\Framework\TestCase;

class ParameterBagTest extends TestCase
{
    private ParameterBag $bag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bag = new ParameterBag();
    }

    public function testConstructorWithEmptyParameters(): void
    {
        $bag = new ParameterBag();
        $this->assertTrue($bag->isEmpty());
        $this->assertEquals([], $bag->all());
    }

    public function testConstructorWithParameters(): void
    {
        $parameters = ['key1' => 'value1', 'key2' => 'value2'];
        $bag = new ParameterBag($parameters);
        
        $this->assertFalse($bag->isEmpty());
        $this->assertEquals($parameters, $bag->all());
    }

    public function testGetExistingParameter(): void
    {
        $this->bag->set('test_key', 'test_value');
        
        $this->assertEquals('test_value', $this->bag->get('test_key'));
    }

    public function testGetNonExistentParameterReturnsDefault(): void
    {
        $this->assertNull($this->bag->get('non_existent'));
        $this->assertEquals('default_value', $this->bag->get('non_existent', 'default_value'));
    }

    public function testGetSupportsVariousDataTypes(): void
    {
        $this->bag->set('string', 'hello');
        $this->bag->set('integer', 42);
        $this->bag->set('float', 3.14);
        $this->bag->set('boolean', true);
        $this->bag->set('array', ['a', 'b', 'c']);
        $this->bag->set('null', null);

        $this->assertEquals('hello', $this->bag->get('string'));
        $this->assertEquals(42, $this->bag->get('integer'));
        $this->assertEquals(3.14, $this->bag->get('float'));
        $this->assertTrue($this->bag->get('boolean'));
        $this->assertEquals(['a', 'b', 'c'], $this->bag->get('array'));
        $this->assertNull($this->bag->get('null'));
    }

    public function testHasReturnsTrueForExistingParameters(): void
    {
        $this->bag->set('existing_key', 'value');
        
        $this->assertTrue($this->bag->has('existing_key'));
    }

    public function testHasReturnsFalseForNonExistentParameters(): void
    {
        $this->assertFalse($this->bag->has('non_existent_key'));
    }

    public function testHasReturnsFalseForNullValues(): void
    {
        $this->bag->set('null_key', null);
        
        $this->assertFalse($this->bag->has('null_key'));
    }

    public function testSetAndGet(): void
    {
        $this->bag->set('new_key', 'new_value');
        
        $this->assertEquals('new_value', $this->bag->get('new_key'));
        $this->assertTrue($this->bag->has('new_key'));
    }

    public function testSetOverwritesExistingValue(): void
    {
        $this->bag->set('key', 'old_value');
        $this->bag->set('key', 'new_value');
        
        $this->assertEquals('new_value', $this->bag->get('key'));
    }

    public function testRemoveDeletesParameter(): void
    {
        $this->bag->set('key_to_remove', 'value');
        $this->assertTrue($this->bag->has('key_to_remove'));
        
        $this->bag->remove('key_to_remove');
        
        $this->assertFalse($this->bag->has('key_to_remove'));
        $this->assertNull($this->bag->get('key_to_remove'));
    }

    public function testRemoveNonExistentKeyDoesNotThrow(): void
    {
        $this->bag->remove('non_existent_key');
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function testClearRemovesAllParameters(): void
    {
        $this->bag->set('key1', 'value1');
        $this->bag->set('key2', 'value2');
        $this->assertFalse($this->bag->isEmpty());
        
        $this->bag->clear();
        
        $this->assertTrue($this->bag->isEmpty());
        $this->assertEquals([], $this->bag->all());
        $this->assertFalse($this->bag->has('key1'));
        $this->assertFalse($this->bag->has('key2'));
    }

    public function testAllReturnsAllParameters(): void
    {
        $parameters = [
            'key1' => 'value1',
            'key2' => 42,
            'key3' => ['nested', 'array']
        ];
        
        foreach ($parameters as $key => $value) {
            $this->bag->set($key, $value);
        }
        
        $this->assertEquals($parameters, $this->bag->all());
    }

    public function testOnlyReturnsSpecifiedKeys(): void
    {
        $this->bag->set('key1', 'value1');
        $this->bag->set('key2', 'value2');
        $this->bag->set('key3', 'value3');
        
        $result = $this->bag->only(['key1', 'key3']);
        
        $this->assertEquals(['key1' => 'value1', 'key3' => 'value3'], $result);
    }

    public function testOnlyWithNonExistentKeys(): void
    {
        $this->bag->set('existing_key', 'value');
        
        $result = $this->bag->only(['existing_key', 'non_existent_key']);
        
        $this->assertEquals(['existing_key' => 'value'], $result);
    }

    public function testOnlyWithEmptyArray(): void
    {
        $this->bag->set('key1', 'value1');
        $this->bag->set('key2', 'value2');
        
        $result = $this->bag->only([]);
        
        $this->assertEquals([], $result);
    }

    public function testIsEmptyReturnsTrueForEmptyBag(): void
    {
        $this->assertTrue($this->bag->isEmpty());
    }

    public function testIsEmptyReturnsFalseForNonEmptyBag(): void
    {
        $this->bag->set('key', 'value');
        
        $this->assertFalse($this->bag->isEmpty());
    }

    public function testIsEmptyReturnsTrueAfterClear(): void
    {
        $this->bag->set('key', 'value');
        $this->assertFalse($this->bag->isEmpty());
        
        $this->bag->clear();
        
        $this->assertTrue($this->bag->isEmpty());
    }

    public function testIsEmptyReturnsTrueAfterRemovingAllItems(): void
    {
        $this->bag->set('key1', 'value1');
        $this->bag->set('key2', 'value2');
        $this->assertFalse($this->bag->isEmpty());
        
        $this->bag->remove('key1');
        $this->bag->remove('key2');
        
        $this->assertTrue($this->bag->isEmpty());
    }

    public function testChainedOperations(): void
    {
        $this->bag->set('key1', 'value1');
        $this->bag->set('key2', 'value2');
        $this->bag->set('key3', 'value3');
        
        $this->assertEquals('value2', $this->bag->get('key2'));
        $this->bag->remove('key2');
        $this->assertFalse($this->bag->has('key2'));
        
        $only = $this->bag->only(['key1', 'key3']);
        $this->assertEquals(['key1' => 'value1', 'key3' => 'value3'], $only);
        
        $this->bag->clear();
        $this->assertTrue($this->bag->isEmpty());
    }
}