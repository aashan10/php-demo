# Testing

The Elementary framework comes with a comprehensive PHPUnit test suite to ensure code quality and reliability. This guide covers how to write and run tests for your application.

## Overview

The testing setup includes:
- PHPUnit 11.x for testing framework
- Organized test directory structure
- Configuration for unit and feature tests
- Code coverage reporting
- Custom test base classes

## Directory Structure

```
tests/
├── Unit/           # Unit tests for individual classes
├── Feature/        # Integration/feature tests
└── Support/        # Test support classes and utilities
```

## Running Tests

### Basic Commands

Run all tests:
```bash
composer test
# or
./vendor/bin/phpunit
```

Run only unit tests:
```bash
composer test-unit
```

Run only feature tests:
```bash
composer test-feature
```

Generate coverage report:
```bash
composer test-coverage
```

**Note**: Coverage reports require Xdebug or pcov extension to be installed.

### Docker Environment

If you're using Docker:
```bash
docker-compose exec app composer test
```

## Writing Tests

### Unit Tests

Unit tests should extend the base `TestCase` class and test individual components in isolation:

```php
<?php

namespace Tests\Unit\MyModule;

use Tests\Support\TestCase;
use Elementary\MyModule\MyClass;

class MyClassTest extends TestCase
{
    public function testSomeMethod(): void
    {
        $instance = new MyClass();
        
        $result = $instance->someMethod('input');
        
        $this->assertEquals('expected', $result);
    }
}
```

### Feature Tests

Feature tests test your application's behavior from the user's perspective:

```php
<?php

namespace Tests\Feature;

use Tests\Support\TestCase;

class UserRegistrationTest extends TestCase
{
    public function testUserCanRegister(): void
    {
        // Test full user registration flow
    }
}
```

## Test Configuration

The `phpunit.xml` configuration includes:

- **Bootstrap**: Uses `bootstrap.php` for application initialization
- **Test Suites**: Separate unit and feature test suites
- **Code Coverage**: Includes source directories, excludes test files
- **Environment**: Sets `APP_ENV=testing`
- **Logging**: Generates HTML and JUnit reports

## Available Test Suites

### DI Container Tests
Located in `tests/Unit/DI/ContainerTest.php`
- Tests dependency injection and resolution
- Covers binding, singleton behavior, and error cases

### Template Engine Tests  
Located in `tests/Unit/Template/EngineTest.php`
- Tests Cigg template compilation and rendering
- Covers component rendering and caching

### Database Model Tests
Located in `tests/Unit/Database/ModelTest.php`
- Tests ActiveRecord-style model behavior
- Covers CRUD operations and query building

### Router Tests
Located in `tests/Unit/Routing/RouterTest.php`
- Tests route registration and middleware
- Covers route groups and named routes

## Best Practices

### 1. Test Structure
- Follow AAA pattern (Arrange, Act, Assert)
- Use descriptive test method names
- One assertion per test when possible

### 2. Mocking
Use PHPUnit's built-in mocking for dependencies:

```php
public function testWithMockedDependency(): void
{
    $mock = $this->createMock(DependencyClass::class);
    $mock->expects($this->once())
         ->method('someMethod')
         ->willReturn('mocked result');
    
    $instance = new ClassUnderTest($mock);
    // Test with mocked dependency
}
```

### 3. Data Providers
Use data providers for testing multiple scenarios:

```php
/**
 * @dataProvider validationDataProvider
 */
public function testValidation($input, $expected): void
{
    $result = $this->validator->validate($input);
    $this->assertEquals($expected, $result);
}

public function validationDataProvider(): array
{
    return [
        ['valid@email.com', true],
        ['invalid-email', false],
        ['', false],
    ];
}
```

### 4. Setup and Teardown
Use setUp and tearDown for test preparation:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->container = new Container();
    // Additional setup
}

protected function tearDown(): void
{
    // Cleanup
    parent::tearDown();
}
```

## Coverage Reports

HTML coverage reports are generated in `storage/coverage/` directory. Open `storage/coverage/index.html` in your browser to view detailed coverage information.

## Continuous Integration

The test suite is designed to work with CI/CD pipelines. Example GitHub Actions workflow:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v4
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: 8.2
        extensions: pdo, pdo_mysql
        
    - name: Install dependencies
      run: composer install
      
    - name: Run tests
      run: composer test
```

## Extending the Test Suite

To add new test categories:

1. Create new directories under `tests/`
2. Update `phpunit.xml` with new test suites
3. Add corresponding composer scripts
4. Follow existing naming conventions

The test suite provides a solid foundation for maintaining code quality and catching regressions as your Elementary application grows.