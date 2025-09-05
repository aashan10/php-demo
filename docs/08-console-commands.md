# Console Commands

The Elementary framework includes a command-line interface that allows you to run custom commands for tasks like database migrations, data processing, or other administrative tasks.

## Creating a Command

To create a new command, create a new PHP class in the `src/Console/Commands` directory. The class should have two main components:

1.  A static property `$defaultName` which defines the command's name.
2.  An `execute()` method which contains the logic for the command.

```php
// src/Console/Commands/HelloWorldCommand.php

namespace App\Console\Commands;

final class HelloWorldCommand
{
    public static string $defaultName = 'app:hello-world';

    public function execute(array $args): int
    {
        echo "Hello, World!\n";

        // Return 0 for success
        return 0;
    }
}
```

The `ConsoleKernel` will automatically discover any command classes in this directory.

## Running Commands

To run a command, you can use the `elementary` script from within the `cli` Docker container. First, get a shell inside the container:

```bash
docker-compose exec cli bash
```

Then, from within the container, you can run your command:

```bash
php elementary app:hello-world
```

### Listing Commands

If you run the `elementary` script without any arguments, it will display a list of all available commands.

```bash
php elementary
```

## Example: 1BRC Command

The project includes advanced commands for the "One Billion Row Challenge" (`1brc:execute` and `1brc:fork`). These serve as excellent examples of how to build complex, high-performance commands within the framework.

To run the parallel version:

```bash
php elementary 1brc:fork
```

```