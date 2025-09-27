# Spark - Reactive Components for Elementary Framework

Spark is a reactive component system for the Elementary Framework that enables you to build dynamic, interactive web applications with minimal JavaScript. Inspired by Laravel Livewire, Spark brings server-side reactivity to PHP applications while maintaining clean separation of concerns.

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Getting Started](#getting-started)
4. [Creating Components](#creating-components)
5. [Registering Components](#registering-components)
6. [Rendering Components](#rendering-components)
7. [API Reference](#api-reference)
8. [Examples](#examples)

## Overview

Spark allows you to create reactive components that:
- Update in real-time without page refreshes
- Maintain state between interactions
- Handle form inputs with two-way data binding
- Emit and listen to events
- Validate data on the server
- Preserve user input during updates

### Key Features

- **Server-Side Rendering**: Components are rendered on the server with full PHP capabilities
- **Client-Side Reactivity**: JavaScript handles DOM updates and user interactions
- **Two-Way Data Binding**: Automatic synchronization between frontend and backend
- **Event System**: Components can emit and listen to events
- **Form Validation**: Built-in validation with error display
- **State Management**: Component state is preserved across requests
- **Security**: Built-in CSRF protection and checksum validation
- **Middleware Support**: Extensible validation and security pipeline

## Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                    Spark Architecture                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Frontend (JavaScript)          Backend (PHP)              │
│  ┌─────────────────────┐        ┌────────────────────────┐  │
│  │   Spark.ts          │        │   SparkComponent       │  │
│  │   - DOM Hydration   │◄─────► │   - State Management   │  │
│  │   - Event Handling  │        │   - Business Logic     │  │
│  │   - AJAX Requests   │        │   - Validation         │  │
│  │   - Form Preservation│        │   - Template Rendering │  │
│  └─────────────────────┘        └────────────────────────┘  │
│           │                                │                │
│           │                                │                │
│  ┌─────────────────────┐        ┌────────────────────────┐  │
│  │   Reactivity        │        │   SparkManager         │  │
│  │   - Signals         │        │   - Component Factory  │  │
│  │   - Effects         │        │   - Request Handling   │  │
│  │   - State Tracking  │        │   - Event Management   │  │
│  └─────────────────────┘        └────────────────────────┘  │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                    HTTP Layer                               │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │ Middleware Pipeline                                     │ │
│  │ ValidateJsonPayload → ValidateSparkRequest → [Handler]  │ │
│  └─────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

### Request Lifecycle

1. **Initial Render**:
   - Component is created server-side
   - Template is compiled and rendered
   - Component state is serialized and embedded in HTML
   - JavaScript hydrates the component on page load

2. **User Interaction**:
   - User interacts with component (clicks, types, etc.)
   - JavaScript captures the event and updates local state
   - Debounced AJAX request is sent to server
   - Server recreates component with current state
   - Business logic is executed
   - Updated HTML is returned to client
   - DOM is updated while preserving form state and focus

## Getting Started

### Prerequisites

- Elementary Framework project
- PHP 8.0+
- Modern browser with JavaScript enabled

### Installation

Spark is included with the Elementary Framework. To enable it:

1. **Ensure routes are loaded**:
```php
// In your route loader, include:
require_once BASE_PATH . '/routes/spark.php';
```

2. **Include JavaScript assets**:
```typescript
// In resources/js/app.ts
import './spark';
```

3. **Build assets**:
```bash
npm run build
```

### Your First Component

Create a simple counter component:

```php
<?php
// src/SparkComponents/CounterComponent.php
namespace App\SparkComponents;

use Elementary\Spark\SparkComponent;

class CounterComponent extends SparkComponent
{
    public int $count = 0;
    public string $message = 'Click to increment!';

    public function increment(): void
    {
        $this->count++;
        $this->message = "Count is now {$this->count}!";
    }

    public function render(): string
    {
        // Use compiled template system
        $cachePath = $this->getCompiledTemplate();
        $componentData = $this->getPublicProperties();
        extract($componentData);
        ob_start();
        include $cachePath;
        return ob_get_clean();
    }
}
```

Create the template:

```html
<!-- templates/spark/counter.cigg -->
<div class="counter">
    <h3>{{ $message }}</h3>
    <p>Current count: <strong>{{ $count }}</strong></p>
    <button wire:click="increment">Increment</button>
</div>
```

Register the component:

```php
// config/spark.php
<?php
return [
    'components' => [
        'counter' => App\SparkComponents\CounterComponent::class,
    ]
];
```

Use in templates:

```html
<!-- In any template -->
<ui-spark-counter></ui-spark-counter>
```

## Next Steps

- [Creating Components](creating-components.md) - Learn to build complex components
- [Component Registration](registration.md) - Understand the registration system  
- [Rendering Guide](rendering.md) - Master component rendering
- [API Reference](api-reference.md) - Complete API documentation
- [Examples](examples.md) - Real-world examples and patterns

## Quick Links

- [Component Lifecycle](lifecycle.md)
- [Event System](events.md)
- [Form Handling](forms.md)
- [Validation](validation.md)
- [Security](security.md)
- [Performance](performance.md)
- [Troubleshooting](troubleshooting.md)