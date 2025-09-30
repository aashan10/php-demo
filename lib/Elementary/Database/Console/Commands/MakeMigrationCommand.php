<?php

declare(strict_types=1);

namespace Elementary\Database\Console\Commands;

use Elementary\Console\Command;
use Elementary\Database\Migration\MigrationRunner;

final class MakeMigrationCommand extends Command
{
    public static $signature = 'make:migration {name}';
    public static string $description = 'Create a new migration file';

    public function execute(array $args): int
    {
        // Try named argument first, then positional argument
        $name = $args['name'] ?? $args[0] ?? null;

        if (!$name) {
            $this->error('Migration name is required.');
            $this->line('Usage: make:migration {name}');
            return 1;
        }

        $runner = new MigrationRunner();
        $filepath = $runner->makeMigration($name);

        $this->success('Migration created: ' . basename($filepath));
        $this->line('Path: ' . $filepath);

        return 0;
    }
}