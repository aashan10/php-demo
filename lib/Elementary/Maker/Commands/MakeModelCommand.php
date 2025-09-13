<?php

declare(strict_types=1);

namespace Elementary\Maker\Commands;

use Elementary\Console\Command;
use Elementary\Maker\Factories\ModelFactory;

class MakeModelCommand extends Command
{
    public static string $signature = 'make:model {name} {--table=}';
    public static string $description = 'Create a new model class. The --table option can be used to specify the database table name. Usage: eleemntary make:model User --table=users';

    public function __construct(private ModelFactory $factory) {}

    public function execute(array $args = []): int 
    {

        $name = $args[0] ?? null;

        if (!$name) {
            $this->error("Model name is required.");
            return self::FAILURE;
        }

        $table = array_reduce($args, function ($carry, $arg) {
            if (str_starts_with($arg, '--table=')) {
                return substr($arg, 8);
            }
            return $carry;
        }, null);

        if (!$table) {
            $this->warning("No table name provided. Using default naming convention.");
            $table = strtolower($name) . 's'; // Simple pluralization

            $this->info("Using table name '$table'.");
        }

        $this->factory->make($name, $table);
        $this->success("Model '$name' created successfully with table '$table'.");

        return self::SUCCESS;
    }
}
