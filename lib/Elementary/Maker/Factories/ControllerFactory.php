<?php

declare(strict_types=1);

namespace Elementary\Maker\Factories;

use Elementary\Template\Cigg\Engine;

class ControllerFactory 
{
    public function __construct(private Engine $engine) {}

    public function make(string $name): void
    {
        $template = __DIR__ . '/../stubs/controller.cigg';
        $cacheDir = $this->engine->getCachePath();

        $cacheFileName = md5($template . $name ) . '.php';

        $compiledTemplatePath = sprintf("%s/%s", rtrim( $cacheDir, '/' ), ltrim( $cacheFileName, '/' ));

        $this->engine->compileTemplate($template, $compiledTemplatePath);

        $content = $this->engine->renderCompiledTemplate($compiledTemplatePath, [
            'class' => $name
        ]);

        $targetFilePath = BASE_PATH . '/src/Controllers/' . $name . '.php';

        file_put_contents($targetFilePath, $content);
    }
}
