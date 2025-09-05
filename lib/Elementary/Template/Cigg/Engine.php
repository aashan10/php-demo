<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg;

use Elementary\Template\Cigg\Lexer\Lexer;
use Elementary\Template\Cigg\Parser\Parser;
use Elementary\Template\Cigg\Compiler\Compiler;
use Elementary\Template\Cigg\Directives\DirectiveRegistry;
use Elementary\Template\Cigg\Directives\DirectiveInterface;

/**
 * Main Cigg Template Engine
 */
class Engine
{
    private Lexer $lexer;
    private Parser $parser;
    private Compiler $compiler;
    private DirectiveRegistry $directiveRegistry;
    
    private string $viewsPath;
    private string $cachePath;
    private array $globals = [];

    public function __construct(string $viewsPath, string $cachePath)
    {
        $this->viewsPath = rtrim($viewsPath, '/');
        $this->cachePath = rtrim($cachePath, '/');
        
        $this->lexer = new Lexer();
        $this->parser = new Parser();
        $this->directiveRegistry = new DirectiveRegistry();
        $this->compiler = new Compiler($this->directiveRegistry);
        
        // Ensure cache directory exists
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    /**
     * Render a template
     */
    public function render(string $template, array $data = []): string
    {
        $templatePath = $this->viewsPath . '/' . $template . '.cigg';
        
        if (!file_exists($templatePath)) {
            throw new \Exception("Template not found: {$template}");
        }

        $cacheKey = md5($templatePath);
        $cachePath = $this->cachePath . '/' . $cacheKey . '.php';
        
        // Check if we need to recompile
        if (!file_exists($cachePath) || filemtime($templatePath) > filemtime($cachePath)) {
            $this->compileTemplate($templatePath, $cachePath);
        }

        // Render the compiled template
        return $this->renderCompiledTemplate($cachePath, array_merge($this->globals, $data));
    }

    /**
     * Register a custom directive class
     */
    public function directive(DirectiveInterface $directive): void
    {
        $this->directiveRegistry->register($directive);
    }

    /**
     * Register a simple callable directive (Blade-style)
     */
    public function directiveCallable(string $name, callable $handler): void
    {
        $this->directiveRegistry->registerCallable($name, $handler);
    }

    /**
     * Add global variable
     */
    public function addGlobal(string $key, $value): void
    {
        $this->globals[$key] = $value;
    }

    private function compileTemplate(string $templatePath, string $cachePath): void
    {
        $content = file_get_contents($templatePath);
        
        // Lexing
        $tokens = $this->lexer->tokenize($content);
        
        // Parsing
        $ast = $this->parser->parse($tokens);

        // Compilation
        $compiled = $this->compiler->compile($ast);

        file_put_contents($cachePath, $compiled);
    }

    private function renderCompiledTemplate(string $cachePath, array $data): string
    {
        extract($data);
        
        ob_start();
        include $cachePath;
        return ob_get_clean();
    }
}
