# Cigg Components

The Cigg templating engine includes a powerful component system, inspired by Laravel Blade components, that allows you to create reusable, isolated pieces of UI. You can create components as simple, view-only files or as class-based components for more complex logic.

## Component Syntax

Components are rendered using an HTML-like tag starting with an `x-` prefix.

```html
<x-alert type="success" message="This is a success message."/>
```

### Passing Props

You can pass data to components using attributes (props). 

**Static Props**: For static string values, use standard HTML attributes.

```html
<x-alert type="warning"/>
```

**Dynamic Props**: To pass a PHP variable or expression, prefix the attribute name with a colon (`:`).

```html
<x-alert :type="$alertType" :message="'Message: ' . $message"/>
```

### Slots

For more complex content, you can pass content to a component via its "slot". The content between the opening and closing component tags will be injected into the component's template in a `$slot` variable.

```html
<x-card>
    <h2 class="font-bold">Card Title</h2>
    <p>This is the body of the card.</p>
</x-card>
```

## Anonymous (View-Only) Components

For simple components that only require a view, you can create a template file in the `templates/components/` directory. The name of the file will correspond to the component tag.

**Example: `templates/components/alert.cigg`**

```html
{{-- This component will be rendered with <x-alert> --}}
<div class="alert alert-{{ $type ?? 'info' }}">
    <p>{{ $message ?? 'This is a default message.' }}</p>
</div>
```

**Usage:**

```html
<x-alert type="danger" message="Something went wrong!" />
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
    <x-card title="My Awesome Card">
        This is the body of the card passed into the slot.
    </x-card>
    ```
