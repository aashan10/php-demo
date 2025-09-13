<?php

declare(strict_types=1);

namespace Elementary\Console\Commands;

use Elementary\Console\Command;
use Elementary\Template\Cigg\Engine;
use Elementary\Utils\Traits\BetterTry;

class TemplateCompileCommand extends Command
{

    use BetterTry;

    public static $signature = 'template:compile {--force : Force recompilation of all templates}';
    public static $description = 'Compile all template files to improve performance';

    public function __construct(private Engine $engine)
    {
    }

    public function execute(array $args = []): int 
    {
        $cacheDir = $this->engine->getCachePath();
        $templateDir = $this->engine->getViewsPath();


        $this->info('Clearing existing templates..');

        $files = glob($cacheDir . '/*.php');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->success('Cleared existing templates.');

        $this->info('Compiling templates...');

        $data = [];

        $directoryIterator = new \RecursiveDirectoryIterator($templateDir);
        $iterator = new \RecursiveIteratorIterator($directoryIterator);

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'cigg') {

                $fullPath      = $file->getRealPath();
                $cacheKey      = md5($fullPath);
                $cacheFileName = $cacheDir . '/' . $cacheKey . '.php';

                $relativePath = str_replace($templateDir . DIRECTORY_SEPARATOR, '', $fullPath);

                [$result, $error] = $this->try(fn () => $this->engine->compileTemplate($fullPath, $cacheFileName));
                if ($error) {
                    $this->warning("Failed to compile template: {$relativePath}. Error: " . $error->getMessage());
                } else {
                    $data[] = [$relativePath, $cacheFileName];
                }
                
            }
        }

        $this->success('Template compilation completed.');
        $this->table(['Template', 'Cached As'], $data);

        return 0;
    }

}
