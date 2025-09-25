<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use Elementary\Utils\FlashBag;
use Elementary\Utils\SessionBag;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class FlashBagTest extends TestCase
{
    private FlashBag $flashBag;
    private SessionBag|MockObject $sessionBag;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->sessionBag = $this->createMock(SessionBag::class);
        $this->flashBag = new FlashBag($this->sessionBag);
    }

    public function testConstructorRequiresSessionBag(): void
    {
        $sessionBag = $this->createMock(SessionBag::class);
        $flashBag = new FlashBag($sessionBag);
        
        $this->assertInstanceOf(FlashBag::class, $flashBag);
    }

    public function testAddStoresValueWithFlashPrefix(): void
    {
        $this->sessionBag->expects($this->once())
            ->method('set')
            ->with('flash_message', 'Hello World');
        
        $this->flashBag->add('message', 'Hello World');
    }

    public function testAddSupportsVariousDataTypes(): void
    {
        $expectedCalls = [
            ['flash_string', 'hello'],
            ['flash_integer', 42],
            ['flash_float', 3.14],
            ['flash_boolean', true],
            ['flash_array', ['a', 'b', 'c']],
            ['flash_object', (object) ['prop' => 'value']]
        ];
        
        $callCount = 0;
        $this->sessionBag->expects($this->exactly(6))
            ->method('set')
            ->willReturnCallback(function ($key, $value) use ($expectedCalls, &$callCount) {
                $expected = $expectedCalls[$callCount];
                $this->assertEquals($expected[0], $key);
                $this->assertEquals($expected[1], $value);
                $callCount++;
            });
        
        $this->flashBag->add('string', 'hello');
        $this->flashBag->add('integer', 42);
        $this->flashBag->add('float', 3.14);
        $this->flashBag->add('boolean', true);
        $this->flashBag->add('array', ['a', 'b', 'c']);
        $this->flashBag->add('object', (object) ['prop' => 'value']);
    }

    public function testGetRetrievesValueAndRemovesIt(): void
    {
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with('flash_message', null)
            ->willReturn('Flash message content');
        
        $this->sessionBag->expects($this->once())
            ->method('remove')
            ->with('flash_message');
        
        $result = $this->flashBag->get('message');
        
        $this->assertEquals('Flash message content', $result);
    }

    public function testGetReturnsDefaultWhenValueNotFound(): void
    {
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with('flash_nonexistent', 'default_value')
            ->willReturn('default_value');
        
        $this->sessionBag->expects($this->once())
            ->method('remove')
            ->with('flash_nonexistent');
        
        $result = $this->flashBag->get('nonexistent', 'default_value');
        
        $this->assertEquals('default_value', $result);
    }

    public function testGetAlwaysRemovesKeyAfterRetrieving(): void
    {
        // Even if the value doesn't exist, remove should still be called
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with('flash_test', null)
            ->willReturn(null);
        
        $this->sessionBag->expects($this->once())
            ->method('remove')
            ->with('flash_test');
        
        $result = $this->flashBag->get('test');
        
        $this->assertNull($result);
    }

    public function testHasChecksForFlashKey(): void
    {
        $this->sessionBag->expects($this->once())
            ->method('has')
            ->with('flash_message')
            ->willReturn(true);
        
        $result = $this->flashBag->has('message');
        
        $this->assertTrue($result);
    }

    public function testHasReturnsFalseWhenKeyDoesNotExist(): void
    {
        $this->sessionBag->expects($this->once())
            ->method('has')
            ->with('flash_nonexistent')
            ->willReturn(false);
        
        $result = $this->flashBag->has('nonexistent');
        
        $this->assertFalse($result);
    }

    public function testAllRetrievesAllFlashMessagesAndRemovesThem(): void
    {
        $sessionData = [
            'flash_success' => 'Operation successful',
            'flash_error' => 'An error occurred',
            'flash_info' => 'Information message',
            'regular_key' => 'Not a flash message',
            'flash_warning' => 'Warning message'
        ];
        
        $this->sessionBag->expects($this->once())
            ->method('all')
            ->willReturn($sessionData);
        
        // Expect remove to be called for each flash key
        $expectedRemoveCalls = ['flash_success', 'flash_error', 'flash_info', 'flash_warning'];
        $removeCallCount = 0;
        $this->sessionBag->expects($this->exactly(4))
            ->method('remove')
            ->willReturnCallback(function ($key) use ($expectedRemoveCalls, &$removeCallCount) {
                $this->assertEquals($expectedRemoveCalls[$removeCallCount], $key);
                $removeCallCount++;
            });
        
        $result = $this->flashBag->all();
        
        $expected = [
            'success' => 'Operation successful',
            'error' => 'An error occurred',
            'info' => 'Information message',
            'warning' => 'Warning message'
        ];
        
        $this->assertEquals($expected, $result);
    }

    public function testAllReturnsEmptyArrayWhenNoFlashMessages(): void
    {
        $sessionData = [
            'regular_key1' => 'value1',
            'regular_key2' => 'value2',
            'not_flash' => 'value3'
        ];
        
        $this->sessionBag->expects($this->once())
            ->method('all')
            ->willReturn($sessionData);
        
        $this->sessionBag->expects($this->never())
            ->method('remove');
        
        $result = $this->flashBag->all();
        
        $this->assertEquals([], $result);
    }

    public function testAllHandlesEmptySession(): void
    {
        $this->sessionBag->expects($this->once())
            ->method('all')
            ->willReturn([]);
        
        $this->sessionBag->expects($this->never())
            ->method('remove');
        
        $result = $this->flashBag->all();
        
        $this->assertEquals([], $result);
    }

    public function testFlashMessagesAreOneTimeUse(): void
    {
        // Simulate adding and then retrieving a flash message
        $this->sessionBag->expects($this->once())
            ->method('set')
            ->with('flash_message', 'One time message');
        
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with('flash_message', null)
            ->willReturn('One time message');
        
        $this->sessionBag->expects($this->once())
            ->method('remove')
            ->with('flash_message');
        
        // Add the message
        $this->flashBag->add('message', 'One time message');
        
        // Get the message (should remove it)
        $result = $this->flashBag->get('message');
        
        $this->assertEquals('One time message', $result);
    }

    public function testMultipleFlashMessagesOfSameType(): void
    {
        // Test that we can add multiple messages and they overwrite each other
        $expectedCalls = [
            ['flash_error', 'First error'],
            ['flash_error', 'Second error']
        ];
        
        $callCount = 0;
        $this->sessionBag->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function ($key, $value) use ($expectedCalls, &$callCount) {
                $expected = $expectedCalls[$callCount];
                $this->assertEquals($expected[0], $key);
                $this->assertEquals($expected[1], $value);
                $callCount++;
            });
        
        $this->flashBag->add('error', 'First error');
        $this->flashBag->add('error', 'Second error'); // Should overwrite first
    }

    public function testFlashBagWithComplexData(): void
    {
        $complexData = [
            'user' => ['id' => 123, 'name' => 'John'],
            'validation_errors' => [
                'email' => 'Invalid email format',
                'password' => 'Password too short'
            ],
            'redirect_url' => '/dashboard'
        ];
        
        $this->sessionBag->expects($this->once())
            ->method('set')
            ->with('flash_form_data', $complexData);
        
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with('flash_form_data', null)
            ->willReturn($complexData);
        
        $this->sessionBag->expects($this->once())
            ->method('remove')
            ->with('flash_form_data');
        
        $this->flashBag->add('form_data', $complexData);
        $result = $this->flashBag->get('form_data');
        
        $this->assertEquals($complexData, $result);
    }

    public function testTypicalFlashMessageWorkflow(): void
    {
        // Simulate a typical web application flow:
        // 1. Process form submission
        // 2. Add success/error messages  
        // 3. Redirect
        // 4. Display messages on next page
        // 5. Messages are automatically removed
        
        // Step 1 & 2: Add messages after processing
        $setCallCount = 0;
        $this->sessionBag->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function ($key, $value) use (&$setCallCount) {
                $expectedCalls = [
                    ['flash_success', 'User profile updated successfully!'],
                    ['flash_info', 'Check your email for confirmation.']
                ];
                $expected = $expectedCalls[$setCallCount];
                $this->assertEquals($expected[0], $key);
                $this->assertEquals($expected[1], $value);
                $setCallCount++;
            });
        
        $this->flashBag->add('success', 'User profile updated successfully!');
        $this->flashBag->add('info', 'Check your email for confirmation.');
        
        // Step 4: Retrieve messages to display (after redirect)
        $hasCallCount = 0;
        $this->sessionBag->expects($this->exactly(2))
            ->method('has')
            ->willReturnCallback(function ($key) use (&$hasCallCount) {
                $expectedKeys = ['flash_success', 'flash_info'];
                $this->assertEquals($expectedKeys[$hasCallCount], $key);
                $hasCallCount++;
                return true;
            });
        
        $getCallCount = 0;
        $this->sessionBag->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function ($key, $default) use (&$getCallCount) {
                $expectedCalls = [
                    ['flash_success', null, 'User profile updated successfully!'],
                    ['flash_info', null, 'Check your email for confirmation.']
                ];
                $expected = $expectedCalls[$getCallCount];
                $this->assertEquals($expected[0], $key);
                $this->assertEquals($expected[1], $default);
                $getCallCount++;
                return $expected[2];
            });
        
        $removeCallCount = 0;
        $this->sessionBag->expects($this->exactly(2))
            ->method('remove')
            ->willReturnCallback(function ($key) use (&$removeCallCount) {
                $expectedKeys = ['flash_success', 'flash_info'];
                $this->assertEquals($expectedKeys[$removeCallCount], $key);
                $removeCallCount++;
            });
        
        // Check if messages exist before displaying
        $this->assertTrue($this->flashBag->has('success'));
        $this->assertTrue($this->flashBag->has('info'));
        
        // Retrieve messages (they get removed automatically)
        $successMessage = $this->flashBag->get('success');
        $infoMessage = $this->flashBag->get('info');
        
        $this->assertEquals('User profile updated successfully!', $successMessage);
        $this->assertEquals('Check your email for confirmation.', $infoMessage);
    }

    public function testFlashKeyPrefixing(): void
    {
        // Test that keys are properly prefixed with 'flash_'
        $this->sessionBag->expects($this->once())
            ->method('set')
            ->with('flash_custom_key_name', 'value');
        
        $this->sessionBag->expects($this->once())
            ->method('has')
            ->with('flash_custom_key_name')
            ->willReturn(true);
        
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with('flash_custom_key_name', 'default')
            ->willReturn('value');
        
        $this->sessionBag->expects($this->once())
            ->method('remove')
            ->with('flash_custom_key_name');
        
        $this->flashBag->add('custom_key_name', 'value');
        $this->assertTrue($this->flashBag->has('custom_key_name'));
        $result = $this->flashBag->get('custom_key_name', 'default');
        
        $this->assertEquals('value', $result);
    }
}