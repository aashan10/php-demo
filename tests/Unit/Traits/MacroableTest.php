<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use Elementary\Exceptions\MethodNotDefinedException;
use Elementary\Traits\Macroable;
use PHPUnit\Framework\TestCase;

class MacroableTest extends TestCase
{
    private MockMacroableClass $instance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->instance = new MockMacroableClass();
        $this->clearMacros();
    }

    protected function tearDown(): void
    {
        $this->clearMacros();
        parent::tearDown();
    }

    private function clearMacros(): void
    {
        $reflection = new \ReflectionClass(MockMacroableClass::class);
        $macrosProperty = $reflection->getProperty('macros');
        $macrosProperty->setAccessible(true);
        $macrosProperty->setValue([]);
    }

    public function test_can_register_and_call_macro()
    {
        MockMacroableClass::macro('customMethod', function ($args) {
            return 'macro result: ' . $args[0];
        });

        $result = $this->instance->customMethod('test');

        $this->assertEquals('macro result: test', $result);
    }

    public function test_can_register_multiple_macros()
    {
        MockMacroableClass::macro('first', function () {
            return 'first';
        });

        MockMacroableClass::macro('second', function () {
            return 'second';
        });

        $this->assertEquals('first', $this->instance->first());
        $this->assertEquals('second', $this->instance->second());
    }

    public function test_macro_can_access_object_properties()
    {
        $this->instance->setProperty('test value');

        MockMacroableClass::macro('getProperty', function () {
            return $this->property;
        });

        $result = $this->instance->getProperty();

        $this->assertEquals('test value', $result);
    }

    public function test_macro_can_call_object_methods()
    {
        MockMacroableClass::macro('callOriginalMethod', function ($args) {
            return $this->originalMethod($args[0]);
        });

        $result = $this->instance->callOriginalMethod('test');

        $this->assertEquals('original: test', $result);
    }

    public function test_macro_receives_arguments_correctly()
    {
        MockMacroableClass::macro('multipleArgs', function ($args) {
            return [$args[0], $args[1], $args[2]];
        });

        $result = $this->instance->multipleArgs('first', 'second', 'third');

        $this->assertEquals(['first', 'second', 'third'], $result);
    }

    public function test_macro_can_return_different_types()
    {
        MockMacroableClass::macro('returnString', fn($args) => 'string');
        MockMacroableClass::macro('returnInt', fn($args) => 42);
        MockMacroableClass::macro('returnArray', fn($args) => [1, 2, 3]);
        MockMacroableClass::macro('returnNull', fn($args) => null);

        $this->assertEquals('string', $this->instance->returnString());
        $this->assertEquals(42, $this->instance->returnInt());
        $this->assertEquals([1, 2, 3], $this->instance->returnArray());
        $this->assertNull($this->instance->returnNull());
    }

    public function test_throws_exception_for_undefined_method()
    {
        $this->expectException(MethodNotDefinedException::class);
        $this->expectExceptionMessage('Call to undefined method `undefinedMethod` in an instance of class `Tests\Unit\Traits\MockMacroableClass`');

        $this->instance->undefinedMethod();
    }

    public function test_existing_methods_take_precedence_over_macros()
    {
        MockMacroableClass::macro('originalMethod', function ($args) {
            return 'macro: ' . $args[0];
        });

        $result = $this->instance->originalMethod('test');

        $this->assertEquals('original: test', $result);
    }

    public function test_macro_overrides_previous_macro_with_same_name()
    {
        MockMacroableClass::macro('testMethod', fn($args) => 'first');
        MockMacroableClass::macro('testMethod', fn($args) => 'second');

        $result = $this->instance->testMethod();

        $this->assertEquals('second', $result);
    }

    public function test_macro_can_use_closures_with_use_statements()
    {
        $external = 'external value';

        MockMacroableClass::macro('useClosure', function ($args) use ($external) {
            return $external;
        });

        $result = $this->instance->useClosure();

        $this->assertEquals('external value', $result);
    }

    public function test_macro_context_is_bound_to_instance()
    {
        $this->instance->setProperty('instance1');
        $instance2 = new MockMacroableClass();
        $instance2->setProperty('instance2');

        MockMacroableClass::macro('getContext', function ($args) {
            return $this->property;
        });

        $this->assertEquals('instance1', $this->instance->getContext());
        $this->assertEquals('instance2', $instance2->getContext());
    }

    public function test_macros_are_shared_across_all_instances()
    {
        MockMacroableClass::macro('sharedMethod', fn($args) => 'shared');

        $instance1 = new MockMacroableClass();
        $instance2 = new MockMacroableClass();

        $this->assertEquals('shared', $instance1->sharedMethod());
        $this->assertEquals('shared', $instance2->sharedMethod());
    }

    public function test_macro_can_modify_object_properties()
    {
        MockMacroableClass::macro('modifyProperty', function ($args) {
            $this->property = $args[0];
            return $this;
        });

        $result = $this->instance->modifyProperty('modified');

        $this->assertSame($this->instance, $result);
        $this->assertEquals('modified', $this->instance->getProperty());
    }

    public function test_macro_can_chain_methods()
    {
        MockMacroableClass::macro('chainable', function ($args) {
            $this->property = $args[0];
            return $this;
        });

        MockMacroableClass::macro('getValue', function ($args) {
            return $this->property;
        });

        $result = $this->instance->chainable('chained')->getValue();

        $this->assertEquals('chained', $result);
    }

    public function test_macro_with_complex_logic()
    {
        MockMacroableClass::macro('complexMacro', function ($args) {
            $numbers = $args[0];
            $result = 0;
            foreach ($numbers as $number) {
                if ($number > 10) {
                    $result += $number * 2;
                } else {
                    $result += $number;
                }
            }
            return $result;
        });

        $result = $this->instance->complexMacro([5, 15, 8, 20]);

        $this->assertEquals(83, $result); // 5 + (15*2) + 8 + (20*2) = 5 + 30 + 8 + 40 = 83
    }

    public function test_macro_can_call_other_macros()
    {
        MockMacroableClass::macro('helperMacro', function ($args) {
            return strtoupper($args[0]);
        });

        MockMacroableClass::macro('mainMacro', function ($args) {
            return 'Result: ' . $this->helperMacro($args[0]);
        });

        $result = $this->instance->mainMacro('test');

        $this->assertEquals('Result: TEST', $result);
    }

    public function test_macro_with_no_arguments()
    {
        MockMacroableClass::macro('noArgs', function ($args) {
            return 'no arguments';
        });

        $result = $this->instance->noArgs();

        $this->assertEquals('no arguments', $result);
    }

    public function test_macro_can_throw_exceptions()
    {
        MockMacroableClass::macro('throwingMacro', function ($args) {
            throw new \RuntimeException('Macro exception');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Macro exception');

        $this->instance->throwingMacro();
    }

    public function test_macro_preserves_argument_types()
    {
        MockMacroableClass::macro('typeTest', function ($args) {
            $string = $args[0];
            $int = $args[1];
            $array = $args[2];
            $bool = $args[3];
            $null = $args[4];
            
            return [
                'string' => is_string($string),
                'int' => is_int($int),
                'array' => is_array($array),
                'bool' => is_bool($bool),
                'null' => is_null($null),
            ];
        });

        $result = $this->instance->typeTest('test', 42, [1, 2, 3], true, null);

        $this->assertTrue($result['string']);
        $this->assertTrue($result['int']);
        $this->assertTrue($result['array']);
        $this->assertTrue($result['bool']);
        $this->assertTrue($result['null']);
    }

    public function test_call_macro_method_directly()
    {
        MockMacroableClass::macro('directCall', fn($args) => 'direct: ' . $args[0]);

        // Test calling callMacro directly (protected method access through public wrapper)
        $result = $this->instance->callMacroPublic('directCall', ['test']);

        $this->assertEquals('direct: test', $result);
    }

    public function test_call_macro_prefers_existing_methods()
    {
        MockMacroableClass::macro('originalMethod', fn($args) => 'macro: ' . $args[0]);

        $result = $this->instance->callMacroPublic('originalMethod', ['test']);

        $this->assertEquals('original: test', $result);
    }

    public function test_call_macro_throws_for_undefined_method()
    {
        $this->expectException(MethodNotDefinedException::class);

        $this->instance->callMacroPublic('undefined', []);
    }
}

// Mock class to test the Macroable trait
class MockMacroableClass
{
    use Macroable;

    private string $property = '';

    public function setProperty(string $value): void
    {
        $this->property = $value;
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function originalMethod(string $arg): string
    {
        return 'original: ' . $arg;
    }

    // Public wrapper for testing callMacro directly
    public function callMacroPublic(string $name, array $args): mixed
    {
        return $this->callMacro($name, $args);
    }
}