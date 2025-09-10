<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Elementary\Console\AbstractCommand;
use Elementary\Template\Cigg\Engine;

final class ClearCacheCommand extends AbstractCommand
{
    public function __construct(private Engine $engine) {}

    public static $signature = 'cache:clear';

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
