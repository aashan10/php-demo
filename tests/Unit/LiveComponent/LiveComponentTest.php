<?php

declare(strict_types=1);

namespace Tests\Unit\SparkComponent;

use Elementary\Spark\SparkComponent;
use Elementary\Template\Cigg\Engine;
use PHPUnit\Framework\TestCase;

class TestSparkComponent extends SparkComponent
{
    public string $name = 'John';
    public int $age = 25;
    
    public function updateName(string $newName): void
    {
        $this->name = $newName;
    }
    
    public function render(): string
    {
        return "<div>Hello {$this->name}, age {$this->age}</div>";
    }
}

class LiveComponentTest extends TestCase
{
    private SparkComponent $component;
    private Engine $engine;
    
    protected function setUp(): void
    {
        $this->engine = $this->createMock(Engine::class);
        $this->component = new TestSparkComponent($this->engine);
    }
    
    public function test_component_has_unique_id(): void
    {
        $this->assertNotEmpty($this->component->getId());
        $this->assertStringStartsWith('lw-', $this->component->getId());
    }
    
    public function test_component_gets_public_properties(): void
    {
        $properties = $this->component->getPublicProperties();
        
        $this->assertEquals('John', $properties['name']);
        $this->assertEquals(25, $properties['age']);
    }
    
    public function test_component_syncs_input(): void
    {
        $this->component->syncInput([
            'name' => 'Jane',
            'age' => 30
        ]);
        
        $this->assertEquals('Jane', $this->component->name);
        $this->assertEquals(30, $this->component->age);
    }
    
    public function test_component_calls_method(): void
    {
        $this->component->callMethod('updateName', ['Alice']);
        
        $this->assertEquals('Alice', $this->component->name);
    }
    
    public function test_component_validates_data(): void
    {
        $component = new class($this->engine) extends SparkComponent {
            public string $email = '';
            
            protected array $rules = [
                'email' => 'required|email'
            ];
            
            public function render(): string
            {
                return '<div></div>';
            }
        };
        
        $component->email = 'invalid-email';
        $errors = $component->validate();
        
        $this->assertArrayHasKey('email', $errors);
        $this->assertContains('The email field must be a valid email address.', $errors['email']);
    }
    
    public function test_component_generates_checksum(): void
    {
        $state = $this->component->getState();
        
        $this->assertArrayHasKey('checksum', $state);
        $this->assertNotEmpty($state['checksum']);
    }
    
    public function test_component_verifies_checksum(): void
    {
        $checksum = $this->component->getState()['checksum'];
        
        $this->assertTrue($this->component->verifyChecksum($checksum));
        $this->assertFalse($this->component->verifyChecksum('invalid-checksum'));
    }
}