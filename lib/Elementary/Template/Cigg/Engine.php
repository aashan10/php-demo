<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg;

use Elementary\Config\ConfigBag;
use Elementary\Template\Cigg\Lexer\Lexer;
use Elementary\Template\Cigg\Parser\Parser;
use Elementary\Template\Cigg\Compiler\Compiler;
use Psr\Container\ContainerInterface;

/**
 * Main Cigg Template Engine
 */
class Engine
{
    private string $viewsPath;
    private string $cachePath;
    private array $globals = [];

    public function __construct(
        private ConfigBag $config,
        private Lexer $lexer,
        private Parser $parser,
        private Compiler $compiler,
        private LayoutManager $layoutManager,
    ) {
        $this->viewsPath = rtrim($config->get('template.paths.views'), '/');
        $this->cachePath = rtrim($config->get('template.paths.cache'), '/');

        // Ensure cache directory exists
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    public function getCachePath(): string
    {
        return $this->cachePath;
    }

    public function getViewsPath(): string
    {
        return $this->viewsPath;
    }

    public function resolveView(string $template): string
    {
        $templatePath = $this->viewsPath . '/' . str_replace('.', '/', $template) . '.cigg';

        if (!file_exists($templatePath)) {
            throw new \Exception("Template not found: {$templatePath}");
        }

        return $templatePath;
    }

    /**
     * Render a template
     */
    public function render(string $template, array $data = []): string
    {
        if (!$this->layoutManager->isRenderingLayout()) {
            $this->layoutManager->reset();
        }

        $templatePath = $this->resolveView($template);
        $cacheKey = md5($templatePath);
        $cachePath = $this->cachePath . '/' . $cacheKey . '.php';

        if ($this->isExpired($templatePath, $cachePath)) {
            $this->compileTemplate($templatePath, $cachePath);
        }

        $content = $this->renderCompiledTemplate($cachePath, array_merge($this->globals, $data));

        if ($layout = $this->layoutManager->getLayout()) {
            $this->layoutManager->clearLayout();
            $this->layoutManager->setIsRenderingLayout(true);
            $layoutContent = $this->render($layout, $data);
            $this->layoutManager->setIsRenderingLayout(false);
            return $layoutContent;
        }

        return $content;
    }

    private function isExpired(string $templatePath, string $cachePath): bool
    {
        if (!file_exists($cachePath)) {
            return true;
        }

        $env = $this->config->get('app.env', 'dev');
        if (!str_starts_with($env, 'prod')) {
            return true;
        }
        return filemtime($templatePath) > filemtime($cachePath);
    }

    public function compileTemplate(string $templatePath, string $cachePath): void
    {
        $content = file_get_contents($templatePath);
        $tokens = $this->lexer->tokenize($content);
        $ast = $this->parser->parse($tokens);
        $compiled = $this->compiler->compile($ast);
        file_put_contents($cachePath, $compiled);
    }

    public function addGlobal(string $key, $value): void
    {
        $this->globals[$key] = $value;
    }

    public function renderCompiledTemplate(string $cachePath, array $data): string
    {
        $data['__engine'] = $this;
        $data['__layoutManager'] = $this->layoutManager;

        extract($data);

        ob_start();
        include $cachePath;
        return ob_get_clean();
    }

    /**
     * Render a component
     */
    public function renderComponent(string $componentPath, array $attributes = [], string $slot = ''): string
    {
        // Look for the component template
        $componentTemplatePath = $this->viewsPath . '/components/' . str_replace('.', '/', $componentPath) . '.cigg';
        
        if (!file_exists($componentTemplatePath)) {
            // Try without the components directory (legacy support)
            $componentTemplatePath = $this->viewsPath . '/' . str_replace('.', '/', $componentPath) . '.cigg';
            
            if (!file_exists($componentTemplatePath)) {
                throw new \Exception("Component template not found: {$componentPath}");
            }
        }

        // Compile the component template if needed
        $cacheKey = md5($componentTemplatePath . serialize($attributes));
        $cachePath = $this->cachePath . '/component_' . $cacheKey . '.php';

        if ($this->isExpired($componentTemplatePath, $cachePath)) {
            $this->compileTemplate($componentTemplatePath, $cachePath);
        }

        // Prepare component data
        $componentData = array_merge($this->globals, $attributes, [
            '__slot' => $slot,
            '__attributes' => $attributes,
            '__engine' => $this,
            '__layoutManager' => $this->layoutManager,
        ]);

        // Render the component
        extract($componentData);
        ob_start();
        include $cachePath;
        return ob_get_clean();
    }
}
