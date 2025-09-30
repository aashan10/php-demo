<?php

declare(strict_types=1);

namespace Elementary\Database\Console\Commands;

use Elementary\Console\Command;
use Elementary\Database\Migration\MigrationRunner;

final class MigrateStatusCommand extends Command
{
    public static $signature = 'migrate:status';
    public static string $description = 'Show the status of each migration';

    public function execute(array $args): int
    {
        $this->info('Migration status:');

        $runner = new MigrationRunner();
        $status = $runner->status();

        if (empty($status)) {
            $this->info('No migrations found.');
            return 0;
        }

        $this->line('');
        $this->line(sprintf('%-50s %s', 'Migration', 'Status'));
        $this->line(str_repeat('-', 60));

        foreach ($status as $migration) {
            $statusColor = $migration['status'] === 'Ran' ? 'success' : 'warning';
            $this->line(sprintf('%-50s %s', 
                $migration['migration'], 
                $this->colorize($migration['status'], $statusColor)
            ));
        }

        return 0;
    }

    private function colorize(string $text, string $type): string
    {
        $colors = [
            'success' => "\033[32m",  // Green
            'warning' => "\033[33m",  // Yellow
            'error' => "\033[31m",    // Red
        ];

        $reset = "\033[0m";
        $color = $colors[$type] ?? '';

        return $color . $text . $reset;
    }
}