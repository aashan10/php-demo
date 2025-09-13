# Dependency Injection

The Elementary framework is built around a powerful yet simple Dependency Injection (DI) container. The container is responsible for managing class dependencies, allowing you to write loosely coupled and more testable code.

## Auto-Wiring

For many cases, you don't need to do anything at all. The container is capable of automatically resolving most dependencies through a process called "auto-wiring". If a class your controller depends on (like `UserRepository`) has a parameter-less constructor or its own dependencies can be resolved by the container, the container will automatically create the instance for you.

For example, in a controller constructor:

```php
public function __construct(UserRepository $users)
{
    // The container automatically creates and injects an instance of UserRepository
    $this->users = $users;
}
```

## Binding

For more complex situations, you need to tell the container how to resolve a dependency. This is done by "binding" in the `bootstrap.php` file.

### Binding Interfaces to Implementations

A common use case is to bind an interface to a concrete class. This allows you to easily swap out implementations without changing the code in your controllers.

```php
// bootstrap.php

// Example: Tell the container that whenever a class needs a MailerInterface,
// it should provide an instance of SmtpMailer.
$container->bind(MailerInterface::class, SmtpMailer::class);
```

Now you can type-hint the interface in your controller, and the container will provide the correct object:

```php
public function __construct(MailerInterface $mailer)
{
    $this->mailer = $mailer;
}
```

### Complex Bindings with Factories

Sometimes, creating an object is more complex. It might need configuration values or have dependencies that need to be set up in a specific way. For this, you can use a factory closure.

The container will provide an instance of itself (`$c`) to the closure, which you can use to resolve other dependencies.

```php
// bootstrap.php

// The Template Engine needs several dependencies to be built
$container->bind(ElementaryEngine::class, function (Container $c): ElementaryEngine {
    $engine = new ElementaryEngine(
        $c->get(ConfigBag::class),
        $c->get(Lexer::class),
        $c->get(Parser::class),
        $c->get(Compiler::class)
    );

    // You can also perform additional setup
    $engine->addGlobal('app_name', $c->get(ConfigBag::class)->get('app.name', 'MyApp'));

    return $engine;
});
```
