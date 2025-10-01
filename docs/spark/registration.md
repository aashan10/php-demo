# Component Registration

This guide explains how to register Spark components so they can be discovered and used throughout your application.

## Table of Contents

1. [Registration Overview](#registration-overview)
2. [Component Registry](#component-registry)
3. [Configuration-Based Registration](#configuration-based-registration)
4. [Manual Registration](#manual-registration)
5. [Auto-Discovery](#auto-discovery)
6. [Registration Patterns](#registration-patterns)
7. [Troubleshooting](#troubleshooting)

## Registration Overview

Spark components must be registered before they can be used in templates. Registration creates a mapping between component names and their corresponding PHP classes, allowing the system to:

- Discover components by name
- Instantiate the correct class when rendering
- Validate component existence
- Provide helpful error messages

### Registration Flow

```
Component Definition → Registration → Template Usage
     ↓                    ↓              ↓
MyComponent.php → Registry Entry → <ui-spark-my></ui-spark-my>
```

## Component Registry

The `SparkComponentRegistry` is the central registry that manages component mappings.

### Registry Structure

```php
// Internal registry structure
[
    'counter' => 'App\SparkComponents\CounterComponent',
    'todo-list' => 'App\SparkComponents\TodoListComponent',
    'user-profile' => 'App\SparkComponents\UserProfileComponent',
    'forms.contact' => 'App\SparkComponents\Forms\ContactFormComponent',
]
```

### Registry Methods

```php
use Elementary\Spark\SparkComponentRegistry;

// Register a component
SparkComponentRegistry::register('counter', CounterComponent::class);

// Check if component is registered
$exists = SparkComponentRegistry::has('counter');

// Get component class
$class = SparkComponentRegistry::get('counter');

// Get all registered components
$all = SparkComponentRegistry::all();

// Clear registry (useful for testing)
SparkComponentRegistry::clear();
```

## Configuration-Based Registration

The recommended approach is to register components via configuration files.

### Main Configuration File

Create `config/spark.php`:

```php
<?php
// config/spark.php

return [
    'components' => [
        // Basic component registration
        'counter' => App\SparkComponents\CounterComponent::class,
        'todo-list' => App\SparkComponents\TodoListComponent::class,
        'user-profile' => App\SparkComponents\UserProfileComponent::class,
        
        // Nested components (dots become dashes in templates)
        'forms.contact' => App\SparkComponents\Forms\ContactFormComponent::class,
        'forms.login' => App\SparkComponents\Forms\LoginFormComponent::class,
        
        // Widgets
        'widgets.weather' => App\SparkComponents\Widgets\WeatherWidgetComponent::class,
        'widgets.chat' => App\SparkComponents\Widgets\ChatWidgetComponent::class,
        
        // Admin components
        'admin.dashboard' => App\SparkComponents\Admin\DashboardComponent::class,
        'admin.users' => App\SparkComponents\Admin\UserManagementComponent::class,
    ],
    
    // Component settings
    'settings' => [
        'auto_discover' => true,
        'cache_registry' => true,
        'validate_on_register' => true,
    ]
];
```

### Loading Configuration

In your bootstrap process (typically `bootstrap.php`):

```php
<?php
// bootstrap.php

use Elementary\Spark\SparkManager;

// Get SparkManager instance from container
$sparkManager = $container->get(SparkManager::class);

// Load Spark configuration
$sparkConfig = require BASE_PATH . '/config/spark.php';

// Register all components with the unified manager
if (isset($sparkConfig['components'])) {
    foreach ($sparkConfig['components'] as $name => $class) {
        $sparkManager->registerComponent($name, $class);
    }
}
```

### Environment-Specific Registration

```php
<?php
// config/spark.php

$components = [
    'counter' => App\SparkComponents\CounterComponent::class,
    'todo-list' => App\SparkComponents\TodoListComponent::class,
];

// Add development-only components
if (getenv('APP_ENV') === 'development') {
    $components['debug.panel'] = App\SparkComponents\Debug\DebugPanelComponent::class;
    $components['debug.profiler'] = App\SparkComponents\Debug\ProfilerComponent::class;
}

// Add admin components for authenticated users
if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
    $components['admin.dashboard'] = App\SparkComponents\Admin\DashboardComponent::class;
}

return [
    'components' => $components,
];
```

## Manual Registration

For dynamic or conditional registration, you can register components manually:

### Basic Manual Registration

```php
use Elementary\Spark\SparkComponentRegistry;
use App\SparkComponents\CounterComponent;

// Register a single component
SparkComponentRegistry::register('counter', CounterComponent::class);
```

### Conditional Registration

```php
use Elementary\Spark\SparkComponentRegistry;

class ComponentRegistrar
{
    public static function registerComponents(): void
    {
        // Always register core components
        self::registerCoreComponents();
        
        // Conditionally register feature components
        if (FeatureFlag::isEnabled('todos')) {
            self::registerTodoComponents();
        }
        
        if (FeatureFlag::isEnabled('chat')) {
            self::registerChatComponents();
        }
        
        // Register user-specific components
        self::registerUserComponents();
    }
    
    private static function registerCoreComponents(): void
    {
        SparkComponentRegistry::register('counter', CounterComponent::class);
        SparkComponentRegistry::register('notification', NotificationComponent::class);
    }
    
    private static function registerTodoComponents(): void
    {
        SparkComponentRegistry::register('todo-list', TodoListComponent::class);
        SparkComponentRegistry::register('todo-item', TodoItemComponent::class);
    }
    
    private static function registerChatComponents(): void
    {
        SparkComponentRegistry::register('chat-window', ChatWindowComponent::class);
        SparkComponentRegistry::register('chat-message', ChatMessageComponent::class);
    }
    
    private static function registerUserComponents(): void
    {
        $user = getCurrentUser();
        
        if ($user && $user->isAdmin()) {
            SparkComponentRegistry::register('admin-panel', AdminPanelComponent::class);
        }
        
        if ($user && $user->isPremium()) {
            SparkComponentRegistry::register('premium-dashboard', PremiumDashboardComponent::class);
        }
    }
}

// Call during bootstrap
ComponentRegistrar::registerComponents();
```

### Plugin-Based Registration

```php
class PluginManager
{
    private array $plugins = [];
    
    public function registerPlugin(SparkPlugin $plugin): void
    {
        $this->plugins[] = $plugin;
        
        // Register plugin components
        foreach ($plugin->getComponents() as $name => $class) {
            SparkComponentRegistry::register($name, $class);
        }
    }
    
    public function loadPluginsFromDirectory(string $directory): void
    {
        $pluginFiles = glob($directory . '/*.php');
        
        foreach ($pluginFiles as $file) {
            $plugin = require $file;
            if ($plugin instanceof SparkPlugin) {
                $this->registerPlugin($plugin);
            }
        }
    }
}

// Plugin interface
interface SparkPlugin
{
    public function getComponents(): array;
    public function getName(): string;
    public function getVersion(): string;
}

// Example plugin
class TodoPlugin implements SparkPlugin
{
    public function getComponents(): array
    {
        return [
            'todo-list' => TodoListComponent::class,
            'todo-item' => TodoItemComponent::class,
            'todo-form' => TodoFormComponent::class,
        ];
    }
    
    public function getName(): string
    {
        return 'Todo Plugin';
    }
    
    public function getVersion(): string
    {
        return '1.0.0';
    }
}
```

## Auto-Discovery

Implement automatic component discovery based on file structure:

### Auto-Discovery Implementation

```php
class ComponentAutoDiscovery
{
    private string $componentDirectory;
    private string $namespace;
    
    public function __construct(
        string $componentDirectory = 'src/SparkComponents',
        string $namespace = 'App\SparkComponents'
    ) {
        $this->componentDirectory = $componentDirectory;
        $this->namespace = $namespace;
    }
    
    public function discover(): array
    {
        $components = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->componentDirectory)
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $component = $this->analyzeComponent($file);
                if ($component) {
                    $components[$component['name']] = $component['class'];
                }
            }
        }
        
        return $components;
    }
    
    private function analyzeComponent(SplFileInfo $file): ?array
    {
        $relativePath = str_replace($this->componentDirectory . '/', '', $file->getPathname());
        $className = str_replace(['/', '.php'], ['\\', ''], $relativePath);
        $fullClassName = $this->namespace . '\\' . $className;
        
        // Check if class exists and extends SparkComponent
        if (class_exists($fullClassName) && 
            is_subclass_of($fullClassName, SparkComponent::class)) {
            
            $componentName = $this->generateComponentName($className);
            
            return [
                'name' => $componentName,
                'class' => $fullClassName,
            ];
        }
        
        return null;
    }
    
    private function generateComponentName(string $className): string
    {
        // Remove 'Component' suffix
        $name = preg_replace('/Component$/', '', $className);
        
        // Convert PascalCase to kebab-case
        $name = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));
        
        // Replace backslashes with dots for nested components
        return str_replace('\\', '.', $name);
    }
}

// Usage in bootstrap
$discovery = new ComponentAutoDiscovery();
$discoveredComponents = $discovery->discover();

foreach ($discoveredComponents as $name => $class) {
    SparkComponentRegistry::register($name, $class);
}
```

### Selective Auto-Discovery

```php
class SelectiveAutoDiscovery extends ComponentAutoDiscovery
{
    private array $includePatterns = [];
    private array $excludePatterns = [];
    
    public function includePattern(string $pattern): self
    {
        $this->includePatterns[] = $pattern;
        return $this;
    }
    
    public function excludePattern(string $pattern): self
    {
        $this->excludePatterns[] = $pattern;
        return $this;
    }
    
    protected function shouldInclude(string $className): bool
    {
        // Check exclude patterns first
        foreach ($this->excludePatterns as $pattern) {
            if (fnmatch($pattern, $className)) {
                return false;
            }
        }
        
        // If no include patterns, include by default
        if (empty($this->includePatterns)) {
            return true;
        }
        
        // Check include patterns
        foreach ($this->includePatterns as $pattern) {
            if (fnmatch($pattern, $className)) {
                return true;
            }
        }
        
        return false;
    }
}

// Usage
$discovery = new SelectiveAutoDiscovery();
$discovery
    ->includePattern('*Component')
    ->excludePattern('*Test*')
    ->excludePattern('*Abstract*');

$components = $discovery->discover();
```

## Registration Patterns

### Namespace-Based Registration

```php
class NamespaceRegistrar
{
    public static function registerNamespace(
        string $namespace, 
        string $prefix = ''
    ): void {
        $classes = self::getClassesInNamespace($namespace);
        
        foreach ($classes as $class) {
            if (is_subclass_of($class, SparkComponent::class)) {
                $name = self::generateName($class, $prefix);
                SparkComponentRegistry::register($name, $class);
            }
        }
    }
    
    private static function getClassesInNamespace(string $namespace): array
    {
        // Implementation to get all classes in namespace
        // This is a simplified version
        return [];
    }
    
    private static function generateName(string $class, string $prefix): string
    {
        $shortName = (new ReflectionClass($class))->getShortName();
        $name = preg_replace('/Component$/', '', $shortName);
        $name = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));
        
        return $prefix ? $prefix . '.' . $name : $name;
    }
}

// Usage
NamespaceRegistrar::registerNamespace('App\SparkComponents\Forms', 'forms');
NamespaceRegistrar::registerNamespace('App\SparkComponents\Widgets', 'widgets');
```

### Attribute-Based Registration

```php
#[Attribute]
class SparkComponent
{
    public function __construct(
        public string $name,
        public array $aliases = []
    ) {}
}

// Component with attribute
#[SparkComponent('todo-list', ['todos', 'task-list'])]
class TodoListComponent extends SparkComponent
{
    // Component implementation
}

// Registration scanner
class AttributeRegistrar
{
    public static function scanAndRegister(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                self::scanFile($file->getPathname());
            }
        }
    }
    
    private static function scanFile(string $filename): void
    {
        $tokens = token_get_all(file_get_contents($filename));
        
        // Parse tokens to find classes with SparkComponent attribute
        // This is a simplified implementation
        
        foreach ($foundClasses as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(SparkComponent::class);
            
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                SparkComponentRegistry::register($instance->name, $class);
                
                // Register aliases
                foreach ($instance->aliases as $alias) {
                    SparkComponentRegistry::register($alias, $class);
                }
            }
        }
    }
}
```

## Troubleshooting

### Common Registration Issues

#### 1. Component Not Found

```
Error: Spark component 'my-component' is not registered
```

**Solutions:**
- Check that the component is registered in `config/spark.php`
- Verify the component name matches exactly
- Ensure the configuration is loaded during bootstrap

#### 2. Class Not Found

```
Error: Class App\SparkComponents\MyComponent does not exist
```

**Solutions:**
- Check the class file exists
- Verify the namespace is correct
- Ensure autoloading is working

#### 3. Invalid Component Class

```
Error: Class MyClass must extend SparkComponent
```

**Solutions:**
- Make sure your component extends `SparkComponent`
- Check for typos in the base class name
- Verify imports are correct

### Debugging Registration

```php
// Debug registered components
$registered = SparkComponentRegistry::all();
foreach ($registered as $name => $class) {
    echo "Component '{$name}' -> {$class}\n";
    
    if (!class_exists($class)) {
        echo "  ERROR: Class does not exist\n";
    } elseif (!is_subclass_of($class, SparkComponent::class)) {
        echo "  ERROR: Does not extend SparkComponent\n";
    } else {
        echo "  OK: Valid component\n";
    }
}
```

### Validation Helper

```php
class RegistrationValidator
{
    public static function validateRegistry(): array
    {
        $errors = [];
        $components = SparkComponentRegistry::all();
        
        foreach ($components as $name => $class) {
            $componentErrors = self::validateComponent($name, $class);
            if (!empty($componentErrors)) {
                $errors[$name] = $componentErrors;
            }
        }
        
        return $errors;
    }
    
    private static function validateComponent(string $name, string $class): array
    {
        $errors = [];
        
        // Check class exists
        if (!class_exists($class)) {
            $errors[] = "Class '{$class}' does not exist";
            return $errors; // No point in further validation
        }
        
        // Check extends SparkComponent
        if (!is_subclass_of($class, SparkComponent::class)) {
            $errors[] = "Class '{$class}' must extend SparkComponent";
        }
        
        // Check component name format
        if (!preg_match('/^[a-z0-9\-\.]+$/', $name)) {
            $errors[] = "Component name '{$name}' contains invalid characters";
        }
        
        // Check for naming conflicts
        $templateName = str_replace('.', '/', $name);
        $templatePath = "templates/spark/{$templateName}.cigg";
        if (!file_exists($templatePath)) {
            $errors[] = "Template file '{$templatePath}' not found";
        }
        
        return $errors;
    }
}

// Usage
$errors = RegistrationValidator::validateRegistry();
if (!empty($errors)) {
    foreach ($errors as $component => $componentErrors) {
        echo "Errors for component '{$component}':\n";
        foreach ($componentErrors as $error) {
            echo "  - {$error}\n";
        }
    }
}
```

## Best Practices

### 1. Use Configuration Files
Always prefer configuration-based registration over manual registration for maintainability.

### 2. Consistent Naming
Use consistent naming conventions for components and their registry keys.

### 3. Validate Early
Validate component registration during development to catch issues early.

### 4. Group Related Components
Use dot notation to group related components (e.g., `forms.contact`, `widgets.weather`).

### 5. Environment-Specific Components
Register different components based on environment or user roles when needed.

### 6. Documentation
Document your component registry structure for team members.

## Next Steps

- [Rendering Guide](rendering.md) - Learn how to render registered components
- [Creating Components](creating-components.md) - Build components to register
- [API Reference](api-reference.md) - Complete API documentation