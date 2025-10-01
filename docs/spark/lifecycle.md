# Component Lifecycle

This guide covers the complete lifecycle of Spark components, from creation to destruction, including all hooks and phases.

## Table of Contents

1. [Lifecycle Overview](#lifecycle-overview)
2. [Lifecycle Phases](#lifecycle-phases)
3. [Lifecycle Hooks](#lifecycle-hooks)
4. [State Management](#state-management)
5. [Request Lifecycle](#request-lifecycle)
6. [Memory Management](#memory-management)
7. [Best Practices](#best-practices)

## Lifecycle Overview

Spark components go through several distinct phases during their lifetime:

```
Creation → Mounting → Active → Updates → Destruction
    ↓         ↓        ↓        ↓         ↓
Initialize → mount() → Ready → Update → Cleanup
```

### Lifecycle Phases

1. **Creation Phase**: Component class is instantiated
2. **Mounting Phase**: Component is initialized with data and dependencies
3. **Active Phase**: Component is live and can handle interactions
4. **Update Phase**: Component processes updates and re-renders
5. **Destruction Phase**: Component is cleaned up and removed

## Lifecycle Phases

### 1. Creation Phase

The component is instantiated by the SparkManager:

```php
// SparkManager creates the component
$component = new CounterComponent($engine, $logger);
```

**What happens:**
- Constructor is called
- Base properties are initialized
- Template engine and logger are injected
- Component ID is generated

### 2. Mounting Phase

The component is prepared for use:

```php
class CounterComponent extends SparkComponent
{
    public int $count = 0;
    public string $message = '';
    
    protected function mount(): void
    {
        // Called once during component initialization
        $this->count = 0;
        $this->message = 'Welcome to the counter!';
        
        // Initialize any dependencies
        $this->loadInitialData();
    }
    
    private function loadInitialData(): void
    {
        // Load data from database, APIs, etc.
        $this->message = "Counter initialized at " . date('Y-m-d H:i:s');
    }
}
```

**What happens:**
- `mount()` method is called
- Initial state is set up
- Dependencies are resolved
- Component becomes ready for rendering

### 3. Active Phase

The component is live and handling user interactions:

```php
class TodoComponent extends SparkComponent
{
    public array $todos = [];
    public string $newTodo = '';
    
    // Handle user interactions
    public function addTodo(): void
    {
        if (!empty($this->newTodo)) {
            $this->todos[] = [
                'id' => uniqid(),
                'text' => $this->newTodo,
                'completed' => false,
                'created_at' => time()
            ];
            $this->newTodo = '';
            
            // Emit event to notify other components
            $this->emit('todo-added', ['count' => count($this->todos)]);
        }
    }
    
    public function toggleTodo(string $id): void
    {
        foreach ($this->todos as &$todo) {
            if ($todo['id'] === $id) {
                $todo['completed'] = !$todo['completed'];
                break;
            }
        }
    }
}
```

**What happens:**
- Component handles user interactions
- State is updated in response to actions
- Events are emitted to communicate with other components
- Template is re-rendered when state changes

### 4. Update Phase

Component processes updates from the frontend:

```php
class FormComponent extends SparkComponent
{
    public string $name = '';
    public string $email = '';
    private array $originalData = [];
    
    protected function mount(): void
    {
        $this->originalData = $this->getPublicProperties();
    }
    
    protected function updating(string $property, mixed $value): void
    {
        // Called before a property is updated
        if ($property === 'email') {
            // Validate email format
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Invalid email format');
            }
        }
    }
    
    protected function updated(string $property, mixed $value): void
    {
        // Called after a property is updated
        if ($property === 'name') {
            $this->log("Name updated to: {$value}");
        }
        
        // Check if data has changed
        if ($this->hasChanges()) {
            $this->emit('form-dirty');
        }
    }
    
    private function hasChanges(): bool
    {
        return $this->getPublicProperties() !== $this->originalData;
    }
}
```

**What happens:**
- Frontend sends property updates
- `updating()` hook is called for validation
- Property is updated
- `updated()` hook is called for side effects
- Component re-renders if needed

### 5. Destruction Phase

Component is cleaned up when no longer needed:

```php
class ResourceComponent extends SparkComponent
{
    private $fileHandle;
    private $dbConnection;
    
    protected function mount(): void
    {
        $this->fileHandle = fopen('/tmp/component.log', 'a');
        $this->dbConnection = new PDO(/* ... */);
    }
    
    public function __destruct()
    {
        // Cleanup resources
        if ($this->fileHandle) {
            fclose($this->fileHandle);
        }
        
        if ($this->dbConnection) {
            $this->dbConnection = null;
        }
    }
}
```

**What happens:**
- Component is removed from memory
- Resources are cleaned up
- File handles, database connections are closed
- Memory is freed

## Lifecycle Hooks

### mount()

Called once when the component is first created:

```php
protected function mount(): void
{
    // Initialize component state
    $this->loadUserData();
    $this->setupDefaults();
    
    // Subscribe to events
    $this->listen('user-updated', 'onUserUpdated');
}
```

**Use cases:**
- Initialize component state
- Load data from databases or APIs
- Set up event listeners
- Configure component behavior

### updating($property, $value)

Called before a property is updated:

```php
protected function updating(string $property, mixed $value): void
{
    // Validate input
    if ($property === 'quantity' && $value < 0) {
        throw new \InvalidArgumentException('Quantity cannot be negative');
    }
    
    // Transform value
    if ($property === 'email') {
        $value = strtolower(trim($value));
    }
}
```

**Use cases:**
- Validate incoming values
- Transform or sanitize input
- Enforce business rules
- Prevent invalid state changes

### updated($property, $value)

Called after a property is updated:

```php
protected function updated(string $property, mixed $value): void
{
    // React to changes
    if ($property === 'searchTerm') {
        $this->performSearch();
    }
    
    // Update related properties
    if ($property === 'quantity') {
        $this->updateTotal();
    }
    
    // Emit events
    if ($property === 'status') {
        $this->emit('status-changed', ['status' => $value]);
    }
}
```

**Use cases:**
- React to property changes
- Update calculated properties
- Trigger side effects
- Emit events to other components

### render()

Called when the component needs to generate HTML:

```php
public function render(): string
{
    // Pre-render logic
    $this->prepareData();
    
    // Render template
    $cachePath = $this->getCompiledTemplate();
    $componentData = $this->getPublicProperties();
    extract($componentData);
    ob_start();
    include $cachePath;
    $html = ob_get_clean();
    
    // Post-render logic
    $this->trackRender();
    
    return $html;
}
```

**Use cases:**
- Prepare data for rendering
- Generate HTML output
- Track rendering metrics
- Apply post-render transformations

## State Management

### State Persistence

Component state persists across requests:

```php
class StatefulComponent extends SparkComponent
{
    // These properties persist across requests
    public int $counter = 0;
    public array $items = [];
    public bool $isVisible = true;
    
    // These are recreated on each request
    protected array $rules = [];
    private $tempData;
    
    public function increment(): void
    {
        $this->counter++; // State persists
        $this->tempData = 'temp'; // Lost on next request
    }
}
```

### State Restoration

Components are recreated from state on each request:

```php
// First request: Component created with default state
$component = new CounterComponent();
$component->mount();
$component->count = 5;

// AJAX request: Component recreated from client state
$component = new CounterComponent();
$component->syncInput(['count' => 5]); // State restored
$component->increment(); // count becomes 6
```

### State Synchronization

State flows between client and server:

```php
class SyncComponent extends SparkComponent
{
    public string $text = '';
    public int $length = 0;
    
    protected function updated(string $property, mixed $value): void
    {
        if ($property === 'text') {
            // Calculate derived state
            $this->length = strlen($value);
        }
    }
}
```

## Request Lifecycle

### Complete Request Flow

1. **Client Event**: User interacts with component
2. **State Capture**: JavaScript captures current state
3. **AJAX Request**: Request sent to server with state
4. **Component Recreation**: Server recreates component from state
5. **Method Execution**: Server executes requested method
6. **Response Generation**: Server renders updated component
7. **DOM Update**: Client updates DOM with new HTML

### Request Processing

```php
// Simplified request processing
class SparkRequestProcessor
{
    public function processRequest(Request $request): Response
    {
        // 1. Extract payload
        $payload = $request->attributes->get('spark_payload');
        
        // 2. Create component
        $component = $this->createComponent($payload['componentName']);
        
        // 3. Restore state
        $component->setId($payload['componentId']);
        $component->syncInput($payload['serverMemo']['data']);
        
        // 4. Apply updates
        if (isset($payload['updates'])) {
            $component->syncInput($payload['updates']);
        }
        
        // 5. Execute method
        if (isset($payload['method'])) {
            $component->callMethod($payload['method'], $payload['params'] ?? []);
        }
        
        // 6. Generate response
        return new Response(json_encode([
            'html' => $component->toHtml(),
            'data' => $component->getPublicProperties(),
            'checksum' => $component->generateChecksum(),
            'events' => $this->getEvents()
        ]));
    }
}
```

## Memory Management

### Resource Cleanup

```php
class ResourceAwareComponent extends SparkComponent
{
    private $fileHandles = [];
    private $dbConnections = [];
    
    protected function mount(): void
    {
        $this->fileHandles[] = fopen('/tmp/log.txt', 'a');
        $this->dbConnections[] = new PDO(/* ... */);
    }
    
    public function __destruct()
    {
        // Clean up file handles
        foreach ($this->fileHandles as $handle) {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
        
        // Close database connections
        foreach ($this->dbConnections as $connection) {
            $connection = null;
        }
    }
}
```

### Memory Optimization

```php
class OptimizedComponent extends SparkComponent
{
    private static array $sharedCache = [];
    
    protected function mount(): void
    {
        // Use shared cache instead of instance variables
        if (!isset(self::$sharedCache['expensive_data'])) {
            self::$sharedCache['expensive_data'] = $this->loadExpensiveData();
        }
    }
    
    public function getExpensiveData(): array
    {
        return self::$sharedCache['expensive_data'];
    }
    
    private function loadExpensiveData(): array
    {
        // Expensive operation only done once per process
        return [];
    }
}
```

## Best Practices

### 1. Minimize mount() Logic

```php
// ❌ Bad: Heavy operations in mount()
protected function mount(): void
{
    $this->users = User::all(); // Heavy database query
    $this->processUsers(); // CPU intensive
}

// ✅ Good: Lazy loading
protected function mount(): void
{
    // Only set defaults
    $this->page = 1;
    $this->perPage = 10;
}

public function getUsers(): array
{
    if (!isset($this->users)) {
        $this->users = User::paginate($this->page, $this->perPage);
    }
    return $this->users;
}
```

### 2. Handle State Changes Carefully

```php
// ❌ Bad: Direct property manipulation
public function updateUser(): void
{
    $this->user['name'] = 'New Name'; // Can cause sync issues
}

// ✅ Good: Explicit state management
public function updateUser(string $name): void
{
    $this->user = array_merge($this->user, ['name' => $name]);
    $this->emit('user-updated', $this->user);
}
```

### 3. Use Lifecycle Hooks Appropriately

```php
class WellStructuredComponent extends SparkComponent
{
    public string $searchTerm = '';
    public array $results = [];
    
    protected function mount(): void
    {
        // Initialize only
        $this->results = [];
    }
    
    protected function updated(string $property, mixed $value): void
    {
        // React to changes
        if ($property === 'searchTerm') {
            $this->search();
        }
    }
    
    private function search(): void
    {
        // Business logic
        $this->results = $this->performSearch($this->searchTerm);
    }
}
```

### 4. Clean Up Resources

```php
class DatabaseComponent extends SparkComponent
{
    private ?PDO $connection = null;
    
    protected function getConnection(): PDO
    {
        if (!$this->connection) {
            $this->connection = new PDO(/* ... */);
        }
        return $this->connection;
    }
    
    public function __destruct()
    {
        if ($this->connection) {
            $this->connection = null;
        }
    }
}
```

### 5. Handle Errors Gracefully

```php
class RobustComponent extends SparkComponent
{
    protected function mount(): void
    {
        try {
            $this->initializeData();
        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }
    
    private function handleError(\Exception $e): void
    {
        $this->log('error', 'Component initialization failed', [
            'error' => $e->getMessage(),
            'component' => get_class($this)
        ]);
        
        // Set safe defaults
        $this->setDefaults();
    }
}
```

## Debugging Lifecycle

### Component State Debugging

```php
class DebuggableComponent extends SparkComponent
{
    protected function mount(): void
    {
        $this->debug('Component mounted', [
            'id' => $this->getId(),
            'state' => $this->getPublicProperties()
        ]);
    }
    
    protected function updated(string $property, mixed $value): void
    {
        $this->debug('Property updated', [
            'property' => $property,
            'value' => $value,
            'previous' => $this->$property ?? null
        ]);
    }
    
    private function debug(string $message, array $context = []): void
    {
        if (config('app.debug')) {
            error_log("[Spark Component] {$message}: " . json_encode($context));
        }
    }
}
```

### Lifecycle Timing

```php
class TimedComponent extends SparkComponent
{
    private float $mountTime;
    private float $renderTime;
    
    protected function mount(): void
    {
        $this->mountTime = microtime(true);
        // ... mount logic ...
        $this->log('debug', 'Mount completed', [
            'duration' => microtime(true) - $this->mountTime
        ]);
    }
    
    public function render(): string
    {
        $this->renderTime = microtime(true);
        $html = parent::render();
        $this->log('debug', 'Render completed', [
            'duration' => microtime(true) - $this->renderTime,
            'html_length' => strlen($html)
        ]);
        return $html;
    }
}
```

Understanding the component lifecycle is crucial for building efficient, maintainable Spark components. Use the lifecycle hooks appropriately and always clean up resources to ensure optimal performance.