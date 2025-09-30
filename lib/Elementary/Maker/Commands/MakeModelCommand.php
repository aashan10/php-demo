<?php

declare(strict_types=1);

namespace Elementary\Maker\Commands;

use Elementary\Console\Command;
use Elementary\Maker\Factories\ModelFactory;
use Elementary\Database\Migration\MigrationRunner;

class MakeModelCommand extends Command
{
    public static string $signature = 'make:model {name} {--table=} {-m}';
    public static string $description = 'Create a new model class. The --table option can be used to specify the database table name. Use -m to also create a migration. Usage: elementary make:model User --table=users -m';

    public function __construct(private ModelFactory $factory) {}

    public function execute(array $args = []): int 
    {

        $name = $args[0] ?? null;

        if (!$name) {
            $this->error("Model name is required.");
            return self::FAILURE;
        }

        // Extract --table option
        $table = array_reduce($args, function ($carry, $arg) {
            if (str_starts_with($arg, '--table=')) {
                return substr($arg, 8);
            }
            return $carry;
        }, null);

        // Check for -m flag
        $createMigration = in_array('-m', $args) || in_array('--migration', $args);

        if (!$table) {
            $this->warning("No table name provided. Using default naming convention.");
            $table = strtolower($name) . 's'; // Simple pluralization
            $this->info("Using table name '$table'.");
        }

        // Create the model
        $this->factory->make($name, $table);
        $this->success("Model '$name' created successfully with table '$table'.");

        // Create migration if -m flag was passed
        if ($createMigration) {
            $migrationName = 'create_' . $table . '_table';
            $runner = new MigrationRunner();
            $migrationPath = $runner->makeMigration($migrationName);
            
            $this->success("Migration created: " . basename($migrationPath));
            $this->info("Don't forget to implement the migration logic in: " . $migrationPath);
        }

        return self::SUCCESS;
    }
}
