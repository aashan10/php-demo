# Console Commands

The Elementary framework includes a command-line interface that allows you to run custom commands for tasks like database migrations, data processing, or other administrative tasks.

## Creating a Command

To create a new command, create a new PHP class in the `src/Console/Commands` directory. It's recommended that your command extends `Elementary\Console\Command` to gain access to helpful output methods.

The class should have two main components:

1.  A static property `$defaultName` which defines the command's name.
2.  An `execute()` method which contains the logic for the command.

```php
// src/Console/Commands/HelloWorldCommand.php

namespace App\Console\Commands;

use Elementary\Console\Command; // Don't forget to import!

class HelloWorldCommand extends Command
{
    public static string $defaultName = 'app:hello-world';

    public function execute(array $args): int
    {
        $this->info("Hello, World!"); // Using the new helper method

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

### Listing Commands and Help

To see a list of all available commands, you can run:

```bash
php elementary
# or
php elementary help
```

### Useful Commands

Beyond your custom commands, the framework provides some built-in utilities:

*   **`templates:compile`**: Compiles all Cigg templates into their cached PHP forms. This is highly recommended for production environments to improve performance and simplify deployment.
    ```bash
    php elementary templates:compile
    ```

## Command Output Helpers

When your command extends `Elementary\Console\Command`, you gain access to several convenient methods for printing formatted output to the console:

*   **`$this->line(string $text)`**: Prints a plain line of text.
*   **`$this->info(string $text)`**: Prints an informational message in blue, prefixed with `[INFO]:`.
*   **`$this->success(string $text)`**: Prints a success message in green, prefixed with `[SUCCESS]:`.
*   **`$this->warning(string $text)`**: Prints a warning message in yellow, prefixed with `[WARNING]:`.
*   **`$this->error(string $text)`**: Prints an error message in red, prefixed with `[ERROR]:`.
*   **`$this->table(array $headers, array $rows)`**: Renders tabular data with automatically calculated column widths and borders.

## Example: 1BRC Command

The project includes advanced commands for the "One Billion Row Challenge" (`1brc:execute` and `1brc:fork`). These serve as excellent examples of how to build complex, high-performance commands within the framework.

To run the parallel version:

```bash
php elementary 1brc:fork
```

```