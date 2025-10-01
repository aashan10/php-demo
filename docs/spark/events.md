# Event System

This guide covers the Spark event system, enabling components to communicate with each other and respond to application-wide events.

## Table of Contents

1. [Event System Overview](#event-system-overview)
2. [Emitting Events](#emitting-events)
3. [Listening to Events](#listening-to-events)
4. [Event Types](#event-types)
5. [Global Events](#global-events)
6. [Event Data](#event-data)
7. [Best Practices](#best-practices)
8. [Advanced Patterns](#advanced-patterns)

## Event System Overview

Spark's event system allows components to:
- Communicate without direct coupling
- React to changes in other components
- Trigger application-wide notifications
- Coordinate complex workflows

### Event Flow

```
Component A → emit('event-name', data) → SparkManager → Component B (listening)
                                               ↓
                                        Frontend (JavaScript)
```

### Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Event System Architecture                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Component Level Events    │    Global Events               │
│  ┌───────────────────────┐ │  ┌─────────────────────────────┐ │
│  │ Component A           │ │  │ SparkManager                │ │
│  │ - emit('event')       │─┼─►│ - addEvent()                │ │
│  │ - listen('event')     │ │  │ - getEvents()               │ │
│  └───────────────────────┘ │  │ - clearEvents()             │ │
│                            │  └─────────────────────────────┘ │
│  ┌───────────────────────┐ │              │                  │
│  │ Component B           │ │              ▼                  │
│  │ - onEventReceived()   │◄┼─┐  ┌─────────────────────────────┐ │
│  └───────────────────────┘ │ │  │ Frontend (JavaScript)       │ │
│                            │ │  │ - Custom Events             │ │
│  ┌───────────────────────┐ │ │  │ - Event Listeners           │ │
│  │ Component C           │ │ │  │ - DOM Updates               │ │
│  │ - handleGlobalEvent() │◄┼─┘  └─────────────────────────────┘ │
│  └───────────────────────┘ │                                  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

## Emitting Events

### Basic Event Emission

Use the `emit()` method to send events to other components:

```php
class CounterComponent extends SparkComponent
{
    public int $count = 0;
    
    public function increment(): void
    {
        $this->count++;
        
        // Emit event when count reaches milestone
        if ($this->count % 10 === 0) {
            $this->emit('counter-milestone', [
                'count' => $this->count,
                'milestone' => $this->count
            ]);
        }
        
        // Emit general update event
        $this->emit('counter-updated', $this->count);
    }
    
    public function reset(): void
    {
        $this->count = 0;
        $this->emit('counter-reset');
    }
}
```

### Event with Complex Data

```php
class ShoppingCartComponent extends SparkComponent
{
    public array $items = [];
    public float $total = 0.0;
    
    public function addItem(string $productId, int $quantity = 1): void
    {
        $product = $this->getProduct($productId);
        
        $this->items[] = [
            'id' => $productId,
            'name' => $product->name,
            'price' => $product->price,
            'quantity' => $quantity,
            'subtotal' => $product->price * $quantity
        ];
        
        $this->updateTotal();
        
        // Emit detailed event
        $this->emit('item-added-to-cart', [
            'product' => [
                'id' => $productId,
                'name' => $product->name,
                'price' => $product->price
            ],
            'quantity' => $quantity,
            'cart' => [
                'total_items' => count($this->items),
                'total_amount' => $this->total
            ],
            'timestamp' => time()
        ]);
    }
}
```

### Conditional Event Emission

```php
class UserFormComponent extends SparkComponent
{
    public string $name = '';
    public string $email = '';
    public string $status = 'draft';
    
    public function save(): void
    {
        $errors = $this->validate();
        
        if (empty($errors)) {
            $user = $this->saveUser();
            $this->status = 'saved';
            
            // Emit success event
            $this->emit('user-saved', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'success' => true
            ]);
            
            // Emit specific events based on conditions
            if ($this->isNewUser($user)) {
                $this->emit('new-user-registered', $user->toArray());
            } else {
                $this->emit('user-updated', [
                    'user_id' => $user->id,
                    'changes' => $this->getChanges()
                ]);
            }
        } else {
            $this->status = 'error';
            
            // Emit error event
            $this->emit('user-save-failed', [
                'errors' => $errors,
                'form_data' => $this->getPublicProperties()
            ]);
        }
    }
}
```

## Listening to Events

### Component Event Listeners

Components can listen to events using the `$listeners` property:

```php
class NotificationComponent extends SparkComponent
{
    public array $notifications = [];
    
    protected array $listeners = [
        'user-saved' => 'onUserSaved',
        'user-save-failed' => 'onUserSaveFailed',
        'counter-milestone' => 'onCounterMilestone',
        'item-added-to-cart' => 'onItemAddedToCart'
    ];
    
    public function onUserSaved(array $data): void
    {
        $this->addNotification(
            'success',
            "User {$data['user_name']} saved successfully!",
            $data
        );
    }
    
    public function onUserSaveFailed(array $data): void
    {
        $errorCount = count($data['errors']);
        $this->addNotification(
            'error',
            "Failed to save user. {$errorCount} errors found.",
            $data
        );
    }
    
    public function onCounterMilestone(array $data): void
    {
        $this->addNotification(
            'info',
            "Counter reached milestone: {$data['milestone']}!",
            $data
        );
    }
    
    public function onItemAddedToCart(array $data): void
    {
        $this->addNotification(
            'success',
            "Added {$data['product']['name']} to cart",
            $data
        );
    }
    
    private function addNotification(string $type, string $message, array $data = []): void
    {
        $this->notifications[] = [
            'id' => uniqid(),
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'timestamp' => time(),
            'read' => false
        ];
    }
}
```

### Dynamic Event Listeners

```php
class DynamicListenerComponent extends SparkComponent
{
    public string $listenTo = '';
    public array $receivedEvents = [];
    
    protected function mount(): void
    {
        // Set up dynamic listeners based on component state
        $this->setupEventListeners();
    }
    
    protected function updated(string $property, mixed $value): void
    {
        if ($property === 'listenTo') {
            $this->setupEventListeners();
        }
    }
    
    private function setupEventListeners(): void
    {
        // Clear existing listeners
        $this->listeners = [];
        
        if (!empty($this->listenTo)) {
            $this->listeners[$this->listenTo] = 'onEventReceived';
        }
    }
    
    public function onEventReceived(array $data): void
    {
        $this->receivedEvents[] = [
            'event' => $this->listenTo,
            'data' => $data,
            'timestamp' => time()
        ];
    }
}
```

### Multiple Event Handlers

```php
class MultiHandlerComponent extends SparkComponent
{
    public int $userCount = 0;
    public int $saveCount = 0;
    public int $errorCount = 0;
    
    protected array $listeners = [
        'user-saved' => ['incrementUserCount', 'incrementSaveCount', 'logUserActivity'],
        'user-save-failed' => ['incrementErrorCount', 'logError'],
        'user-updated' => ['incrementSaveCount']
    ];
    
    public function incrementUserCount(array $data): void
    {
        $this->userCount++;
    }
    
    public function incrementSaveCount(array $data): void
    {
        $this->saveCount++;
    }
    
    public function incrementErrorCount(array $data): void
    {
        $this->errorCount++;
    }
    
    public function logUserActivity(array $data): void
    {
        $this->log('info', 'User activity', $data);
    }
    
    public function logError(array $data): void
    {
        $this->log('error', 'User save failed', $data);
    }
}
```

## Event Types

### Component Events

Events emitted by specific components:

```php
class TodoComponent extends SparkComponent
{
    public function addTodo(string $text): void
    {
        $todo = $this->createTodo($text);
        
        // Component-specific events
        $this->emit('todo-added', $todo);
        $this->emit('todo-list-updated', $this->todos);
    }
    
    public function completeTodo(string $id): void
    {
        $todo = $this->findTodo($id);
        $todo['completed'] = true;
        
        // Emit completion event
        $this->emit('todo-completed', $todo);
        
        // Check if all todos are completed
        if ($this->allTodosCompleted()) {
            $this->emit('all-todos-completed', [
                'total' => count($this->todos),
                'completed_at' => time()
            ]);
        }
    }
}
```

### System Events

Events related to system operations:

```php
class SystemEventComponent extends SparkComponent
{
    public function performBackup(): void
    {
        try {
            $this->runBackup();
            
            $this->emit('system-backup-completed', [
                'timestamp' => time(),
                'status' => 'success'
            ]);
        } catch (\Exception $e) {
            $this->emit('system-backup-failed', [
                'error' => $e->getMessage(),
                'timestamp' => time()
            ]);
        }
    }
    
    public function clearCache(): void
    {
        $this->cache->clear();
        
        $this->emit('system-cache-cleared', [
            'timestamp' => time(),
            'cleared_by' => $this->getCurrentUser()->id
        ]);
    }
}
```

### User Events

Events related to user actions:

```php
class UserActivityComponent extends SparkComponent
{
    public function login(string $username): void
    {
        $user = $this->authenticateUser($username);
        
        if ($user) {
            $this->emit('user-logged-in', [
                'user_id' => $user->id,
                'username' => $user->username,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'timestamp' => time()
            ]);
        } else {
            $this->emit('user-login-failed', [
                'username' => $username,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'timestamp' => time()
            ]);
        }
    }
    
    public function logout(): void
    {
        $user = $this->getCurrentUser();
        $this->performLogout();
        
        $this->emit('user-logged-out', [
            'user_id' => $user->id,
            'session_duration' => time() - $user->login_time,
            'timestamp' => time()
        ]);
    }
}
```

## Global Events

### JavaScript Event Handling

Events are automatically sent to the frontend as custom DOM events:

```javascript
// Listen for Spark events in JavaScript
document.addEventListener('spark:user-saved', function(event) {
    const userData = event.detail;
    console.log('User saved:', userData);
    
    // Show toast notification
    showToast('success', `User ${userData.user_name} saved!`);
});

document.addEventListener('spark:counter-milestone', function(event) {
    const { count, milestone } = event.detail;
    
    // Trigger confetti animation
    if (milestone >= 100) {
        triggerConfetti();
    }
    
    // Update page title
    document.title = `Counter: ${count}`;
});

// Listen for cart events
document.addEventListener('spark:item-added-to-cart', function(event) {
    const cartData = event.detail;
    
    // Update cart icon
    updateCartIcon(cartData.cart.total_items);
    
    // Show mini cart preview
    showMiniCart(cartData);
});
```

### Global Event Bus

Create a global event bus for cross-component communication:

```php
class EventBusComponent extends SparkComponent
{
    public array $eventLog = [];
    public int $eventCount = 0;
    
    protected array $listeners = [
        '*' => 'logAllEvents' // Listen to all events
    ];
    
    public function logAllEvents(array $data, string $eventName): void
    {
        $this->eventLog[] = [
            'event' => $eventName,
            'data' => $data,
            'timestamp' => time(),
            'component' => $this->getId()
        ];
        
        $this->eventCount++;
        
        // Keep only last 100 events
        if (count($this->eventLog) > 100) {
            $this->eventLog = array_slice($this->eventLog, -100);
        }
    }
    
    public function getEventsByType(string $type): array
    {
        return array_filter($this->eventLog, function($event) use ($type) {
            return strpos($event['event'], $type) === 0;
        });
    }
}
```

## Event Data

### Structured Event Data

Use consistent data structures for events:

```php
class StandardizedEventComponent extends SparkComponent
{
    public function createOrder(array $orderData): void
    {
        $order = $this->processOrder($orderData);
        
        // Standardized event structure
        $this->emit('order-created', [
            'entity' => [
                'type' => 'order',
                'id' => $order->id,
                'attributes' => $order->toArray()
            ],
            'context' => [
                'user_id' => $this->getCurrentUser()->id,
                'session_id' => session_id(),
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ],
            'metadata' => [
                'timestamp' => time(),
                'version' => '1.0',
                'source' => 'order-component'
            ]
        ]);
    }
}
```

### Event Payload Validation

```php
class ValidatedEventComponent extends SparkComponent
{
    private array $eventSchemas = [
        'user-created' => [
            'required' => ['user_id', 'username', 'email'],
            'optional' => ['profile_data', 'preferences']
        ],
        'order-placed' => [
            'required' => ['order_id', 'total_amount', 'customer_id'],
            'optional' => ['discount', 'shipping_info']
        ]
    ];
    
    protected function emit(string $event, mixed $data = null): void
    {
        // Validate event data before emitting
        if ($this->validateEventData($event, $data)) {
            parent::emit($event, $data);
        } else {
            $this->log('warning', 'Invalid event data', [
                'event' => $event,
                'data' => $data
            ]);
        }
    }
    
    private function validateEventData(string $event, mixed $data): bool
    {
        if (!isset($this->eventSchemas[$event])) {
            return true; // No schema defined, allow
        }
        
        $schema = $this->eventSchemas[$event];
        
        // Check required fields
        foreach ($schema['required'] as $field) {
            if (!isset($data[$field])) {
                return false;
            }
        }
        
        return true;
    }
}
```

## Best Practices

### 1. Use Descriptive Event Names

```php
// ❌ Bad: Generic names
$this->emit('update');
$this->emit('change');
$this->emit('done');

// ✅ Good: Descriptive names
$this->emit('user-profile-updated');
$this->emit('password-changed');
$this->emit('email-verification-completed');
```

### 2. Include Relevant Context

```php
// ❌ Bad: Minimal data
$this->emit('user-saved', $user->id);

// ✅ Good: Rich context
$this->emit('user-saved', [
    'user_id' => $user->id,
    'username' => $user->username,
    'changes' => $this->getChangedFields(),
    'previous_values' => $this->getPreviousValues(),
    'timestamp' => time(),
    'updated_by' => $this->getCurrentUser()->id
]);
```

### 3. Handle Events Gracefully

```php
class RobustEventListener extends SparkComponent
{
    protected array $listeners = [
        'user-updated' => 'onUserUpdated'
    ];
    
    public function onUserUpdated(array $data): void
    {
        try {
            $this->processUserUpdate($data);
        } catch (\Exception $e) {
            $this->log('error', 'Failed to process user update event', [
                'error' => $e->getMessage(),
                'event_data' => $data
            ]);
            
            // Emit error event for monitoring
            $this->emit('event-processing-failed', [
                'original_event' => 'user-updated',
                'error' => $e->getMessage(),
                'component' => get_class($this)
            ]);
        }
    }
}
```

### 4. Avoid Event Loops

```php
class EventLoopPreventionComponent extends SparkComponent
{
    private array $processingEvents = [];
    
    protected array $listeners = [
        'data-updated' => 'onDataUpdated'
    ];
    
    public function updateData(array $newData): void
    {
        $this->data = $newData;
        
        // Only emit if not already processing
        if (!in_array('data-updated', $this->processingEvents)) {
            $this->emit('data-updated', $newData);
        }
    }
    
    public function onDataUpdated(array $data): void
    {
        // Prevent infinite loops
        if (in_array('data-updated', $this->processingEvents)) {
            return;
        }
        
        $this->processingEvents[] = 'data-updated';
        
        try {
            $this->synchronizeRelatedData($data);
        } finally {
            // Always remove from processing list
            $this->processingEvents = array_diff($this->processingEvents, ['data-updated']);
        }
    }
}
```

## Advanced Patterns

### Event Aggregation

```php
class EventAggregatorComponent extends SparkComponent
{
    public array $aggregatedStats = [];
    
    protected array $listeners = [
        'user-login' => 'trackLogin',
        'user-logout' => 'trackLogout',
        'page-view' => 'trackPageView',
        'button-click' => 'trackClick'
    ];
    
    public function trackLogin(array $data): void
    {
        $this->incrementStat('logins', $data['timestamp']);
    }
    
    public function trackLogout(array $data): void
    {
        $this->incrementStat('logouts', $data['timestamp']);
    }
    
    public function trackPageView(array $data): void
    {
        $this->incrementStat('page_views', $data['timestamp']);
        $this->trackPageSpecificStats($data['page']);
    }
    
    public function trackClick(array $data): void
    {
        $this->incrementStat('clicks', $data['timestamp']);
    }
    
    private function incrementStat(string $type, int $timestamp): void
    {
        $hour = date('Y-m-d H', $timestamp);
        
        if (!isset($this->aggregatedStats[$hour])) {
            $this->aggregatedStats[$hour] = [];
        }
        
        if (!isset($this->aggregatedStats[$hour][$type])) {
            $this->aggregatedStats[$hour][$type] = 0;
        }
        
        $this->aggregatedStats[$hour][$type]++;
    }
}
```

### Event-Driven Workflows

```php
class WorkflowComponent extends SparkComponent
{
    public string $currentStep = 'start';
    public array $completedSteps = [];
    
    protected array $listeners = [
        'step-completed' => 'onStepCompleted',
        'workflow-reset' => 'onWorkflowReset'
    ];
    
    protected array $workflow = [
        'start' => 'collect_info',
        'collect_info' => 'validate_info',
        'validate_info' => 'process_payment',
        'process_payment' => 'send_confirmation',
        'send_confirmation' => 'complete'
    ];
    
    public function completeCurrentStep(): void
    {
        $this->completedSteps[] = $this->currentStep;
        
        $this->emit('step-completed', [
            'completed_step' => $this->currentStep,
            'next_step' => $this->getNextStep(),
            'progress' => $this->getProgress()
        ]);
    }
    
    public function onStepCompleted(array $data): void
    {
        $this->currentStep = $data['next_step'] ?? 'complete';
        
        if ($this->currentStep === 'complete') {
            $this->emit('workflow-completed', [
                'completed_steps' => $this->completedSteps,
                'total_time' => $this->getTotalTime()
            ]);
        }
    }
    
    private function getNextStep(): string
    {
        return $this->workflow[$this->currentStep] ?? 'complete';
    }
}
```

The event system is powerful for building loosely coupled, reactive applications. Use events to coordinate between components while maintaining clean separation of concerns.