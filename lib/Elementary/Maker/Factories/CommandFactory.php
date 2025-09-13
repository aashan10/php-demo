<?php

declare(strict_types=1);

namespace Elementary\Maker\Factories;

use Elementary\Template\Cigg\Engine;

class CommandFactory 
{
    public function __construct(private Engine $engine) {}

    public function make(string $name, string $signature, string $description): void
    {
        $template = __DIR__ . '/../stubs/command.cigg';
        $cacheDir = $this->engine->getCachePath();

        $cacheFileName = md5($template . $name) . '.php';

        $compiledTemplatePath = sprintf("%s/%s", rtrim($cacheDir, '/'), ltrim($cacheFileName, '/'));

        $this->engine->compileTemplate($template, $compiledTemplatePath);

        $content = $this->engine->renderCompiledTemplate($compiledTemplatePath, [
            'class' => $name,
            'signature' => $signature,
            'description' => $description,
        ]);

        $targetFilePath = BASE_PATH . '/src/Console/Commands/' . $name . '.php';

        file_put_contents($targetFilePath, $content);
    }
}