<?php

declare(strict_types=1);

namespace Elementary\Maker\Factories;

use Elementary\Template\Cigg\Engine;

class ModelFactory 
{
    public function __construct(private Engine $engine) {}

    public function make(string $name, string $table): void
    {
        $template = __DIR__ . '/../stubs/model.cigg';
        $cacheDir = $this->engine->getCachePath();

        $cacheFileName = md5($template . $name ) . '.php';

        $compiledTemplatePath = sprintf("%s/%s", rtrim( $cacheDir, '/' ), ltrim( $cacheFileName, '/' ));

        $this->engine->compileTemplate($template, $compiledTemplatePath);

        $content = $this->engine->renderCompiledTemplate($compiledTemplatePath, [
            'class' => $name,
            'table' => $table
        ]);

        $targetFilePath = BASE_PATH . '/src/Models/' . $name . '.php';

        file_put_contents($targetFilePath, $content);
    }
}
