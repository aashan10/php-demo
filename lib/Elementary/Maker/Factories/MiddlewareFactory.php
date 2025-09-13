<?php

declare(strict_types=1);

namespace Elementary\Maker\Factories;

use Elementary\Template\Cigg\Engine;

class MiddlewareFactory 
{
    public function __construct(private Engine $engine) {}

    public function make(string $name): void
    {
        $template = __DIR__ . '/../stubs/middleware.cigg';
        $cacheDir = $this->engine->getCachePath();

        $cacheFileName = md5($template . $name) . '.php';

        $compiledTemplatePath = sprintf("%s/%s", rtrim($cacheDir, '/'), ltrim($cacheFileName, '/'));

        $this->engine->compileTemplate($template, $compiledTemplatePath);

        $content = $this->engine->renderCompiledTemplate($compiledTemplatePath, [
            'class' => $name
        ]);

        $targetDir = BASE_PATH . '/src/Middleware/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $targetFilePath = $targetDir . $name . '.php';

        file_put_contents($targetFilePath, $content);
    }
}