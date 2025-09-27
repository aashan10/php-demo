# Component Rendering

This guide covers everything about rendering Spark components, from basic usage in templates to advanced rendering patterns.

## Table of Contents

1. [Rendering Overview](#rendering-overview)
2. [Template Syntax](#template-syntax)
3. [Component Parameters](#component-parameters)
4. [Rendering Lifecycle](#rendering-lifecycle)
5. [Template System](#template-system)
6. [Advanced Rendering](#advanced-rendering)
7. [Performance Optimization](#performance-optimization)
8. [Troubleshooting](#troubleshooting)

## Rendering Overview

Spark components can be rendered in several ways:

1. **Template Components**: Using `<ui-spark-*>` syntax in Cigg templates
2. **Directive Rendering**: Using `@spark()` directive
3. **Programmatic Rendering**: Direct PHP rendering
4. **AJAX Rendering**: Dynamic updates via JavaScript

### Rendering Flow

```
Template Request → Component Discovery → Component Creation → 
Template Compilation → HTML Generation → Client Hydration
```

## Template Syntax

### Basic Component Rendering

The primary way to render Spark components is using the component syntax in templates:

```html
<!-- Basic component -->
<ui-spark-counter></ui-spark-counter>

<!-- Component with parameters -->
<ui-spark-user-profile user-id="123" theme="dark"></ui-spark-user-profile>

<!-- Self-closing syntax -->
<ui-spark-notification message="Hello World" />

<!-- Component with content/slot -->
<ui-spark-modal title="Confirmation">
    <p>Are you sure you want to delete this item?</p>
</ui-spark-modal>
```

### Component Name Resolution

Component names in templates are automatically resolved to registry keys:

```html
<!-- Template Usage → Registry Key → Component Class -->
<ui-spark-counter> → 'counter' → CounterComponent
<ui-spark-todo-list> → 'todo-list' → TodoListComponent
<ui-spark-forms-contact> → 'forms.contact' → Forms\ContactFormComponent
<ui-spark-widgets-weather> → 'widgets.weather' → Widgets\WeatherWidgetComponent
```

### Nested Components

Components can contain other components:

```html
<!-- Parent component template -->
<div class="dashboard">
    <ui-spark-header user-id="{{ $userId }}"></ui-spark-header>
    
    <div class="content">
        <ui-spark-sidebar></ui-spark-sidebar>
        <ui-spark-main-content>
            <ui-spark-widgets-stats></ui-spark-widgets-stats>
            <ui-spark-widgets-chart data="{{ json_encode($chartData) }}"></ui-spark-widgets-chart>
        </ui-spark-main-content>
    </div>
    
    <ui-spark-footer></ui-spark-footer>
</div>
```

## Component Parameters

### Passing Parameters

Parameters are passed as HTML attributes and automatically converted to component properties:

```html
<!-- String parameters -->
<ui-spark-user-card name="John Doe" email="john@example.com"></ui-spark-user-card>

<!-- Numeric parameters -->
<ui-spark-counter initial-count="10" step="2"></ui-spark-counter>

<!-- Boolean parameters -->
<ui-spark-modal is-open="true" closable="false"></ui-spark-modal>

<!-- Array/Object parameters (JSON encoded) -->
<ui-spark-chart data="{{ json_encode($chartData) }}"></ui-spark-chart>

<!-- Dynamic parameters from variables -->
<ui-spark-product product-id="{{ $product->id }}" 
                  price="{{ $product->price }}" 
                  currency="{{ $settings->currency }}"></ui-spark-product>
```

### Parameter Conversion

Parameters are automatically converted to the appropriate PHP types:

```php
// Component class
class ProductComponent extends SparkComponent
{
    public int $productId;      // Converted from "product-id" attribute
    public float $price;        // Converted from "price" attribute  
    public string $currency;    // Converted from "currency" attribute
    public bool $isAvailable;   // Converted from "is-available" attribute
    public array $features;     // JSON decoded from "features" attribute
}
```

### Parameter Validation

Components can validate received parameters:

```php
class UserCardComponent extends SparkComponent
{
    public string $name = '';
    public string $email = '';
    public ?string $avatar = null;
    
    protected array $rules = [
        'name' => 'required|min:2',
        'email' => 'required|email',
    ];
    
    protected function mount(): void
    {
        // Validate parameters on component creation
        $errors = $this->validate();
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                'Invalid parameters: ' . implode(', ', array_keys($errors))
            );
        }
    }
}
```

### Default Parameters

Set default values in component properties:

```php
class PaginationComponent extends SparkComponent
{
    public int $currentPage = 1;
    public int $perPage = 10;
    public int $totalItems = 0;
    public string $baseUrl = '';
    public bool $showNumbers = true;
    public bool $showFirstLast = true;
    
    // These will be used if not provided in template
}
```

## Rendering Lifecycle

### Component Creation Lifecycle

1. **Component Discovery**: Registry lookup by name
2. **Class Instantiation**: Create component instance
3. **Parameter Assignment**: Set properties from attributes
4. **Mount Execution**: Call `mount()` method
5. **Template Compilation**: Compile Cigg template
6. **Rendering**: Execute template with component data
7. **HTML Generation**: Generate final HTML with wire attributes
8. **Client Hydration**: JavaScript takes over on client

### Server-Side Rendering Process

```php
// Simplified rendering process
class ComponentRenderer
{
    public function render(string $componentName, array $attributes = []): string
    {
        // 1. Discover component class
        $componentClass = SparkComponentRegistry::get($componentName);
        
        // 2. Create component instance
        $component = new $componentClass($this->engine, $this->logger);
        
        // 3. Set parameters
        foreach ($attributes as $key => $value) {
            $property = $this->convertAttributeName($key);
            if (property_exists($component, $property)) {
                $component->$property = $this->convertValue($value);
            }
        }
        
        // 4. Mount component
        $component->mount();
        
        // 5. Generate HTML
        return $component->toHtml();
    }
    
    private function convertAttributeName(string $attribute): string
    {
        // Convert kebab-case to camelCase
        return lcfirst(str_replace('-', '', ucwords($attribute, '-')));
    }
    
    private function convertValue(string $value): mixed
    {
        // Convert string values to appropriate types
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float)$value : (int)$value;
        }
        
        if (in_array(strtolower($value), ['true', 'false'])) {
            return strtolower($value) === 'true';
        }
        
        if (str_starts_with($value, '{') || str_starts_with($value, '[')) {
            return json_decode($value, true) ?? $value;
        }
        
        return $value;
    }
}
```

### Client-Side Hydration

After server-side rendering, JavaScript hydrates the component:

```typescript
// Component hydration process
class SparkComponent {
    constructor(element: ComponentElement) {
        // 1. Parse wire:data attribute
        const wireData = element.getAttribute('wire:data');
        const componentData = JSON.parse(atob(wireData));
        
        // 2. Initialize component state
        this.data = componentData.data;
        this.checksum = componentData.checksum;
        this.name = componentData.name;
        
        // 3. Setup reactive signals
        this.setupSignals();
        
        // 4. Bind event listeners
        this.setupEventListeners();
        
        // 5. Component is now live!
    }
}
```

## Template System

### Component Templates

Each component needs a corresponding template file:

```
Component Class: App\SparkComponents\UserProfileComponent
Template File: templates/spark/userprofile.cigg
```

### Template Structure

```html
<!-- templates/spark/userprofile.cigg -->
<div class="user-profile">
    <!-- Component data is available as variables -->
    <div class="avatar">
        @if($avatar)
            <img src="{{ $avatar }}" alt="{{ $name }}">
        @else
            <div class="avatar-placeholder">{{ substr($name, 0, 1) }}</div>
        @endif
    </div>
    
    <div class="info">
        <h3>{{ $name }}</h3>
        <p>{{ $email }}</p>
        
        @if($bio)
            <p class="bio">{{ $bio }}</p>
        @endif
    </div>
    
    <!-- Interactive elements -->
    <div class="actions">
        <button wire:click="toggleFollow">
            {{ $isFollowing ? 'Unfollow' : 'Follow' }}
        </button>
        
        <button wire:click="sendMessage">Send Message</button>
    </div>
    
    <!-- Component state (not visible to user) -->
    @if($showDebug)
        <div class="debug">
            <pre>{{ json_encode($this->getPublicProperties(), JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif
</div>
```

### Template Compilation

Templates are compiled to optimized PHP code:

```php
// Compiled template (cached)
<?php 
// Component data is extracted into variables
extract($componentData);

// Compiled template code
echo '<div class="user-profile">';
if ($avatar) {
    echo '<img src="' . htmlspecialchars($avatar) . '" alt="' . htmlspecialchars($name) . '">';
} else {
    echo '<div class="avatar-placeholder">' . htmlspecialchars(substr($name, 0, 1)) . '</div>';
}
// ... rest of compiled template
echo '</div>';
?>
```

### Template Inheritance

Components can extend other templates:

```html
<!-- templates/spark/base-card.cigg -->
<div class="card {{ $cardClass ?? '' }}">
    <div class="card-header">
        @yield('header')
    </div>
    
    <div class="card-body">
        @yield('content')
    </div>
    
    @if(isset($showFooter) && $showFooter)
        <div class="card-footer">
            @yield('footer')
        </div>
    @endif
</div>

<!-- templates/spark/usercard.cigg -->
@extends('spark/base-card')

@section('header')
    <h3>{{ $name }}</h3>
@endsection

@section('content')
    <p>{{ $email }}</p>
    <button wire:click="viewProfile">View Profile</button>
@endsection
```

## Advanced Rendering

### Conditional Component Rendering

```html
<!-- Conditional rendering based on user role -->
@if($user->isAdmin())
    <ui-spark-admin-panel></ui-spark-admin-panel>
@elseif($user->isModerator())
    <ui-spark-moderator-panel></ui-spark-moderator-panel>
@else
    <ui-spark-user-dashboard></ui-spark-user-dashboard>
@endif

<!-- Conditional rendering based on feature flags -->
@if(FeatureFlag::isEnabled('new-dashboard'))
    <ui-spark-dashboard-v2></ui-spark-dashboard-v2>
@else
    <ui-spark-dashboard-v1></ui-spark-dashboard-v1>
@endif
```

### Dynamic Component Rendering

```php
// Controller
class DashboardController
{
    public function index(): Response
    {
        $widgets = [
            ['type' => 'stats', 'config' => ['period' => '30d']],
            ['type' => 'chart', 'config' => ['chart_type' => 'line']],
            ['type' => 'notifications', 'config' => ['limit' => 5]],
        ];
        
        return $this->render('dashboard', ['widgets' => $widgets]);
    }
}
```

```html
<!-- Template -->
<div class="dashboard">
    @foreach($widgets as $widget)
        <div class="widget">
            <ui-spark-widgets-{{ $widget['type'] }}
                @foreach($widget['config'] as $key => $value)
                    {{ $key }}="{{ $value }}"
                @endforeach
            ></ui-spark-widgets-{{ $widget['type'] }}>
        </div>
    @endforeach
</div>
```

### Programmatic Rendering

```php
use Elementary\Spark\SparkManager;

class ApiController
{
    public function getWidgetHtml(Request $request): Response
    {
        $widgetType = $request->get('type');
        $config = $request->get('config', []);
        
        $manager = SparkManager::getInstance();
        $html = $manager->renderComponent(
            "App\\SparkComponents\\Widgets\\{$widgetType}WidgetComponent",
            $config
        );
        
        return new JsonResponse(['html' => $html]);
    }
}
```

### Component Composition

```php
class DashboardComponent extends SparkComponent
{
    public array $widgets = [];
    
    public function render(): string
    {
        $html = '<div class="dashboard">';
        
        foreach ($this->widgets as $widget) {
            $html .= $this->renderWidget($widget);
        }
        
        $html .= '</div>';
        return $html;
    }
    
    private function renderWidget(array $widget): string
    {
        $manager = SparkManager::getInstance();
        
        $componentClass = "App\\SparkComponents\\Widgets\\" . 
                         ucfirst($widget['type']) . "WidgetComponent";
        
        return $manager->renderComponent($componentClass, $widget['config'] ?? []);
    }
}
```

### Lazy Loading Components

```html
<!-- Initial placeholder -->
<div id="lazy-component-{{ $componentId }}" 
     class="lazy-component" 
     data-component="user-activity-feed"
     data-config="{{ json_encode($config) }}">
    <div class="loading">Loading...</div>
</div>

<script>
// Load component when visible
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            loadLazyComponent(entry.target);
            observer.unobserve(entry.target);
        }
    });
});

document.querySelectorAll('.lazy-component').forEach(el => {
    observer.observe(el);
});

async function loadLazyComponent(element) {
    const component = element.dataset.component;
    const config = JSON.parse(element.dataset.config);
    
    const response = await fetch('/api/components/render', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({component, config})
    });
    
    const {html} = await response.json();
    element.outerHTML = html;
    
    // Hydrate the new component
    window.Spark.scanForComponents();
}
</script>
```

## Performance Optimization

### Template Caching

```php
class OptimizedTemplateEngine
{
    private string $cacheDir;
    private bool $cacheEnabled;
    
    public function render(string $template, array $data = []): string
    {
        $cacheKey = $this->getCacheKey($template, $data);
        $cachedPath = $this->cacheDir . '/' . $cacheKey . '.php';
        
        if ($this->cacheEnabled && $this->isCacheValid($template, $cachedPath)) {
            return $this->renderCached($cachedPath, $data);
        }
        
        return $this->compileAndRender($template, $data, $cachedPath);
    }
    
    private function isCacheValid(string $template, string $cachedPath): bool
    {
        return file_exists($cachedPath) && 
               filemtime($template) <= filemtime($cachedPath);
    }
}
```

### Component Memoization

```php
trait ComponentMemoization
{
    private static array $memoCache = [];
    
    protected function memoize(string $key, callable $callback): mixed
    {
        $fullKey = get_class($this) . ':' . $key;
        
        if (!isset(self::$memoCache[$fullKey])) {
            self::$memoCache[$fullKey] = $callback();
        }
        
        return self::$memoCache[$fullKey];
    }
    
    protected function clearMemoCache(): void
    {
        $classPrefix = get_class($this) . ':';
        
        foreach (array_keys(self::$memoCache) as $key) {
            if (str_starts_with($key, $classPrefix)) {
                unset(self::$memoCache[$key]);
            }
        }
    }
}

// Usage in component
class ExpensiveComponent extends SparkComponent
{
    use ComponentMemoization;
    
    public function getExpensiveData(): array
    {
        return $this->memoize('expensive_data', function() {
            // Expensive computation
            return $this->computeComplexData();
        });
    }
}
```

### Selective Rendering

```php
class SelectiveRenderingComponent extends SparkComponent
{
    public array $items = [];
    private array $changedItems = [];
    
    public function updateItem(int $index, array $data): void
    {
        $this->items[$index] = array_merge($this->items[$index], $data);
        $this->changedItems[] = $index;
    }
    
    public function render(): string
    {
        if (empty($this->changedItems)) {
            // Full render
            return $this->renderFull();
        } else {
            // Partial render - only changed items
            return $this->renderPartial();
        }
    }
    
    private function renderPartial(): string
    {
        // Return only HTML for changed items
        $html = '';
        foreach ($this->changedItems as $index) {
            $html .= $this->renderItem($this->items[$index], $index);
        }
        return $html;
    }
}
```

## Troubleshooting

### Common Rendering Issues

#### 1. Component Not Found
```
Error: Spark component 'my-component' is not registered
```

**Solutions:**
- Check component registration
- Verify component name spelling
- Ensure registry is loaded

#### 2. Template Not Found
```
Error: SparkComponent template not found: templates/spark/mycomponent.cigg
```

**Solutions:**
- Create the template file
- Check template naming convention
- Verify template directory structure

#### 3. Property Not Synced
```
Warning: Property 'myProperty' is not public and cannot be synced
```

**Solutions:**
- Make property public
- Use dynamic properties via `$data` array
- Check property visibility

### Debugging Rendering

```php
class RenderingDebugger
{
    public static function debugComponent(string $componentName): array
    {
        $debug = [
            'component_name' => $componentName,
            'registered' => SparkComponentRegistry::has($componentName),
            'class' => null,
            'template' => null,
            'errors' => []
        ];
        
        if ($debug['registered']) {
            $debug['class'] = SparkComponentRegistry::get($componentName);
            
            if (class_exists($debug['class'])) {
                $instance = new $debug['class'](new Engine(), null);
                $debug['template'] = $instance->getCompiledTemplate();
                $debug['properties'] = $instance->getPublicProperties();
            } else {
                $debug['errors'][] = 'Component class does not exist';
            }
        } else {
            $debug['errors'][] = 'Component not registered';
        }
        
        return $debug;
    }
}

// Usage
$debugInfo = RenderingDebugger::debugComponent('my-component');
var_dump($debugInfo);
```

### Performance Debugging

```html
<!-- Add to templates for performance debugging -->
@if(config('app.debug'))
    <div class="spark-debug" style="border: 1px solid red; padding: 5px; margin: 5px;">
        <strong>Component:</strong> {{ get_class($__spark) }}<br>
        <strong>ID:</strong> {{ $__spark->getId() }}<br>
        <strong>Render Time:</strong> {{ microtime(true) - $__spark->startTime }}ms<br>
        <strong>Properties:</strong> {{ count($__spark->getPublicProperties()) }}<br>
        <details>
            <summary>Data</summary>
            <pre>{{ json_encode($__spark->getPublicProperties(), JSON_PRETTY_PRINT) }}</pre>
        </details>
    </div>
@endif
```

## Best Practices

### 1. Template Organization
- Keep templates focused and single-purpose
- Use template inheritance for common patterns
- Organize templates in logical directories

### 2. Performance
- Cache expensive computations
- Use memoization for repeated operations
- Minimize data passed to templates

### 3. Debugging
- Use debug mode during development
- Implement proper error handling
- Log rendering performance metrics

### 4. Security
- Always escape output in templates
- Validate component parameters
- Sanitize user input

### 5. Maintainability
- Follow consistent naming conventions
- Document component interfaces
- Write tests for complex rendering logic

## Next Steps

- [API Reference](api-reference.md) - Complete API documentation
- [Examples](examples.md) - Real-world rendering examples
- [Performance Guide](performance.md) - Optimization strategies