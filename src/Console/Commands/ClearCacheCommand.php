<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Elementary\Console\Command;
use Elementary\Template\Cigg\Engine;

final class ClearCacheCommand extends Command
{
    public function __construct(private Engine $engine) {}

    public static $signature = 'cache:clear';
    public static string $description = 'Clear all compiled template cache files.';

    public function execute(array $args): int 
    {

        $cachePath = $this->engine->getCachePath();

        $this->info('Clearing all template cache from ' . $cachePath);

        $files = glob($cachePath . '/*.php');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->success('Template cache cleared.');

        return 0;
    }
}
