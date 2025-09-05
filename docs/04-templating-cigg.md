# Templating with Cigg

The Elementary framework includes a custom templating engine called Cigg. It provides a simple yet powerful syntax for creating your views. Template files use the `.cigg` extension and are stored in the `templates/` directory.

## Basic Syntax

### Displaying Data

To display data, you can use "echo" tags. By default, all output is escaped to prevent XSS attacks.

```html
{{-- Escaped output --}}
<h1>Hello, {{ $name }}</h1>
```

### Raw (Unescaped) Output

If you need to output raw HTML, you can use the `{!! ... !!}` syntax. Use this with caution and only with trusted data.

```html
{!! $rawHtmlContent !!}
```

## Directives

Directives are control structures that begin with `@`. Cigg supports common structures like conditionals and loops.

### Conditionals

You can use `@if`, `@elseif`, `@else`, and `@endif` to write conditional statements.

```html
@if ($user->isLoggedIn())
    <p>Welcome, {{ $user->name }}</p>
@elseif ($user->isGuest())
    <p>Please sign up.</p>
@else
    <p>Not sure what you are.</p>
@endif
```

### Loops

Cigg supports `@foreach` loops to iterate over arrays.

```html
<ul>
    @foreach ($users as $user)
        <li>{{ $user->name }}</li>
    @endforeach
</ul>
```

## Custom Directives

The Cigg engine is extensible. You can add your own custom directives.

1.  **Create a Directive Class**: Create a new class that implements the `Elementary\Template\Cigg\Directives\DirectiveInterface`.

2.  **Register the Directive**: Add your new directive class to the `directives` array in the `config/template.php` configuration file.

    ```php
    // config/template.php
    'directives' => [
        // ... other directives
        \App\Directives\MyCustomDirective::class,
    ],
    ```

The templating engine will automatically register and use your custom directive.
