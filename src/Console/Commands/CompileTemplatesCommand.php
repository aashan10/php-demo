<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Elementary\Console\AbstractCommand;
use Elementary\Template\Cigg\Engine;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use SplFileInfo;

final class CompileTemplatesCommand extends AbstractCommand
{
    public static string $defaultName = 'templates:compile';


    public function __construct(private Engine $engine)
    {
    }

    public function execute(array $args): int
    {
        $this->info("Clearing template cache...");
        $this->clearCache();

        $this->info("Compiling Cigg templates...");

        $viewsPath = $this->engine->getViewsPath();
        $cachePath = $this->engine->getCachePath();

        $directory = new RecursiveDirectoryIterator($viewsPath);
        $iterator = new RecursiveIteratorIterator($directory);

        $compiledPairs = [];
        $failed = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'cigg') {
                $sourcePath = $file->getPathname();
                $cacheFile = $cachePath . '/' . md5($sourcePath) . '.php';

                try {
                    $this->engine->compileTemplate($sourcePath, $cacheFile);
                    $relativeSource = str_replace(BASE_PATH . '/', '', $sourcePath);
                    $relativeCache = str_replace(BASE_PATH . '/', '', $cacheFile);
                    $compiledPairs[] = ['source' => $relativeSource, 'cache' => $relativeCache];
                } catch (\Throwable $e) {
                    $failed[] = ['source' => $sourcePath, 'error' => $e->getMessage()];
                }
            }
        }

        if (!empty($compiledPairs)) {
            $this->line(""); // Add a newline for spacing
            $headers = ['Source Template', 'Cached File'];
            $rows = array_map(fn($pair) => [$pair['source'], $pair['cache']], $compiledPairs);
            $this->table($headers, $rows);
        }

        if (!empty($failed)) {
            $this->warning("Failed to compile some templates:");
            foreach ($failed as $failure) {
                $this->line("  - {$failure['source']}");
                $this->error("    Error: {$failure['error']}");
            }
        }

        $this->success("Compilation complete. Compiled " . count($compiledPairs) . " template(s).");

        return 0;
    }

    private function clearCache(): void
    {
        $cachePath = $this->engine->getCachePath();
        $files = glob($cachePath . '/*.php');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}

