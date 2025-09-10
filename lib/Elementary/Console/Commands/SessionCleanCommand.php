<?php

declare(strict_types=1);

namespace Elementary\Console\Commands;

use Elementary\Console\AbstractCommand;
use Elementary\Utils\Traits\BetterTry;

class SessionCleanCommand extends AbstractCommand
{

    use BetterTry;

    public static $signature = 'session:clean {--all : Remove all session files, including active ones}';
    public static $description = 'Clean up old session files to free up space';

    public function execute(array $args = []): int 
    {
        $cacheDir = $this->engine->getCachePath();

        return 0;
    }

}
