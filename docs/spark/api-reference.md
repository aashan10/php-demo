# Spark API Reference

Complete API reference for the Spark reactive component system.

## Table of Contents

1. [SparkComponent Class](#sparkcomponent-class)
2. [SparkManager Class](#sparkmanager-class)
3. [SparkComponentRegistry Class](#sparkcomponentregistry-class)
4. [Middleware Classes](#middleware-classes)
5. [JavaScript API](#javascript-api)
6. [Wire Directives](#wire-directives)
7. [Template Functions](#template-functions)
8. [Configuration Options](#configuration-options)

## SparkComponent Class

The base class for all Spark components.

### Class Definition

```php
namespace Elementary\Spark;

abstract class SparkComponent
{
    protected array $data = [];
    protected array $rules = [];
    protected array $listeners = [];
    protected string $componentId;
    protected Engine $engine;
    protected ?LoggerInterface $logger = null;
}
```

### Constructor

```php
public function __construct(Engine $engine, ?LoggerInterface $logger = null)
```

**Parameters:**
- `$engine` - The Cigg template engine instance
- `$logger` - Optional logger instance for debugging

### Abstract Methods

#### render()

```php
abstract public function render(): string
```

Returns the HTML content of the component. Must be implemented by all components.

**Returns:** `string` - The rendered HTML

**Example:**
```php
public function render(): string
{
    $cachePath = $this->getCompiledTemplate();
    $componentData = $this->getPublicProperties();
    extract($componentData);
    ob_start();
    include $cachePath;
    return ob_get_clean();
}
```

### Lifecycle Methods

#### mount()

```php
protected function mount(): void
```

Called when the component is first created. Override to initialize component state.

**Example:**
```php
protected function mount(): void
{
    $this->count = 0;
    $this->message = 'Welcome!';
}
```

### Property Management

#### __set()

```php
public function __set(string $name, mixed $value): void
```

Sets a dynamic property on the component.

**Parameters:**
- `$name` - Property name
- `$value` - Property value

#### __get()

```php
public function __get(string $name): mixed
```

Gets a dynamic property value.

**Parameters:**
- `$name` - Property name

**Returns:** `mixed` - Property value or null

#### __isset()

```php
public function __isset(string $name): bool
```

Checks if a dynamic property exists.

**Parameters:**
- `$name` - Property name

**Returns:** `bool` - True if property exists

#### getPublicProperties()

```php
public function getPublicProperties(): array
```

Returns all public properties that are synced with the frontend.

**Returns:** `array` - Associative array of public properties

### State Management

#### getId()

```php
public function getId(): string
```

Returns the unique component ID.

**Returns:** `string` - Component ID

#### setId()

```php
public function setId(string $id): void
```

Sets the component ID (used for state restoration).

**Parameters:**
- `$id` - Component ID

#### getState()

```php
public function getState(): array
```

Returns the complete component state for frontend synchronization.

**Returns:** `array` - Component state including ID, name, data, and checksum

### Data Synchronization

#### syncInput()

```php
public function syncInput(array $updates): void
```

Synchronizes properties from frontend updates.

**Parameters:**
- `$updates` - Array of property updates from frontend

**Throws:** `InvalidArgumentException` if attempting to sync non-public properties

### Method Invocation

#### callMethod()

```php
public function callMethod(string $method, array $params = []): void
```

Calls a public method on the component (used by frontend).

**Parameters:**
- `$method` - Method name to call
- `$params` - Array of parameters to pass

**Throws:** 
- `BadMethodCallException` if method doesn't exist or isn't public
- `RuntimeException` if method execution fails

### Validation

#### validate()

```php
public function validate(): array
```

Validates component data against defined rules.

**Returns:** `array` - Validation errors grouped by property

**Example:**
```php
protected array $rules = [
    'name' => 'required|min:2',
    'email' => 'required|email',
];

public function save(): void
{
    $errors = $this->validate();
    if (empty($errors)) {
        // Save logic
    }
}
```

### Events

#### emit()

```php
protected function emit(string $event, mixed $data = null): void
```

Emits an event to other components.

**Parameters:**
- `$event` - Event name
- `$data` - Optional event data

**Example:**
```php
$this->emit('user-saved', ['id' => $user->id, 'name' => $user->name]);
```

### Template Management

#### getCompiledTemplate()

```php
public function getCompiledTemplate(): string
```

Returns the path to the compiled template file.

**Returns:** `string` - Path to compiled template

**Throws:** `Exception` if template file not found

#### toHtml()

```php
public function toHtml(): string
```

Renders the component with Spark wrapper attributes.

**Returns:** `string` - Complete HTML with wire attributes

### Security

#### generateChecksum()

```php
public function generateChecksum(): string
```

Generates a security checksum for the component state.

**Returns:** `string` - SHA-256 checksum

#### verifyChecksum()

```php
public function verifyChecksum(string $checksum): bool
```

Verifies a checksum against current component state.

**Parameters:**
- `$checksum` - Checksum to verify

**Returns:** `bool` - True if checksum is valid

## SparkManager Class

Manages component lifecycle and request handling.

### Class Definition

```php
namespace Elementary\Spark;

class SparkManager
{
    private static ?self $instance = null;
    private array $components = [];
    private array $events = [];
    private ContainerInterface $container;
    private ?LoggerInterface $logger = null;
}
```

### Singleton Methods

#### getInstance()

```php
public static function getInstance(): self
```

Returns the singleton instance.

**Returns:** `SparkManager` instance

**Throws:** `RuntimeException` if not initialized

### Component Management

#### register()

```php
public function register(string $id, SparkComponent $component): void
```

Registers a component instance.

**Parameters:**
- `$id` - Component ID
- `$component` - Component instance

#### getComponent()

```php
public function getComponent(string $id): ?SparkComponent
```

Retrieves a registered component by ID.

**Parameters:**
- `$id` - Component ID

**Returns:** `SparkComponent|null` - Component instance or null

#### createComponent()

```php
public function createComponent(string $class, array $params = []): SparkComponent
```

Creates a new component instance.

**Parameters:**
- `$class` - Component class name
- `$params` - Optional parameters to set on component

**Returns:** `SparkComponent` - New component instance

**Throws:** `InvalidArgumentException` if class is invalid

### Request Handling

#### handleRequest()

```php
public function handleRequest(Request $request): Response
```

Handles AJAX requests from the frontend.

**Parameters:**
- `$request` - HTTP request object

**Returns:** `Response` - JSON response with component updates

### Event Management

#### addEvent()

```php
public function addEvent(string $event, mixed $data = null): void
```

Adds an event to be sent to frontend.

**Parameters:**
- `$event` - Event name
- `$data` - Optional event data

#### getEvents()

```php
public function getEvents(): array
```

Returns all pending events.

**Returns:** `array` - Array of events

#### clearEvents()

```php
public function clearEvents(): void
```

Clears all pending events.

### Rendering

#### renderComponent()

```php
public function renderComponent(string $class, array $params = []): string
```

Renders a component by class name.

**Parameters:**
- `$class` - Component class name
- `$params` - Optional parameters

**Returns:** `string` - Rendered HTML

**Throws:** `RuntimeException` if rendering fails

## SparkComponentRegistry Class

Static registry for component mappings.

### Class Definition

```php
namespace Elementary\Spark;

class SparkComponentRegistry
{
    private static array $components = [];
}
```

### Registry Methods

#### register()

```php
public static function register(string $name, string $class): void
```

Registers a component class.

**Parameters:**
- `$name` - Component name/key
- `$class` - Component class name

#### has()

```php
public static function has(string $name): bool
```

Checks if a component is registered.

**Parameters:**
- `$name` - Component name

**Returns:** `bool` - True if registered

#### get()

```php
public static function get(string $name): string
```

Gets a component class by name.

**Parameters:**
- `$name` - Component name

**Returns:** `string` - Component class name

**Throws:** `InvalidArgumentException` if not found

#### all()

```php
public static function all(): array
```

Returns all registered components.

**Returns:** `array` - Associative array of name => class mappings

#### clear()

```php
public static function clear(): void
```

Clears the registry (useful for testing).

## Middleware Classes

### ValidateJsonPayload

Validates JSON payload structure for Spark requests.

```php
namespace Elementary\Spark\Middleware;

class ValidateJsonPayload implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response;
}
```

**Purpose:** Ensures request contains valid JSON and stores parsed payload in request attributes.

### ValidateSparkRequest

Validates Spark-specific request fields.

```php
namespace Elementary\Spark\Middleware;

class ValidateSparkRequest implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response;
}
```

**Purpose:** Validates required fields (componentName, componentId, serverMemo) and component class existence.

### ValidateSparkChecksum

Validates request integrity via checksums.

```php
namespace Elementary\Spark\Middleware;

class ValidateSparkChecksum implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response;
}
```

**Purpose:** Verifies request integrity by validating component state checksums.

## JavaScript API

### Spark Class

Main JavaScript class for managing Spark components.

```typescript
class Spark {
    private components: Map<string, SparkComponent> = new Map();
    
    public start(): void;
    public getComponent(id: string): SparkComponent | undefined;
    public emit(event: string, data?: any): void;
}
```

#### Methods

##### start()

```typescript
public start(): void
```

Initializes the Spark system and scans for components.

##### getComponent()

```typescript
public getComponent(id: string): SparkComponent | undefined
```

Retrieves a component instance by ID.

**Parameters:**
- `id` - Component ID

**Returns:** Component instance or undefined

##### emit()

```typescript
public emit(event: string, data?: any): void
```

Emits a global event.

**Parameters:**
- `event` - Event name
- `data` - Optional event data

### SparkComponent Class

JavaScript component instance.

```typescript
class SparkComponent {
    public element: ComponentElement;
    public data: Record<string, any> = {};
    public checksum: string = '';
    public id: string;
    public name: string;
}
```

#### Methods

##### set()

```typescript
public set(property: string, value: any): void
```

Sets a component property value.

**Parameters:**
- `property` - Property name
- `value` - Property value

##### get()

```typescript
public get(property: string): any
```

Gets a component property value.

**Parameters:**
- `property` - Property name

**Returns:** Property value

##### callMethod()

```typescript
public async callMethod(method: string, params: any[] = []): Promise<void>
```

Calls a server-side method.

**Parameters:**
- `method` - Method name
- `params` - Method parameters

## Wire Directives

### wire:click

Handles click events on elements.

```html
<button wire:click="methodName">Click Me</button>
<button wire:click="methodWithParams('param1', 123)">With Params</button>
```

### wire:model

Two-way data binding for form inputs.

```html
<input type="text" wire:model="propertyName">
<input type="number" wire:model="count">
<input type="checkbox" wire:model="isActive">
<select wire:model="selectedOption">
    <option value="1">Option 1</option>
    <option value="2">Option 2</option>
</select>
```

**Behavior:**
- Text inputs use `blur` event by default
- Other inputs use `input` event
- Values are automatically type-converted

### wire:submit

Handles form submission.

```html
<form wire:submit="submitForm">
    <input type="text" wire:model="name">
    <button type="submit">Submit</button>
</form>
```

### Other Wire Events

```html
<input wire:blur="validateField">
<input wire:change="handleChange">
<input wire:keydown="handleKeypress">
```

## Template Functions

### Component Access

```html
<!-- Access component instance -->
{{ $__spark->getId() }}
{{ get_class($__spark) }}

<!-- Check component state -->
@if($__spark->hasErrors())
    <!-- Handle errors -->
@endif
```

### Debugging

```html
@if(config('app.debug'))
    <div class="debug">
        <pre>{{ json_encode($__spark->getPublicProperties(), JSON_PRETTY_PRINT) }}</pre>
    </div>
@endif
```

## Configuration Options

### Spark Configuration

```php
// config/spark.php
return [
    'components' => [
        // Component registrations
        'counter' => App\SparkComponents\CounterComponent::class,
    ],
    
    'settings' => [
        'auto_discover' => true,           // Enable auto-discovery
        'cache_registry' => true,         // Cache component registry
        'validate_on_register' => true,   // Validate components on registration
        'default_debounce' => 150,        // Default debounce time (ms)
        'enable_checksums' => false,      // Enable checksum validation
    ],
    
    'paths' => [
        'components' => 'src/SparkComponents',
        'templates' => 'templates/spark',
        'cache' => 'storage/cache/spark',
    ],
];
```

### Middleware Configuration

```php
// config/middleware.php
return [
    'groups' => [
        'spark' => [
            Elementary\Spark\Middleware\ValidateJsonPayload::class,
            Elementary\Spark\Middleware\ValidateSparkRequest::class,
            // Elementary\Spark\Middleware\ValidateSparkChecksum::class,
        ]
    ]
];
```

### Route Configuration

```php
// routes/spark.php
Router::middleware('spark')->group(function() {
    Router::post('/spark/message', function(Request $request, SparkManager $manager) {
        return $manager->handleRequest($request);
    });
});
```

### JavaScript Configuration

```typescript
// Global Spark configuration
window.SparkConfig = {
    debounceTime: 150,
    enableLogging: true,
    apiEndpoint: '/spark/message',
};
```

## Error Codes

### HTTP Status Codes

- `400` - Bad Request (invalid payload, missing fields)
- `403` - Forbidden (invalid checksum)
- `404` - Not Found (component not registered)
- `500` - Internal Server Error (component creation/execution failure)

### Error Response Format

```json
{
    "error": "Error message",
    "componentId": "component-id",
    "validation_errors": {
        "field": ["Error message"]
    }
}
```

### Success Response Format

```json
{
    "html": "<div>Updated HTML</div>",
    "data": {
        "property": "value"
    },
    "checksum": "new-checksum",
    "events": [
        {
            "name": "event-name",
            "data": {},
            "timestamp": 1234567890
        }
    ],
    "errors": {}
}
```

## Type Definitions

### TypeScript Interfaces

```typescript
interface ComponentData {
    id: string;
    name: string;
    data: Record<string, any>;
    checksum: string;
}

interface SparkEvent {
    name: string;
    data: any;
    timestamp: number;
}

interface ComponentElement extends HTMLElement {
    __spark?: SparkComponent;
}
```

### PHP Interfaces

```php
interface SparkComponentInterface
{
    public function render(): string;
    public function getId(): string;
    public function getState(): array;
    public function syncInput(array $updates): void;
    public function callMethod(string $method, array $params = []): void;
    public function validate(): array;
}
```

## Examples

### Basic Component Usage

```php
// Component
class CounterComponent extends SparkComponent
{
    public int $count = 0;
    
    public function increment(): void
    {
        $this->count++;
    }
    
    public function render(): string
    {
        return $this->renderTemplate('counter');
    }
}

// Template
<div>
    <p>Count: {{ $count }}</p>
    <button wire:click="increment">+</button>
</div>

// Usage
<ui-spark-counter></ui-spark-counter>
```

### Advanced Component with Validation

```php
class UserFormComponent extends SparkComponent
{
    public string $name = '';
    public string $email = '';
    
    protected array $rules = [
        'name' => 'required|min:2',
        'email' => 'required|email',
    ];
    
    public function save(): void
    {
        $errors = $this->validate();
        if (empty($errors)) {
            // Save user
            $this->emit('user-saved', ['name' => $this->name]);
        }
    }
    
    public function render(): string
    {
        return $this->renderTemplate('userform');
    }
}
```

This completes the comprehensive API reference for the Spark reactive component system.