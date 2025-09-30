<?php

declare(strict_types=1);

namespace Elementary\Database\Console\Commands;

use Elementary\Console\Command;
use Elementary\Database\Migration\MigrationRunner;

final class MigrateRollbackCommand extends Command
{
    public static $signature = 'migrate:rollback';
    public static string $description = 'Rollback the last batch of database migrations';

    public function execute(array $args): int
    {
        $this->info('Rolling back migrations...');

        $runner = new MigrationRunner();
        $rolledBack = $runner->rollback();

        if (empty($rolledBack)) {
            $this->info('Nothing to rollback.');
        } else {
            $this->success('Rolled back:');
            foreach ($rolledBack as $migration) {
                $this->line('  ' . $migration);
            }
        }

        return 0;
    }
}