# Cigg Components

The Cigg templating engine includes a powerful component system, inspired by Laravel Blade components, that allows you to create reusable, isolated pieces of UI. You can create components as simple, view-only files or as class-based components for more complex logic.

## Component Syntax

Components are rendered using an HTML-like tag with namespace dot notation based on their directory structure.

```html
<ui-alert type="success" message="This is a success message."/>
```

### Namespace Mapping

The component namespace maps directly to the directory structure under `templates/components/`:

- `<ui-alert>` → `templates/components/alert.cigg`
- `<ui-forms.input>` → `templates/components/forms/input.cigg`
- `<ui-forms.button>` → `templates/components/forms/button.cigg`
- `<ui-card>` → `templates/components/card.cigg`

### Passing Props

You can pass data to components using attributes (props). 

**Static Props**: For static string values, use standard HTML attributes.

```html
<ui-alert type="warning"/>
```

**Dynamic Props**: To pass a PHP variable or expression, prefix the attribute name with a colon (`:`).

```html
<ui-alert :type="$alertType" :message="'Message: ' . $message"/>
```

### Slots

For more complex content, you can pass content to a component via its "slot". The content between the opening and closing component tags will be injected into the component's template in a `$slot` variable.

```html
<ui-card>
    <h2 class="font-bold">Card Title</h2>
    <p>This is the body of the card.</p>
</ui-card>
```

## Anonymous (View-Only) Components

For simple components that only require a view, you can create a template file in the `templates/components/` directory. The directory structure maps to the component namespace using dot notation.

**Example: `templates/components/alert.cigg`**

```html
{{-- This component will be rendered with <ui-alert> --}}
<div class="alert alert-{{ $type ?? 'info' }}">
    <p>{{ $message ?? 'This is a default message.' }}</p>
</div>
```

**Example: `templates/components/forms/input.cigg`**

```html
{{-- This component will be rendered with <ui-forms.input> --}}
@php
    $type = $type ?? 'text';
    $name = $name ?? '';
    $label = $label ?? '';
    $placeholder = $placeholder ?? '';
    $value = $value ?? '';
    $required = isset($required) ? 'required' : '';
@endphp

<div>
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-semibold text-gray-900 mb-2">
            {{ $label }}
        </label>
    @endif
    
    <input 
        type="{{ $type }}" 
        id="{{ $name }}" 
        name="{{ $name }}" 
        value="{{ $value }}" 
        {{ $required }}
        class="w-full py-3 px-4 border border-gray-300 rounded-lg"
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
    >
</div>
```

**Usage:**

```html
<ui-alert type="danger" message="Something went wrong!" />
<ui-forms.input type="email" name="email" label="Email Address" placeholder="Enter your email" required="true" />
```

## Class-Based Components

For components that require more complex logic, you can create a dedicated PHP class.

1.  **Create the Component Class**

    Create a class that extends `Elementary\Template\Cigg\Component`. It must have a `render` method that returns the view to be rendered.

    **Example: `src/View/Components/Card.php`** (You may need to create this directory)

    ```php
    <?php

    namespace App\View\Components;

    use Elementary\Template\Cigg\Component;
    use Elementary\Template\Cigg\Engine;

    class Card extends Component
    {
        private Engine $engine;

        public function __construct(Engine $engine)
        {
            $this->engine = $engine;
        }

        public function render(): string
        {
            // You can access props via $this->attributes
            $title = $this->attributes['title'] ?? 'Default Title';

            // The slot content is available in $this->slot
            $slotContent = $this->slot;

            // Render a view and pass data to it
            return $this->engine->render('components.card', [
                'title' => $title,
                'slot' => $slotContent,
            ]);
        }
    }
    ```

2.  **Register the Component**

    Map your component tag to its class in `config/components.php`.

    ```php
    // config/components.php
    return [
        'card' => App\View\Components\Card::class,
    ];
    ```

3.  **Create the Component's View**

    This is the view that the class-based component will render.

    **Example: `templates/components/card.cigg`**

    ```html
    <div class="card">
        <h2 class="card-title">{{ $title }}</h2>
        <div class="card-body">
            {{ $slot }}
        </div>
    </div>
    ```

4.  **Usage**

    You can now use the component in any template.

    ```html
    <ui-card title="My Awesome Card">
        This is the body of the card passed into the slot.
    </ui-card>
    ```
