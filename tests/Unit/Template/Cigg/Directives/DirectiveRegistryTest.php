<?php

declare(strict_types=1);

namespace Tests\Unit\Template\Cigg\Directives;

use Elementary\Template\Cigg\Directives\DirectiveRegistry;
use Elementary\Template\Cigg\Directives\DirectiveInterface;
use PHPUnit\Framework\TestCase;

class DirectiveRegistryTest extends TestCase
{
    private DirectiveRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new DirectiveRegistry();
    }

    public function test_constructor_creates_empty_registry()
    {
        $registry = new DirectiveRegistry();
        
        $this->assertEmpty($registry->getNames());
        $this->assertEmpty($registry->getAllDirectives());
        $this->assertEmpty($registry->getAllCallables());
    }

    public function test_register_directive_class()
    {
        $directive = $this->createMockDirective('test');
        
        $this->registry->register($directive);
        
        $this->assertTrue($this->registry->has('test'));
        $this->assertSame($directive, $this->registry->get('test'));
        $this->assertContains('test', $this->registry->getNames());
        $this->assertContains($directive, $this->registry->getAllDirectives());
    }

    public function test_register_callable_directive()
    {
        $callable = function($expression, $raw) {
            return "<?php echo {$expression}; ?>";
        };
        
        $this->registry->registerCallable('custom', $callable);
        
        $this->assertTrue($this->registry->has('custom'));
        $this->assertSame($callable, $this->registry->getCallable('custom'));
        $this->assertContains('custom', $this->registry->getNames());
        $this->assertArrayHasKey('custom', $this->registry->getAllCallables());
    }

    public function test_get_nonexistent_directive_returns_null()
    {
        $this->assertNull($this->registry->get('nonexistent'));
        $this->assertNull($this->registry->getCallable('nonexistent'));
        $this->assertFalse($this->registry->has('nonexistent'));
    }

    public function test_register_multiple_directives()
    {
        $directive1 = $this->createMockDirective('if');
        $directive2 = $this->createMockDirective('foreach');
        $callable1 = fn($expr) => "callable1: {$expr}";
        $callable2 = fn($expr) => "callable2: {$expr}";
        
        $this->registry->register($directive1);
        $this->registry->register($directive2);
        $this->registry->registerCallable('custom1', $callable1);
        $this->registry->registerCallable('custom2', $callable2);
        
        $this->assertCount(4, $this->registry->getNames());
        $this->assertCount(2, $this->registry->getAllDirectives());
        $this->assertCount(2, $this->registry->getAllCallables());
        
        $this->assertSame($directive1, $this->registry->get('if'));
        $this->assertSame($directive2, $this->registry->get('foreach'));
        $this->assertSame($callable1, $this->registry->getCallable('custom1'));
        $this->assertSame($callable2, $this->registry->getCallable('custom2'));
    }

    public function test_directive_names_contain_both_types()
    {
        $directive = $this->createMockDirective('class_directive');
        $callable = fn($expr) => $expr;
        
        $this->registry->register($directive);
        $this->registry->registerCallable('callable_directive', $callable);
        
        $names = $this->registry->getNames();
        
        $this->assertCount(2, $names);
        $this->assertContains('class_directive', $names);
        $this->assertContains('callable_directive', $names);
    }

    public function test_has_method_works_for_both_types()
    {
        $directive = $this->createMockDirective('class_type');
        $callable = fn($expr) => $expr;
        
        $this->registry->register($directive);
        $this->registry->registerCallable('callable_type', $callable);
        
        $this->assertTrue($this->registry->has('class_type'));
        $this->assertTrue($this->registry->has('callable_type'));
        $this->assertFalse($this->registry->has('nonexistent'));
    }

    public function test_override_directive_with_same_name()
    {
        $directive1 = $this->createMockDirective('test');
        $directive2 = $this->createMockDirective('test');
        
        $this->registry->register($directive1);
        $this->registry->register($directive2);
        
        // Should have the second directive
        $this->assertSame($directive2, $this->registry->get('test'));
        $this->assertNotSame($directive1, $this->registry->get('test'));
        
        // Should still have only one name
        $this->assertCount(1, $this->registry->getNames());
    }

    public function test_override_callable_with_same_name()
    {
        $callable1 = fn() => 'first';
        $callable2 = fn() => 'second';
        
        $this->registry->registerCallable('test', $callable1);
        $this->registry->registerCallable('test', $callable2);
        
        // Should have the second callable
        $this->assertSame($callable2, $this->registry->getCallable('test'));
        $this->assertNotSame($callable1, $this->registry->getCallable('test'));
    }

    public function test_directive_class_takes_precedence_over_callable()
    {
        $directive = $this->createMockDirective('name');
        $callable = fn($expr) => $expr;
        
        // Register callable first
        $this->registry->registerCallable('name', $callable);
        $this->registry->register($directive);
        
        // get() should return the class directive
        $this->assertSame($directive, $this->registry->get('name'));
        
        // getCallable() should still return null since class takes precedence
        $this->assertSame($callable, $this->registry->getCallable('name'));
        
        // has() should return true
        $this->assertTrue($this->registry->has('name'));
    }

    public function test_remove_directive_class()
    {
        $directive = $this->createMockDirective('test');
        $this->registry->register($directive);
        
        $this->assertTrue($this->registry->has('test'));
        
        $this->registry->remove('test');
        
        $this->assertFalse($this->registry->has('test'));
        $this->assertNull($this->registry->get('test'));
        $this->assertNotContains('test', $this->registry->getNames());
    }

    public function test_remove_callable_directive()
    {
        $callable = fn($expr) => $expr;
        $this->registry->registerCallable('test', $callable);
        
        $this->assertTrue($this->registry->has('test'));
        
        $this->registry->remove('test');
        
        $this->assertFalse($this->registry->has('test'));
        $this->assertNull($this->registry->getCallable('test'));
        $this->assertNotContains('test', $this->registry->getNames());
    }

    public function test_remove_both_types_with_same_name()
    {
        $directive = $this->createMockDirective('test');
        $callable = fn($expr) => $expr;
        
        $this->registry->register($directive);
        $this->registry->registerCallable('test', $callable);
        
        $this->assertTrue($this->registry->has('test'));
        
        $this->registry->remove('test');
        
        $this->assertFalse($this->registry->has('test'));
        $this->assertNull($this->registry->get('test'));
        $this->assertNull($this->registry->getCallable('test'));
    }

    public function test_remove_nonexistent_directive()
    {
        // Should not throw an exception
        $this->registry->remove('nonexistent');
        
        $this->assertFalse($this->registry->has('nonexistent'));
    }

    public function test_clear_removes_all_directives()
    {
        $directive1 = $this->createMockDirective('class1');
        $directive2 = $this->createMockDirective('class2');
        $callable1 = fn() => 'callable1';
        $callable2 = fn() => 'callable2';
        
        $this->registry->register($directive1);
        $this->registry->register($directive2);
        $this->registry->registerCallable('custom1', $callable1);
        $this->registry->registerCallable('custom2', $callable2);
        
        $this->assertCount(4, $this->registry->getNames());
        
        $this->registry->clear();
        
        $this->assertEmpty($this->registry->getNames());
        $this->assertEmpty($this->registry->getAllDirectives());
        $this->assertEmpty($this->registry->getAllCallables());
        $this->assertFalse($this->registry->has('class1'));
        $this->assertFalse($this->registry->has('custom1'));
    }

    public function test_get_all_directives_returns_only_class_instances()
    {
        $directive1 = $this->createMockDirective('class1');
        $directive2 = $this->createMockDirective('class2');
        $callable = fn() => 'callable';
        
        $this->registry->register($directive1);
        $this->registry->register($directive2);
        $this->registry->registerCallable('custom', $callable);
        
        $allDirectives = $this->registry->getAllDirectives();
        
        $this->assertCount(2, $allDirectives);
        $this->assertContains($directive1, $allDirectives);
        $this->assertContains($directive2, $allDirectives);
        $this->assertNotContains($callable, $allDirectives);
    }

    public function test_get_all_callables_returns_only_callable_functions()
    {
        $directive = $this->createMockDirective('class');
        $callable1 = fn() => 'callable1';
        $callable2 = fn() => 'callable2';
        
        $this->registry->register($directive);
        $this->registry->registerCallable('custom1', $callable1);
        $this->registry->registerCallable('custom2', $callable2);
        
        $allCallables = $this->registry->getAllCallables();
        
        $this->assertCount(2, $allCallables);
        $this->assertArrayHasKey('custom1', $allCallables);
        $this->assertArrayHasKey('custom2', $allCallables);
        $this->assertSame($callable1, $allCallables['custom1']);
        $this->assertSame($callable2, $allCallables['custom2']);
    }

    public function test_registry_handles_complex_callable_functions()
    {
        $complexCallable = function($expression, $raw = '') {
            static $callCount = 0;
            $callCount++;
            return "<?php /* Call #{$callCount} */ echo {$expression}; ?>";
        };
        
        $this->registry->registerCallable('complex', $complexCallable);
        
        $this->assertTrue($this->registry->has('complex'));
        $this->assertSame($complexCallable, $this->registry->getCallable('complex'));
    }

    public function test_registry_handles_invokable_objects()
    {
        $invokableObject = new class {
            public function __invoke($expression) {
                return "Invokable: {$expression}";
            }
        };
        
        $this->registry->registerCallable('invokable', $invokableObject);
        
        $this->assertTrue($this->registry->has('invokable'));
        $this->assertSame($invokableObject, $this->registry->getCallable('invokable'));
    }

    public function test_registry_handles_array_callbacks()
    {
        $helper = new class {
            public function process($expression) {
                return "Helper: {$expression}";
            }
        };
        
        $arrayCallback = [$helper, 'process'];
        
        $this->registry->registerCallable('helper', $arrayCallback);
        
        $this->assertTrue($this->registry->has('helper'));
        $this->assertSame($arrayCallback, $this->registry->getCallable('helper'));
    }

    public function test_registry_preserves_directive_order()
    {
        $directives = [];
        for ($i = 0; $i < 5; $i++) {
            $directive = $this->createMockDirective("directive{$i}");
            $directives[] = $directive;
            $this->registry->register($directive);
        }
        
        $allDirectives = $this->registry->getAllDirectives();
        
        $this->assertCount(5, $allDirectives);
        
        // Values should be in the same order (though keys may vary)
        $this->assertEquals($directives, array_values($allDirectives));
    }

    public function test_edge_cases()
    {
        // Test with empty string name
        $directive = $this->createMockDirective('');
        $this->registry->register($directive);
        
        $this->assertTrue($this->registry->has(''));
        $this->assertSame($directive, $this->registry->get(''));
        
        // Test with numeric name
        $numericDirective = $this->createMockDirective('123');
        $this->registry->register($numericDirective);
        
        $this->assertTrue($this->registry->has('123'));
        $this->assertSame($numericDirective, $this->registry->get('123'));
        
        // Test with special characters
        $specialDirective = $this->createMockDirective('test-directive_name');
        $this->registry->register($specialDirective);
        
        $this->assertTrue($this->registry->has('test-directive_name'));
        $this->assertSame($specialDirective, $this->registry->get('test-directive_name'));
    }

    private function createMockDirective(string $name): DirectiveInterface
    {
        $mock = $this->createMock(DirectiveInterface::class);
        $mock->method('getName')->willReturn($name);
        $mock->method('isBlock')->willReturn(false);
        return $mock;
    }
}