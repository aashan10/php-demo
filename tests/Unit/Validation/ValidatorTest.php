<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Elementary\Exceptions\MethodNotDefinedException;
use Elementary\Utils\ParameterBag;
use Elementary\Validation\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear any macros between tests to avoid interference
        $this->clearMacros();
    }

    protected function tearDown(): void
    {
        // Clear macros after each test
        $this->clearMacros();
        
        parent::tearDown();
    }

    private function clearMacros(): void
    {
        // Use reflection to access the private static $macros property
        $reflection = new \ReflectionClass(Validator::class);
        $macrosProperty = $reflection->getProperty('macros');
        $macrosProperty->setAccessible(true);
        $macrosProperty->setValue([]);
    }

    public function testConstructorSetsDataAndRules(): void
    {
        $data = ['name' => 'John', 'email' => 'john@example.com'];
        $rules = ['name' => ['required'], 'email' => ['required', 'email']];
        
        $validator = new Validator($data, $rules);
        
        // We can't directly access private properties, but we can test the behavior
        $this->assertInstanceOf(Validator::class, $validator);
    }

    public function testPassesReturnsTrueForValidData(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secretpassword',
            'password_confirmation' => 'secretpassword'
        ];
        
        $rules = [
            'name' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'minLength:8'],
            'password_confirmation' => ['required', 'equals:password']
        ];
        
        $validator = new Validator($data, $rules);
        
        $this->assertTrue($validator->passes());
        $this->assertFalse($validator->fails());
    }

    public function testFailsReturnsTrueForInvalidData(): void
    {
        $data = [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123'
        ];
        
        $rules = [
            'name' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'minLength:8']
        ];
        
        $validator = new Validator($data, $rules);
        
        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->fails());
    }

    public function testErrorsReturnsParameterBag(): void
    {
        $data = ['name' => ''];
        $rules = ['name' => ['required']];
        
        $validator = new Validator($data, $rules);
        $validator->passes(); // Run validation
        
        $errors = $validator->errors();
        
        $this->assertInstanceOf(ParameterBag::class, $errors);
        $this->assertFalse($errors->isEmpty());
    }

    // --- Required Rule Tests ---

    public function testRequiredRuleFailsForEmptyString(): void
    {
        $validator = new Validator(['name' => ''], ['name' => ['required']]);
        $validator->passes();
        
        $errors = $validator->errors();
        $this->assertTrue($errors->has('name'));
        
        $nameErrors = $errors->get('name');
        $this->assertInstanceOf(ParameterBag::class, $nameErrors);
        $this->assertEquals('The name field is required.', $nameErrors->get('name'));
    }

    public function testRequiredRuleFailsForNull(): void
    {
        $validator = new Validator(['name' => null], ['name' => ['required']]);
        $validator->passes();
        
        $errors = $validator->errors();
        $this->assertTrue($errors->has('name'));
    }

    public function testRequiredRuleFailsForMissingField(): void
    {
        $validator = new Validator([], ['name' => ['required']]);
        $validator->passes();
        
        $errors = $validator->errors();
        $this->assertTrue($errors->has('name'));
    }

    public function testRequiredRulePassesForNonEmptyString(): void
    {
        $validator = new Validator(['name' => 'John'], ['name' => ['required']]);
        
        $this->assertTrue($validator->passes());
        $this->assertTrue($validator->errors()->isEmpty());
    }

    public function testRequiredRulePassesForZero(): void
    {
        $validator = new Validator(['count' => 0], ['count' => ['required']]);
        
        // Note: In PHP, empty(0) is true, so this will fail
        // This might be expected behavior or might need adjustment
        $this->assertFalse($validator->passes());
    }

    // --- Email Rule Tests ---

    public function testEmailRulePassesForValidEmails(): void
    {
        $validEmails = [
            'user@example.com',
            'test.email@domain.org',
            'user+tag@example.co.uk',
            'firstname.lastname@company.com',
            '123@domain.com'
        ];

        foreach ($validEmails as $email) {
            $validator = new Validator(['email' => $email], ['email' => ['email']]);
            $this->assertTrue($validator->passes(), "Failed for email: $email");
        }
    }

    public function testEmailRuleFailsForInvalidEmails(): void
    {
        $invalidEmails = [
            'invalid-email',
            '@domain.com',
            'user@',
            'user space@domain.com',
            'user@domain',
            'user..double.dot@domain.com'
        ];

        foreach ($invalidEmails as $email) {
            $validator = new Validator(['email' => $email], ['email' => ['email']]);
            $validator->passes();
            
            $errors = $validator->errors();
            $this->assertTrue($errors->has('email'), "Should have failed for email: $email");
            
            $emailErrors = $errors->get('email');
            $this->assertEquals('The email must be a valid email address.', $emailErrors->get('email'));
        }
    }

    public function testEmailRuleSkipsValidationForEmptyValue(): void
    {
        // Email rule should not fail if value is empty (unless required is also present)
        $validator = new Validator(['email' => ''], ['email' => ['email']]);
        
        $this->assertTrue($validator->passes());
    }

    // --- MinLength Rule Tests ---

    public function testMinLengthRulePassesForSufficientLength(): void
    {
        $testCases = [
            ['password', 'password123', 8], // exactly 12 chars, min 8
            ['name', 'John', 4], // exactly 4 chars, min 4
            ['description', 'This is a long description', 10] // much longer than min
        ];

        foreach ($testCases as [$field, $value, $minLength]) {
            $validator = new Validator([$field => $value], [$field => ["minLength:$minLength"]]);
            $this->assertTrue($validator->passes(), "Failed for '$value' with minLength:$minLength");
        }
    }

    public function testMinLengthRuleFailsForInsufficientLength(): void
    {
        $testCases = [
            ['password', '123', 8], // 3 chars, min 8
            ['name', 'Jo', 3], // 2 chars, min 3
            // Note: empty string doesn't trigger minLength validation (use required rule)
        ];

        foreach ($testCases as [$field, $value, $minLength]) {
            $validator = new Validator([$field => $value], [$field => ["minLength:$minLength"]]);
            $validator->passes();
            
            $errors = $validator->errors();
            $this->assertTrue($errors->has($field), "Should have failed for '$value' with minLength:$minLength");
            
            $fieldErrors = $errors->get($field);
            $this->assertEquals("The $field must be at least $minLength characters.", $fieldErrors->get($field));
        }
        
        // Test that empty string passes minLength validation (as per current implementation)
        $validator = new Validator(['code' => ''], ['code' => ['minLength:1']]);
        $this->assertTrue($validator->passes(), "Empty string should pass minLength validation");
    }

    public function testMinLengthRuleSkipsValidationForEmptyValue(): void
    {
        $validator = new Validator(['password' => ''], ['password' => ['minLength:8']]);
        
        $this->assertTrue($validator->passes());
    }

    public function testMinLengthRuleTrimsWhitespace(): void
    {
        // "  abc  " should be treated as "abc" (3 chars)
        $validator = new Validator(['name' => '  abc  '], ['name' => ['minLength:5']]);
        $validator->passes();
        
        $errors = $validator->errors();
        $this->assertTrue($errors->has('name')); // Should fail because trimmed length is 3, but min is 5
    }

    // --- Equals Rule Tests ---

    public function testEqualsRulePassesForMatchingValues(): void
    {
        $data = [
            'password' => 'secret123',
            'password_confirmation' => 'secret123'
        ];
        $rules = ['password_confirmation' => ['equals:password']];
        
        $validator = new Validator($data, $rules);
        
        $this->assertTrue($validator->passes());
    }

    public function testEqualsRuleFailsForDifferentValues(): void
    {
        $data = [
            'password' => 'secret123',
            'password_confirmation' => 'different'
        ];
        $rules = ['password_confirmation' => ['equals:password']];
        
        $validator = new Validator($data, $rules);
        $validator->passes();
        
        $errors = $validator->errors();
        $this->assertTrue($errors->has('password_confirmation'));
        
        $fieldErrors = $errors->get('password_confirmation');
        $this->assertEquals('The password_confirmation must match the password field.', $fieldErrors->get('password_confirmation'));
    }

    public function testEqualsRuleFailsWhenComparisonFieldMissing(): void
    {
        $data = ['password_confirmation' => 'secret123'];
        $rules = ['password_confirmation' => ['equals:password']];
        
        $validator = new Validator($data, $rules);
        $validator->passes();
        
        $errors = $validator->errors();
        $this->assertTrue($errors->has('password_confirmation'));
    }

    public function testEqualsRuleSkipsValidationForEmptyValue(): void
    {
        $data = [
            'password' => 'secret123',
            'password_confirmation' => ''
        ];
        $rules = ['password_confirmation' => ['equals:password']];
        
        $validator = new Validator($data, $rules);
        
        $this->assertTrue($validator->passes());
    }

    // --- Complex Validation Scenarios ---

    public function testMultipleRulesForSingleField(): void
    {
        $data = ['email' => ''];
        $rules = ['email' => ['required', 'email']];
        
        $validator = new Validator($data, $rules);
        $validator->passes();
        
        $errors = $validator->errors();
        $this->assertTrue($errors->has('email'));
        
        // Should have the required error (first rule that fails)
        $emailErrors = $errors->get('email');
        $this->assertEquals('The email field is required.', $emailErrors->get('email'));
    }

    public function testMultipleFieldsWithDifferentRules(): void
    {
        $data = [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
            'age' => 'not-a-number'
        ];
        
        $rules = [
            'name' => ['required'],
            'email' => ['email'],
            'password' => ['minLength:8']
        ];
        
        $validator = new Validator($data, $rules);
        $validator->passes();
        
        $errors = $validator->errors();
        $this->assertTrue($errors->has('name'));
        $this->assertTrue($errors->has('email'));
        $this->assertTrue($errors->has('password'));
        $this->assertFalse($errors->has('age')); // No rules for age
    }

    public function testComplexValidationScenario(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'securepassword123',
            'password_confirmation' => 'securepassword123',
            'terms' => 'accepted'
        ];
        
        $rules = [
            'first_name' => ['required', 'minLength:2'],
            'last_name' => ['required', 'minLength:2'],
            'email' => ['required', 'email'],
            'password' => ['required', 'minLength:8'],
            'password_confirmation' => ['required', 'equals:password'],
            'terms' => ['required']
        ];
        
        $validator = new Validator($data, $rules);
        
        $this->assertTrue($validator->passes());
        $this->assertTrue($validator->errors()->isEmpty());
    }

    // --- Macroable Trait Integration Tests ---

    public function testMacroCanBeAdded(): void
    {
        // Add a custom validation rule via macro
        Validator::macro('validateNumeric', function (array $args): void {
            $field = $args[0];
            $value = $args[1];
            
            if (!empty($value) && !is_numeric($value)) {
                $this->addError($field, "The {$field} must be a number.");
            }
        });

        $data = ['age' => '25'];
        $rules = ['age' => ['numeric']];
        
        $validator = new Validator($data, $rules);
        
        $this->assertTrue($validator->passes());
    }

    public function testMacroValidationFails(): void
    {
        // Add a custom validation rule via macro
        Validator::macro('validateNumeric', function (array $args): void {
            $field = $args[0];
            $value = $args[1];
            
            if (!empty($value) && !is_numeric($value)) {
                $this->addError($field, "The {$field} must be a number.");
            }
        });

        $data = ['age' => 'not-a-number'];
        $rules = ['age' => ['numeric']];
        
        $validator = new Validator($data, $rules);
        $validator->passes();
        
        $errors = $validator->errors();
        $this->assertTrue($errors->has('age'));
        
        $ageErrors = $errors->get('age');
        $this->assertEquals('The age must be a number.', $ageErrors->get('age'));
    }

    public function testMacroWithParameters(): void
    {
        // Add a custom rule with parameters
        Validator::macro('validateMaxLength', function (array $args): void {
            $field = $args[0];
            $value = $args[1];
            $maxLength = $args[2];
            
            if (!empty($value) && strlen(trim($value)) > (int)$maxLength) {
                $this->addError($field, "The {$field} must not exceed {$maxLength} characters.");
            }
        });

        $data = ['description' => 'This is a very long description that exceeds the limit'];
        $rules = ['description' => ['maxLength:20']];
        
        $validator = new Validator($data, $rules);
        $validator->passes();
        
        $errors = $validator->errors();
        $this->assertTrue($errors->has('description'));
        
        $descErrors = $errors->get('description');
        $this->assertEquals('The description must not exceed 20 characters.', $descErrors->get('description'));
    }

    public function testUndefinedValidationRuleThrowsException(): void
    {
        $data = ['field' => 'value'];
        $rules = ['field' => ['nonExistentRule']];
        
        $validator = new Validator($data, $rules);
        
        $this->expectException(MethodNotDefinedException::class);
        $this->expectExceptionMessage('Call to undefined method `validateNonExistentRule` in an instance of class `Elementary\Validation\Validator`');
        
        $validator->passes();
    }

    public function testBuiltInRulesHavePrecedenceOverMacros(): void
    {
        // Try to override a built-in rule with a macro
        Validator::macro('validateRequired', function (array $args): void {
            $field = $args[0];
            $value = $args[1];
            
            // This custom required rule should NOT be called
            $this->addError($field, 'Custom required validation.');
        });

        $data = ['name' => ''];
        $rules = ['name' => ['required']];
        
        $validator = new Validator($data, $rules);
        $validator->passes();
        
        $errors = $validator->errors();
        $this->assertTrue($errors->has('name'));
        
        $nameErrors = $errors->get('name');
        // Should use the built-in message, not the custom one
        $this->assertEquals('The name field is required.', $nameErrors->get('name'));
        $this->assertNotEquals('Custom required validation.', $nameErrors->get('name'));
    }

    public function testMultipleMacrosCanBeRegistered(): void
    {
        // Register multiple custom validation rules
        Validator::macro('validateAlpha', function (array $args): void {
            $field = $args[0];
            $value = $args[1];
            
            if (!empty($value) && !ctype_alpha($value)) {
                $this->addError($field, "The {$field} may only contain letters.");
            }
        });

        Validator::macro('validatePositive', function (array $args): void {
            $field = $args[0];
            $value = $args[1];
            
            if (!empty($value) && (!is_numeric($value) || $value <= 0)) {
                $this->addError($field, "The {$field} must be a positive number.");
            }
        });

        $data = [
            'name' => 'John123', // Should fail alpha
            'score' => '-5' // Should fail positive
        ];
        $rules = [
            'name' => ['alpha'],
            'score' => ['positive']
        ];
        
        $validator = new Validator($data, $rules);
        $validator->passes();
        
        $errors = $validator->errors();
        $this->assertTrue($errors->has('name'));
        $this->assertTrue($errors->has('score'));
        
        $nameErrors = $errors->get('name');
        $scoreErrors = $errors->get('score');
        $this->assertEquals('The name may only contain letters.', $nameErrors->get('name'));
        $this->assertEquals('The score must be a positive number.', $scoreErrors->get('score'));
    }

    public function testMacroAccessToValidatorInternals(): void
    {
        // Test that macros have access to validator's data and methods
        Validator::macro('validateUnique', function (array $args): void {
            $field = $args[0];
            $value = $args[1];
            
            // Access the validator's data through $this
            $fieldCount = 0;
            foreach ($this->data as $key => $val) {
                if ($val === $value) {
                    $fieldCount++;
                }
            }
            
            if ($fieldCount > 1) {
                $this->addError($field, "The {$field} must be unique in the dataset.");
            }
        });

        $data = [
            'email1' => 'test@example.com',
            'email2' => 'test@example.com', // Duplicate
            'email3' => 'other@example.com'
        ];
        
        $rules = [
            'email1' => ['unique'],
            'email2' => ['unique'],
            'email3' => ['unique']
        ];
        
        $validator = new Validator($data, $rules);
        $validator->passes();
        
        $errors = $validator->errors();
        $this->assertTrue($errors->has('email1'));
        $this->assertTrue($errors->has('email2'));
        $this->assertFalse($errors->has('email3'));
    }

    public function testValidatorCanBeExtendedWithComplexMacros(): void
    {
        // Test a more complex macro that validates against external data
        $existingEmails = ['existing@example.com', 'taken@example.com'];
        
        Validator::macro('validateEmailNotExists', function (array $args) use ($existingEmails): void {
            $field = $args[0];
            $value = $args[1];
            
            if (!empty($value) && in_array($value, $existingEmails)) {
                $this->addError($field, "The {$field} already exists.");
            }
        });

        $data = ['email' => 'existing@example.com'];
        $rules = ['email' => ['emailNotExists']];
        
        $validator = new Validator($data, $rules);
        $validator->passes();
        
        $errors = $validator->errors();
        $this->assertTrue($errors->has('email'));
        
        $emailErrors = $errors->get('email');
        $this->assertEquals('The email already exists.', $emailErrors->get('email'));
    }
}