<?php

declare(strict_types=1);

namespace Elementary\Database\Console\Commands;

use Elementary\Console\Command;
use Elementary\Database\Migration\MigrationRunner;

final class MigrateCommand extends Command
{
    public static $signature = 'migrate';
    public static string $description = 'Run database migrations';

    public function execute(array $args): int
    {
        $this->info('Running migrations...');

        $runner = new MigrationRunner();
        $executed = $runner->migrate();

        if (empty($executed)) {
            $this->info('Nothing to migrate.');
        } else {
            $this->success('Migrated:');
            foreach ($executed as $migration) {
                $this->line('  ' . $migration);
            }
        }

        return 0;
    }
}