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

### Executing Raw PHP

While it's best to keep logic in controllers, you can execute raw PHP code in your templates using the `@php` and `@endphp` directives.

```html
@php
    // You can write any PHP code here.
    $message = 'This is a raw PHP block.';
@endphp

<p>{{ $message }}</p>
```

## Template Inheritance & Includes

Cigg makes it easy to build complex layouts and include partial templates.

### Defining a Layout

Layouts are defined using `@section` and `@yield`. The `@yield` directive is used to display the contents of a section, while `@section` defines a piece of content.

Here is an example of a main layout file (`layouts/app.cigg`):

```html
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title', 'My App')</title>
</head>
<body>
    <div class="container">
        @yield('content')
    </div>
</body>
</html>
```

### Extending a Layout

You can extend a layout using the `@extends` directive. You can then inject content into the layout's sections using `@section` blocks.

```html
@extends('layouts.app')

@section('title')
    My Page Title
@endsection

@section('content')
    <p>This is the content for my page.</p>
@endsection
```

### Including Partials

You can include a partial template from within another template using the `@include` directive. All variables from the parent template will be available to the included template.

```html
{{-- In a user profile template --}}
@include('users.header', ['headline' => 'User Profile'])

<p>User details...</p>
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
