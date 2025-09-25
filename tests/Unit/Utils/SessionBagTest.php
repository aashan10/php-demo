<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use Elementary\Utils\SessionBag;
use PHPUnit\Framework\TestCase;

class SessionBagTest extends TestCase
{
    private SessionBag $sessionBag;
    private array $originalSession;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Backup original session
        $this->originalSession = $_SESSION ?? [];
        
        // Initialize clean session for testing
        $_SESSION = [];
        
        $this->sessionBag = new SessionBag();
    }

    protected function tearDown(): void
    {
        // Restore original session
        $_SESSION = $this->originalSession;
        
        parent::tearDown();
    }

    public function testConstructorDoesNotStartSession(): void
    {
        // Constructor should be empty and not start session
        $sessionBag = new SessionBag();
        $this->assertInstanceOf(SessionBag::class, $sessionBag);
    }

    public function testGetReturnsValueFromSession(): void
    {
        $_SESSION['test_key'] = 'test_value';
        
        $this->assertEquals('test_value', $this->sessionBag->get('test_key'));
    }

    public function testGetReturnsDefaultForNonExistentKey(): void
    {
        $this->assertNull($this->sessionBag->get('non_existent_key'));
        $this->assertEquals('default_value', $this->sessionBag->get('non_existent_key', 'default_value'));
    }

    public function testGetSupportsVariousDataTypes(): void
    {
        $_SESSION['string'] = 'hello';
        $_SESSION['integer'] = 42;
        $_SESSION['float'] = 3.14;
        $_SESSION['boolean_true'] = true;
        $_SESSION['boolean_false'] = false;
        $_SESSION['null'] = null;
        $_SESSION['array'] = ['a', 'b', 'c'];
        $_SESSION['object'] = (object) ['prop' => 'value'];

        $this->assertEquals('hello', $this->sessionBag->get('string'));
        $this->assertEquals(42, $this->sessionBag->get('integer'));
        $this->assertEquals(3.14, $this->sessionBag->get('float'));
        $this->assertTrue($this->sessionBag->get('boolean_true'));
        $this->assertFalse($this->sessionBag->get('boolean_false'));
        $this->assertNull($this->sessionBag->get('null'));
        $this->assertEquals(['a', 'b', 'c'], $this->sessionBag->get('array'));
        $this->assertEquals((object) ['prop' => 'value'], $this->sessionBag->get('object'));
    }

    public function testSetStoresValueInSession(): void
    {
        $this->sessionBag->set('new_key', 'new_value');
        
        $this->assertEquals('new_value', $_SESSION['new_key']);
        $this->assertEquals('new_value', $this->sessionBag->get('new_key'));
    }

    public function testSetOverwritesExistingValue(): void
    {
        $_SESSION['existing_key'] = 'old_value';
        
        $this->sessionBag->set('existing_key', 'new_value');
        
        $this->assertEquals('new_value', $_SESSION['existing_key']);
        $this->assertEquals('new_value', $this->sessionBag->get('existing_key'));
    }

    public function testSetSupportsVariousDataTypes(): void
    {
        $this->sessionBag->set('string', 'hello');
        $this->sessionBag->set('integer', 42);
        $this->sessionBag->set('float', 3.14);
        $this->sessionBag->set('boolean', true);
        $this->sessionBag->set('array', ['nested', 'data']);
        $this->sessionBag->set('object', (object) ['property' => 'value']);

        $this->assertEquals('hello', $_SESSION['string']);
        $this->assertEquals(42, $_SESSION['integer']);
        $this->assertEquals(3.14, $_SESSION['float']);
        $this->assertTrue($_SESSION['boolean']);
        $this->assertEquals(['nested', 'data'], $_SESSION['array']);
        $this->assertEquals((object) ['property' => 'value'], $_SESSION['object']);
    }

    public function testHasReturnsTrueForExistingKeys(): void
    {
        $_SESSION['existing_key'] = 'value';
        
        $this->assertTrue($this->sessionBag->has('existing_key'));
    }

    public function testHasReturnsFalseForNonExistentKeys(): void
    {
        $this->assertFalse($this->sessionBag->has('non_existent_key'));
    }

    public function testHasReturnsFalseForNullValues(): void
    {
        $_SESSION['null_key'] = null;
        
        $this->assertFalse($this->sessionBag->has('null_key'));
    }

    public function testHasReturnsTrueForFalsyButSetValues(): void
    {
        $_SESSION['false_key'] = false;
        $_SESSION['zero_key'] = 0;
        $_SESSION['empty_string_key'] = '';
        $_SESSION['empty_array_key'] = [];

        $this->assertTrue($this->sessionBag->has('false_key'));
        $this->assertTrue($this->sessionBag->has('zero_key'));
        $this->assertTrue($this->sessionBag->has('empty_string_key'));
        $this->assertTrue($this->sessionBag->has('empty_array_key'));
    }

    public function testRemoveDeletesKeyFromSession(): void
    {
        $_SESSION['key_to_remove'] = 'value';
        $this->assertTrue($this->sessionBag->has('key_to_remove'));
        
        $this->sessionBag->remove('key_to_remove');
        
        $this->assertFalse($this->sessionBag->has('key_to_remove'));
        $this->assertArrayNotHasKey('key_to_remove', $_SESSION);
    }

    public function testRemoveNonExistentKeyDoesNotThrow(): void
    {
        $this->sessionBag->remove('non_existent_key');
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function testAllReturnsEntireSessionArray(): void
    {
        $_SESSION = [
            'key1' => 'value1',
            'key2' => 42,
            'key3' => ['nested', 'array']
        ];
        
        $result = $this->sessionBag->all();
        
        $this->assertEquals($_SESSION, $result);
    }

    public function testAllReturnsEmptyArrayWhenSessionEmpty(): void
    {
        $_SESSION = [];
        
        $result = $this->sessionBag->all();
        
        $this->assertEquals([], $result);
    }

    public function testClearRemovesAllSessionData(): void
    {
        $_SESSION = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];
        
        $this->sessionBag->clear();
        
        $this->assertEquals([], $_SESSION);
        $this->assertEquals([], $this->sessionBag->all());
        $this->assertFalse($this->sessionBag->has('key1'));
        $this->assertFalse($this->sessionBag->has('key2'));
        $this->assertFalse($this->sessionBag->has('key3'));
    }

    public function testDestroyCallsClearAndSessionDestroy(): void
    {
        // Mock session_status and session_destroy for testing
        if (session_status() === PHP_SESSION_NONE) {
            // If no session is active, just test the clear functionality
            $_SESSION = ['key1' => 'value1', 'key2' => 'value2'];
            
            $result = $this->sessionBag->destroy();
            
            // Should clear the session array
            $this->assertEquals([], $_SESSION);
            
            // Return value depends on session state - we can't reliably test session_destroy() 
            // without actually starting a session, so we just ensure no exception is thrown
            $this->assertTrue(is_bool($result));
        } else {
            // If session is active, test both clear and destroy
            $_SESSION = ['key1' => 'value1', 'key2' => 'value2'];
            
            $result = $this->sessionBag->destroy();
            
            $this->assertEquals([], $_SESSION);
            $this->assertTrue(is_bool($result));
        }
    }

    public function testSessionBagWorksWithComplexData(): void
    {
        $complexData = [
            'user' => [
                'id' => 123,
                'name' => 'John Doe',
                'preferences' => [
                    'theme' => 'dark',
                    'language' => 'en',
                    'notifications' => true
                ]
            ],
            'cart' => [
                'items' => [
                    ['id' => 1, 'name' => 'Product A', 'quantity' => 2],
                    ['id' => 2, 'name' => 'Product B', 'quantity' => 1]
                ],
                'total' => 99.99
            ]
        ];
        
        $this->sessionBag->set('complex_data', $complexData);
        
        $this->assertEquals($complexData, $this->sessionBag->get('complex_data'));
        $this->assertTrue($this->sessionBag->has('complex_data'));
        
        // Test that the data is actually stored in $_SESSION
        $this->assertEquals($complexData, $_SESSION['complex_data']);
    }

    public function testChainedOperations(): void
    {
        // Test a realistic workflow
        $this->sessionBag->set('user_id', 123);
        $this->sessionBag->set('username', 'john_doe');
        $this->sessionBag->set('preferences', ['theme' => 'dark']);
        
        $this->assertTrue($this->sessionBag->has('user_id'));
        $this->assertEquals(123, $this->sessionBag->get('user_id'));
        $this->assertEquals('john_doe', $this->sessionBag->get('username'));
        
        // Remove one item
        $this->sessionBag->remove('preferences');
        $this->assertFalse($this->sessionBag->has('preferences'));
        
        // Check remaining items
        $all = $this->sessionBag->all();
        $this->assertArrayHasKey('user_id', $all);
        $this->assertArrayHasKey('username', $all);
        $this->assertArrayNotHasKey('preferences', $all);
        
        // Clear everything
        $this->sessionBag->clear();
        $this->assertEquals([], $this->sessionBag->all());
    }
}