# Creating Spark Components

This guide covers everything you need to know about creating Spark components, from basic concepts to advanced patterns.

## Table of Contents

1. [Component Basics](#component-basics)
2. [Component Structure](#component-structure)
3. [Properties and State](#properties-and-state)
4. [Methods and Actions](#methods-and-actions)
5. [Template System](#template-system)
6. [Validation](#validation)
7. [Events](#events)
8. [Advanced Patterns](#advanced-patterns)

## Component Basics

### What is a Spark Component?

A Spark component is a PHP class that extends `SparkComponent` and represents a reactive piece of your user interface. Each component:

- Manages its own state
- Handles user interactions
- Renders HTML templates
- Communicates with other components via events

### Basic Component Structure

```php
<?php
namespace App\SparkComponents;

use Elementary\Spark\SparkComponent;

class MyComponent extends SparkComponent
{
    // Public properties (synced with frontend)
    public string $message = 'Hello World';
    public int $count = 0;
    
    // Protected properties (server-only)
    protected array $rules = [];
    protected array $listeners = [];
    
    // Component lifecycle
    protected function mount(): void
    {
        // Called when component is first created
    }
    
    // Public methods (callable from frontend)
    public function doSomething(): void
    {
        // Handle user actions
    }
    
    // Template rendering
    public function render(): string
    {
        // Return component HTML
    }
}
```

## Component Structure

### Directory Structure

```
src/SparkComponents/
├── CounterComponent.php
├── TodoListComponent.php
├── Forms/
│   ├── ContactFormComponent.php
│   └── LoginFormComponent.php
└── Widgets/
    ├── WeatherWidgetComponent.php
    └── ChatWidgetComponent.php

templates/spark/
├── counter.cigg
├── todolist.cigg
├── forms/
│   ├── contactform.cigg
│   └── loginform.cigg
└── widgets/
    ├── weatherwidget.cigg
    └── chatwidget.cigg
```

### Naming Conventions

- **Class Names**: `PascalCase` ending with `Component`
- **Template Files**: `lowercase` matching the component name (minus `Component`)
- **Template Directory**: `templates/spark/`
- **Namespace**: `App\SparkComponents\`

### Component Discovery

Components are automatically discovered by converting the class name:

```php
// Class: App\SparkComponents\TodoListComponent
// Template: templates/spark/todolist.cigg
// Usage: <ui-spark-todolist></ui-spark-todolist>

// Class: App\SparkComponents\Forms\ContactFormComponent  
// Template: templates/spark/forms/contactform.cigg
// Usage: <ui-spark-forms-contactform></ui-spark-forms-contactform>
```

## Properties and State

### Public Properties

Public properties are automatically synced between server and client:

```php
class UserProfileComponent extends SparkComponent
{
    // These are synced with the frontend
    public string $name = '';
    public string $email = '';
    public int $age = 0;
    public bool $isActive = true;
    public array $interests = [];
    
    // Complex objects work too
    public ?User $user = null;
}
```

### Property Types

Spark supports various property types:

```php
class ExampleComponent extends SparkComponent
{
    // Primitives
    public string $text = '';
    public int $number = 0;
    public float $decimal = 0.0;
    public bool $flag = false;
    
    // Arrays
    public array $items = [];
    public array $config = ['key' => 'value'];
    
    // Objects (must be serializable)
    public ?DateTime $date = null;
    public ?stdClass $data = null;
    
    // Collections
    public array $users = [];
}
```

### Protected/Private Properties

Properties that should not be synced with the client:

```php
class SecureComponent extends SparkComponent
{
    public string $publicData = '';
    
    // These stay on the server
    protected string $secretKey = '';
    private array $internalState = [];
    protected PDO $database;
}
```

### Dynamic Properties

You can also use dynamic properties via the `$data` array:

```php
class DynamicComponent extends SparkComponent
{
    protected function mount(): void
    {
        // Set dynamic properties
        $this->data['dynamicValue'] = 'Hello';
        $this->data['computedProperty'] = $this->calculateSomething();
    }
    
    public function updateDynamic(): void
    {
        $this->data['dynamicValue'] = 'Updated!';
    }
}
```

## Methods and Actions

### Public Methods

Public methods can be called from the frontend:

```php
class TodoComponent extends SparkComponent
{
    public array $todos = [];
    public string $newTodo = '';
    
    public function addTodo(): void
    {
        if (trim($this->newTodo) !== '') {
            $this->todos[] = [
                'id' => uniqid(),
                'text' => $this->newTodo,
                'completed' => false
            ];
            $this->newTodo = '';
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
    
    public function removeTodo(string $id): void
    {
        $this->todos = array_filter(
            $this->todos, 
            fn($todo) => $todo['id'] !== $id
        );
    }
}
```

### Method Parameters

Methods can accept parameters from the frontend:

```php
class CalculatorComponent extends SparkComponent
{
    public float $result = 0;
    
    public function add(float $a, float $b): void
    {
        $this->result = $a + $b;
    }
    
    public function setResult(float $value): void
    {
        $this->result = $value;
    }
    
    // Complex parameters
    public function processData(array $data, string $operation): void
    {
        match($operation) {
            'sum' => $this->result = array_sum($data),
            'avg' => $this->result = array_sum($data) / count($data),
            'max' => $this->result = max($data),
            'min' => $this->result = min($data),
        };
    }
}
```

### Protected/Private Methods

Methods that should not be callable from the frontend:

```php
class DataComponent extends SparkComponent
{
    public array $data = [];
    
    public function loadData(): void
    {
        $this->data = $this->fetchFromDatabase();
    }
    
    // Not callable from frontend
    protected function fetchFromDatabase(): array
    {
        // Database logic here
        return [];
    }
    
    private function validateData(array $data): bool
    {
        // Validation logic
        return true;
    }
}
```

## Template System

### Basic Template

Templates use the Cigg templating engine:

```html
<!-- templates/spark/profile.cigg -->
<div class="profile-card">
    <h2>{{ $name }}</h2>
    <p>Email: {{ $email }}</p>
    <p>Age: {{ $age }}</p>
    
    @if($isActive)
        <span class="badge-active">Active</span>
    @else
        <span class="badge-inactive">Inactive</span>
    @endif
    
    <button spark:click="toggleActive">
        {{ $isActive ? 'Deactivate' : 'Activate' }}
    </button>
</div>
```

### Spark Directives

Spark provides several directives for interactivity:

#### `spark:click`
```html
<button spark:click="methodName">Click Me</button>
<button spark:click="methodWithParams('param1', 123)">With Params</button>
```

#### `spark:model`
```html
<!-- Text input -->
<input type="text" spark:model="name" placeholder="Enter name">

<!-- Number input -->
<input type="number" spark:model="age">

<!-- Checkbox -->
<input type="checkbox" spark:model="isActive"> Active

<!-- Select -->
<select spark:model="selectedOption">
    <option value="">Choose...</option>
    <option value="option1">Option 1</option>
    <option value="option2">Option 2</option>
</select>

<!-- Textarea -->
<textarea spark:model="description"></textarea>
```

#### `spark:submit`
```html
<form spark:submit="submitForm">
    <input type="text" spark:model="formData.name">
    <input type="email" spark:model="formData.email">
    <button type="submit">Submit</button>
</form>
```

#### Other Spark Events
```html
<input spark:blur="validateField" spark:model="email">
<input spark:keydown="handleKeypress" spark:model="search">
<select spark:change="handleChange" spark:model="category">
```

#### `spark:on-*` Event Directives

The new `spark:on-*` directive system provides flexible event handling for any DOM event:

```html
<!-- Re-render component on blur -->
<input type="text" spark:on-blur="render" placeholder="Re-renders on blur">

<!-- Call method on focus -->
<input type="email" spark:on-focus="validateEmail">

<!-- Call method on any DOM event -->
<button spark:on-mouseenter="onHover">Hover Me</button>
<input spark:on-keydown="handleKeypress" type="text">
<select spark:on-change="updateSelection">
    <option value="option1">Option 1</option>
    <option value="option2">Option 2</option>
</select>

<!-- Multiple event handlers -->
<input type="text" 
       spark:model="searchTerm"
       spark:on-blur="render"
       spark:on-keydown="handleKeypress">
```

**Supported Actions:**
- `"render"` - Triggers component re-render via server sync
- `"methodName"` - Calls the specified component method

**Supported Events:**
Any valid DOM event: `blur`, `focus`, `click`, `change`, `keydown`, `keyup`, `mouseenter`, `mouseleave`, `submit`, etc.

**Example Component:**
```php
class SearchComponent extends SparkComponent
{
    public string $searchTerm = '';
    public array $results = [];
    
    public function handleKeypress(Request $request): void
    {
        // Handle special key combinations
        $key = $request->get('key');
        if ($key === 'Enter') {
            $this->search();
        }
    }
    
    public function validateEmail(): void
    {
        // Validate email on focus
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $this->addError('email', 'Invalid email format');
        }
    }
    
    public function search(): void
    {
        // Perform search when term changes (triggered by render)
        $this->results = $this->performSearch($this->searchTerm);
    }
}
```

### Conditional Rendering

```html
@if($items->count() > 0)
    <ul>
        @foreach($items as $item)
            <li>{{ $item->name }}</li>
        @endforeach
    </ul>
@else
    <p>No items found.</p>
@endif

@unless($user->isGuest())
    <div class="user-menu">Welcome, {{ $user->name }}!</div>
@endunless
```

### Loops and Iteration

```html
@foreach($todos as $index => $todo)
    <div class="todo-item {{ $todo['completed'] ? 'completed' : '' }}">
        <input type="checkbox" 
               spark:click="toggleTodo('{{ $todo['id'] }}')"
               {{ $todo['completed'] ? 'checked' : '' }}>
        <span>{{ $todo['text'] }}</span>
        <button spark:click="removeTodo('{{ $todo['id'] }}')">×</button>
    </div>
@endforeach

@for($i = 1; $i <= 5; $i++)
    <div>Item {{ $i }}</div>
@endfor
```

### Component Access

Access the component instance in templates:

```html
<!-- templates/spark/advanced.cigg -->
<div class="component-info">
    <p>Component ID: {{ $__spark->getId() }}</p>
    <p>Component Class: {{ get_class($__spark) }}</p>
    
    @if($__spark->hasErrors())
        <div class="errors">
            <!-- Display validation errors -->
        </div>
    @endif
</div>
```

## Validation

### Validation Rules

Define validation rules for component properties:

```php
class UserFormComponent extends SparkComponent
{
    public string $name = '';
    public string $email = '';
    public int $age = 0;
    public string $password = '';
    
    protected array $rules = [
        'name' => 'required|min:2|max:50',
        'email' => 'required|email',
        'age' => 'required|min:13|max:120',
        'password' => 'required|min:8',
    ];
    
    public function save(): void
    {
        $errors = $this->validate();
        
        if (empty($errors)) {
            // Save the user
            $this->emit('user-saved', ['name' => $this->name]);
        }
        // Errors are automatically displayed in the template
    }
}
```

### Built-in Validation Rules

```php
protected array $rules = [
    'field1' => 'required',                    // Must have a value
    'field2' => 'min:5',                      // Minimum length
    'field3' => 'max:100',                    // Maximum length
    'field4' => 'email',                      // Valid email format
    'field5' => 'required|email|max:255',     // Multiple rules
];
```

### Custom Validation

```php
class CustomValidationComponent extends SparkComponent
{
    public string $username = '';
    
    protected array $rules = [
        'username' => 'required|min:3',
    ];
    
    public function validate(): array
    {
        $errors = parent::validate();
        
        // Custom validation
        if ($this->isUsernameTaken($this->username)) {
            $errors['username'][] = 'This username is already taken.';
        }
        
        return $errors;
    }
    
    private function isUsernameTaken(string $username): bool
    {
        // Check database
        return false;
    }
}
```

### Displaying Validation Errors

```html
<!-- templates/spark/userform.cigg -->
<form spark:submit="save">
    <div>
        <label>Name:</label>
        <input type="text" spark:model="name">
        <!-- Errors are automatically displayed -->
    </div>
    
    <div>
        <label>Email:</label>
        <input type="email" spark:model="email">
    </div>
    
    <button type="submit">Save</button>
</form>
```

## Events

### Emitting Events

Components can emit events to communicate with other components:

```php
class OrderComponent extends SparkComponent
{
    public function placeOrder(): void
    {
        // Process order
        $orderId = $this->processOrder();
        
        // Emit event to notify other components
        $this->emit('order-placed', [
            'orderId' => $orderId,
            'total' => $this->total,
            'customer' => $this->customer
        ]);
    }
    
    public function updateQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
        $this->emit('quantity-changed', ['quantity' => $quantity]);
    }
}
```

### Listening to Events

```php
class NotificationComponent extends SparkComponent
{
    public array $notifications = [];
    
    protected array $listeners = [
        'order-placed' => 'handleOrderPlaced',
        'user-logged-in' => 'handleUserLogin',
        'cart-updated' => 'handleCartUpdate',
    ];
    
    public function handleOrderPlaced(array $data): void
    {
        $this->notifications[] = [
            'type' => 'success',
            'message' => "Order #{$data['orderId']} placed successfully!"
        ];
    }
    
    public function handleUserLogin(array $data): void
    {
        $this->notifications[] = [
            'type' => 'info',
            'message' => "Welcome back, {$data['username']}!"
        ];
    }
}
```

### JavaScript Event Handling

Listen to events in JavaScript:

```html
<script>
document.addEventListener('spark:order-placed', function(e) {
    console.log('Order placed:', e.detail);
    // Show toast notification
    showToast('Order placed successfully!');
});

document.addEventListener('spark:quantity-changed', function(e) {
    console.log('Quantity changed:', e.detail.quantity);
    // Update cart icon badge
    updateCartBadge(e.detail.quantity);
});
</script>
```

## Advanced Patterns

### Component Composition

```php
class DashboardComponent extends SparkComponent
{
    public function render(): string
    {
        // Render multiple child components
        return $this->renderTemplate('dashboard', [
            'widgets' => [
                'stats' => $this->renderWidget('stats'),
                'chart' => $this->renderWidget('chart'),
                'notifications' => $this->renderWidget('notifications'),
            ]
        ]);
    }
    
    private function renderWidget(string $type): string
    {
        $componentClass = "App\\SparkComponents\\Widgets\\" . 
                         ucfirst($type) . "WidgetComponent";
        
        $manager = SparkManager::getInstance();
        return $manager->renderComponent($componentClass);
    }
}
```

### Conditional Component Loading

```php
class ConditionalComponent extends SparkComponent
{
    public bool $showAdvanced = false;
    public string $userRole = 'guest';
    
    public function toggleAdvanced(): void
    {
        $this->showAdvanced = !$this->showAdvanced;
    }
    
    public function render(): string
    {
        $template = match($this->userRole) {
            'admin' => 'admin-dashboard',
            'user' => 'user-dashboard',
            default => 'guest-dashboard'
        };
        
        return $this->renderTemplate($template);
    }
}
```

### Data Loading Patterns

```php
class DataLoaderComponent extends SparkComponent
{
    public array $items = [];
    public bool $loading = false;
    public string $error = '';
    
    protected function mount(): void
    {
        $this->loadData();
    }
    
    public function refresh(): void
    {
        $this->loadData();
    }
    
    private function loadData(): void
    {
        $this->loading = true;
        $this->error = '';
        
        try {
            $this->items = $this->fetchFromApi();
        } catch (Exception $e) {
            $this->error = $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }
    
    private function fetchFromApi(): array
    {
        // API call logic
        return [];
    }
}
```

### Form Handling Patterns

```php
class AdvancedFormComponent extends SparkComponent
{
    public array $formData = [];
    public array $errors = [];
    public bool $submitting = false;
    public bool $submitted = false;
    
    protected array $rules = [
        'formData.name' => 'required|min:2',
        'formData.email' => 'required|email',
    ];
    
    protected function mount(): void
    {
        $this->formData = [
            'name' => '',
            'email' => '',
            'message' => '',
        ];
    }
    
    public function submit(): void
    {
        $this->submitting = true;
        $this->errors = $this->validate();
        
        if (empty($this->errors)) {
            try {
                $this->processForm();
                $this->submitted = true;
                $this->emit('form-submitted', $this->formData);
            } catch (Exception $e) {
                $this->errors['general'] = [$e->getMessage()];
            }
        }
        
        $this->submitting = false;
    }
    
    public function reset(): void
    {
        $this->formData = [];
        $this->errors = [];
        $this->submitted = false;
    }
    
    private function processForm(): void
    {
        // Process the form data
    }
}
```

## Best Practices

### 1. Keep Components Focused
Each component should have a single responsibility.

### 2. Use Proper Naming
- Clear, descriptive component names
- Consistent method naming
- Meaningful property names

### 3. Handle Errors Gracefully
Always validate user input and handle exceptions.

### 4. Optimize Performance
- Minimize the amount of data synced
- Use protected properties for server-only data
- Implement proper caching where needed

### 5. Security Considerations
- Validate all user input
- Never expose sensitive data in public properties
- Use proper authorization checks

### 6. Testing
Write tests for your components to ensure reliability.

## Next Steps

- [Component Registration](registration.md) - Learn how to register components
- [Rendering Guide](rendering.md) - Master component rendering
- [API Reference](api-reference.md) - Complete API documentation
- [Examples](examples.md) - Real-world examples