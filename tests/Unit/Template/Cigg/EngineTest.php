<?php

declare(strict_types=1);

namespace Tests\Unit\Template\Cigg;

use Elementary\Config\ConfigBag;
use Elementary\Template\Cigg\Engine;
use Elementary\Template\Cigg\Lexer\Lexer;
use Elementary\Template\Cigg\Parser\Parser;
use Elementary\Template\Cigg\Compiler\Compiler;
use Elementary\Template\Cigg\LayoutManager;
use PHPUnit\Framework\TestCase;

class EngineTest extends TestCase
{
    private ConfigBag $config;
    private Lexer $lexer;
    private Parser $parser;
    private Compiler $compiler;
    private LayoutManager $layoutManager;
    private Engine $engine;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create temporary directories for testing
        $this->tempDir = sys_get_temp_dir() . '/cigg_test_' . uniqid();
        mkdir($this->tempDir . '/views', 0755, true);
        mkdir($this->tempDir . '/cache', 0755, true);
        
        $this->config = $this->createMock(ConfigBag::class);
        $this->lexer = $this->createMock(Lexer::class);
        $this->parser = $this->createMock(Parser::class);
        $this->compiler = $this->createMock(Compiler::class);
        $this->layoutManager = $this->createMock(LayoutManager::class);
        
        $this->setupDefaultConfigMock();
        
        $this->engine = new Engine(
            $this->config,
            $this->lexer,
            $this->parser,
            $this->compiler,
            $this->layoutManager
        );
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        if (is_dir($this->tempDir)) {
            $this->recursiveDelete($this->tempDir);
        }
        parent::tearDown();
    }

    private function setupDefaultConfigMock(): void
    {
        $this->config->method('get')
            ->willReturnMap([
                ['template.paths.views', null, $this->tempDir . '/views'],
                ['template.paths.cache', null, $this->tempDir . '/cache'],
                ['app.env', 'dev', 'dev']
            ]);
    }

    private function recursiveDelete(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_constructor_creates_cache_directory()
    {
        $nonExistentCache = $this->tempDir . '/new_cache';
        
        $config = $this->createMock(ConfigBag::class);
        $config->method('get')
            ->willReturnMap([
                ['template.paths.views', null, $this->tempDir . '/views'],
                ['template.paths.cache', null, $nonExistentCache]
            ]);
        
        $engine = new Engine(
            $config,
            $this->lexer,
            $this->parser,
            $this->compiler,
            $this->layoutManager
        );
        
        $this->assertTrue(is_dir($nonExistentCache));
        $this->assertEquals($nonExistentCache, $engine->getCachePath());
    }

    public function test_get_cache_path()
    {
        $expected = $this->tempDir . '/cache';
        $this->assertEquals($expected, $this->engine->getCachePath());
    }

    public function test_get_views_path()
    {
        $expected = $this->tempDir . '/views';
        $this->assertEquals($expected, $this->engine->getViewsPath());
    }

    public function test_resolve_view_converts_dots_to_slashes()
    {
        $templateContent = 'Hello {{ $name }}';
        file_put_contents($this->tempDir . '/views/users/profile.cigg', $templateContent);
        
        $resolved = $this->engine->resolveView('users.profile');
        
        $expected = $this->tempDir . '/views/users/profile.cigg';
        $this->assertEquals($expected, $resolved);
    }

    public function test_resolve_view_throws_exception_for_missing_template()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Template not found:');
        
        $this->engine->resolveView('nonexistent.template');
    }

    public function test_add_global_variable()
    {
        $this->engine->addGlobal('app_name', 'My App');
        $this->engine->addGlobal('version', '1.0.0');
        
        // We can't directly test globals, but we can test they're passed to renderCompiledTemplate
        $cacheFile = $this->tempDir . '/cache/test.php';
        file_put_contents($cacheFile, '<?php echo $app_name ?? "default"; ?> v<?php echo $version ?? "0"; ?>');
        
        $result = $this->engine->renderCompiledTemplate($cacheFile, []);
        
        $this->assertEquals('My App v1.0.0', $result);
    }

    public function test_render_compiled_template_with_data()
    {
        $cacheFile = $this->tempDir . '/cache/test.php';
        file_put_contents($cacheFile, 'Hello <?php echo $name ?? "World"; ?>!');
        
        $result = $this->engine->renderCompiledTemplate($cacheFile, ['name' => 'John']);
        
        $this->assertEquals('Hello John!', $result);
    }

    public function test_render_compiled_template_includes_engine_and_layout_manager()
    {
        $cacheFile = $this->tempDir . '/cache/test.php';
        file_put_contents($cacheFile, '<?php echo isset($__engine) ? "engine" : "no-engine"; ?><?php echo isset($__layoutManager) ? "-layout" : "-no-layout"; ?>');
        
        $result = $this->engine->renderCompiledTemplate($cacheFile, []);
        
        $this->assertEquals('engine-layout', $result);
    }

    public function test_compile_template()
    {
        $templatePath = $this->tempDir . '/views/test.cigg';
        $cachePath = $this->tempDir . '/cache/compiled.php';
        $templateContent = 'Hello {{ $name }}';
        $compiledContent = 'Hello <?php echo htmlspecialchars($name, ENT_QUOTES, \'UTF-8\'); ?>';
        
        file_put_contents($templatePath, $templateContent);
        
        // Setup mocks for compilation process
        $this->lexer->expects($this->once())
                   ->method('tokenize')
                   ->with($templateContent)
                   ->willReturn(['mock_tokens']);
        
        $mockAst = $this->createMock(\Elementary\Template\Cigg\AST\Node::class);
        $this->parser->expects($this->once())
                    ->method('parse')
                    ->with(['mock_tokens'])
                    ->willReturn($mockAst);
        
        $this->compiler->expects($this->once())
                      ->method('compile')
                      ->with($mockAst)
                      ->willReturn($compiledContent);
        
        $this->engine->compileTemplate($templatePath, $cachePath);
        
        $this->assertFileExists($cachePath);
        $this->assertEquals($compiledContent, file_get_contents($cachePath));
    }

    public function test_is_expired_returns_true_in_dev_environment()
    {
        $templatePath = $this->tempDir . '/views/test.cigg';
        $cachePath = $this->tempDir . '/cache/test.php';
        
        file_put_contents($templatePath, 'template content');
        file_put_contents($cachePath, 'cached content');
        
        // Make cache file newer than template
        touch($cachePath, time() + 100);
        
        // Should still be expired in dev environment
        $reflection = new \ReflectionClass($this->engine);
        $method = $reflection->getMethod('isExpired');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->engine, $templatePath, $cachePath);
        $this->assertTrue($result);
    }

    public function test_is_expired_checks_filemtime_in_production()
    {
        $config = $this->createMock(ConfigBag::class);
        $config->method('get')
            ->willReturnMap([
                ['template.paths.views', null, $this->tempDir . '/views'],
                ['template.paths.cache', null, $this->tempDir . '/cache'],
                ['app.env', 'dev', 'production']
            ]);
        
        $engine = new Engine(
            $config,
            $this->lexer,
            $this->parser,
            $this->compiler,
            $this->layoutManager
        );
        
        $templatePath = $this->tempDir . '/views/test.cigg';
        $cachePath = $this->tempDir . '/cache/test.php';
        
        file_put_contents($templatePath, 'template content');
        file_put_contents($cachePath, 'cached content');
        
        // Make cache newer than template
        touch($cachePath, time() + 100);
        
        $reflection = new \ReflectionClass($engine);
        $method = $reflection->getMethod('isExpired');
        $method->setAccessible(true);
        
        $result = $method->invoke($engine, $templatePath, $cachePath);
        $this->assertFalse($result);
        
        // Make template newer than cache
        touch($templatePath, time() + 200);
        
        $result = $method->invoke($engine, $templatePath, $cachePath);
        $this->assertTrue($result);
    }

    public function test_is_expired_returns_true_when_cache_file_missing()
    {
        $templatePath = $this->tempDir . '/views/test.cigg';
        $cachePath = $this->tempDir . '/cache/nonexistent.php';
        
        file_put_contents($templatePath, 'template content');
        
        $reflection = new \ReflectionClass($this->engine);
        $method = $reflection->getMethod('isExpired');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->engine, $templatePath, $cachePath);
        $this->assertTrue($result);
    }

    public function test_render_compiles_and_renders_template()
    {
        $templatePath = $this->tempDir . '/views/greeting.cigg';
        $templateContent = 'Hello {{ $name }}!';
        $compiledContent = '<?php echo "Hello " . htmlspecialchars($name ?? "", ENT_QUOTES, \'UTF-8\') . "!"; ?>';
        
        file_put_contents($templatePath, $templateContent);
        
        // Setup layout manager mock
        $this->layoutManager->method('isRenderingLayout')->willReturn(false);
        $this->layoutManager->method('getLayout')->willReturn(null);
        
        // Setup compilation mocks
        $mockAst = $this->createMock(\Elementary\Template\Cigg\AST\Node::class);
        $this->lexer->method('tokenize')
                   ->with($templateContent)
                   ->willReturn(['mock_tokens']);
        
        $this->parser->method('parse')
                    ->with(['mock_tokens'])
                    ->willReturn($mockAst);
        
        $this->compiler->method('compile')
                      ->with($mockAst)
                      ->willReturn($compiledContent);
        
        $result = $this->engine->render('greeting', ['name' => 'World']);
        
        $this->assertEquals('Hello World!', $result);
    }

    public function test_render_with_layout()
    {
        $templatePath = $this->tempDir . '/views/content.cigg';
        $layoutPath = $this->tempDir . '/views/layout.cigg';
        
        file_put_contents($templatePath, 'Content: {{ $message }}');
        file_put_contents($layoutPath, 'Layout: Content goes here');
        
        $compiledContent = '<?php echo "Content: " . htmlspecialchars($message ?? "", ENT_QUOTES, \'UTF-8\'); ?>';
        $compiledLayout = '<?php echo "Layout: Content goes here"; ?>';
        
        // Setup layout manager mock
        $this->layoutManager->method('isRenderingLayout')
                           ->willReturnOnConsecutiveCalls(false, false);
        
        $this->layoutManager->method('getLayout')
                           ->willReturnOnConsecutiveCalls('layout', null);
        
        $this->layoutManager->expects($this->once())
                           ->method('clearLayout');
        
        $this->layoutManager->expects($this->exactly(2))
                           ->method('setIsRenderingLayout')
                           ->with($this->callback(function($arg) {
                               static $callCount = 0;
                               $callCount++;
                               return ($callCount === 1 && $arg === true) || ($callCount === 2 && $arg === false);
                           }));
        
        // Setup compilation mocks
        $contentAst = $this->createMock(\Elementary\Template\Cigg\AST\Node::class);
        $layoutAst = $this->createMock(\Elementary\Template\Cigg\AST\Node::class);
        
        $this->lexer->method('tokenize')
                   ->willReturnMap([
                       ['Content: {{ $message }}', ['content_tokens']],
                       ['Layout: Content goes here', ['layout_tokens']]
                   ]);
        
        $this->parser->method('parse')
                    ->willReturnMap([
                        [['content_tokens'], $contentAst],
                        [['layout_tokens'], $layoutAst]
                    ]);
        
        $this->compiler->method('compile')
                      ->willReturnMap([
                          [$contentAst, $compiledContent],
                          [$layoutAst, $compiledLayout]
                      ]);
        
        $result = $this->engine->render('content', ['message' => 'Hello']);
        
        $this->assertEquals('Layout: Content goes here', $result);
    }

    public function test_render_component_self_closing()
    {
        $componentPath = $this->tempDir . '/views/components/button.cigg';
        $componentContent = '<button>{{ $text ?? "Click me" }}</button>';
        $compiledContent = '<?php echo "<button>" . htmlspecialchars($text ?? "Click me", ENT_QUOTES, \'UTF-8\') . "</button>"; ?>';
        
        file_put_contents($componentPath, $componentContent);
        
        // Setup layout manager mock
        $this->layoutManager->method('isRenderingLayout')->willReturn(false);
        
        // Setup compilation mocks
        $componentAst = $this->createMock(\Elementary\Template\Cigg\AST\Node::class);
        $this->lexer->method('tokenize')
                   ->with($componentContent)
                   ->willReturn(['component_tokens']);
        
        $this->parser->method('parse')
                    ->with(['component_tokens'])
                    ->willReturn($componentAst);
        
        $this->compiler->method('compile')
                      ->with('component_ast')
                      ->willReturn($compiledContent);
        
        $result = $this->engine->renderComponent('button', ['text' => 'Submit']);
        
        $this->assertEquals('<button>Submit</button>', $result);
    }

    public function test_render_component_with_slot_content()
    {
        $componentPath = $this->tempDir . '/views/components/card.cigg';
        $componentContent = '<div class="card">{{ $__slot }}</div>';
        $compiledContent = '<?php echo "<div class=\"card\">" . ($__slot ?? "") . "</div>"; ?>';
        
        file_put_contents($componentPath, $componentContent);
        
        // Setup layout manager mock
        $this->layoutManager->method('isRenderingLayout')->willReturn(false);
        
        // Setup compilation mocks
        $componentAst = $this->createMock(\Elementary\Template\Cigg\AST\Node::class);
        $this->lexer->method('tokenize')
                   ->with($componentContent)
                   ->willReturn(['component_tokens']);
        
        $this->parser->method('parse')
                    ->with(['component_tokens'])
                    ->willReturn($componentAst);
        
        $this->compiler->method('compile')
                      ->with('component_ast')
                      ->willReturn($compiledContent);
        
        $result = $this->engine->renderComponent('card', ['title' => 'My Card'], 'Card content here');
        
        $this->assertEquals('<div class="card">Card content here</div>', $result);
    }

    public function test_render_component_fallback_to_non_components_directory()
    {
        // Component not in components/ directory
        $componentPath = $this->tempDir . '/views/legacy-component.cigg';
        $componentContent = 'Legacy: {{ $value }}';
        $compiledContent = '<?php echo "Legacy: " . htmlspecialchars($value ?? "", ENT_QUOTES, \'UTF-8\'); ?>';
        
        file_put_contents($componentPath, $componentContent);
        
        // Setup layout manager mock
        $this->layoutManager->method('isRenderingLayout')->willReturn(false);
        
        // Setup compilation mocks
        $componentAst = $this->createMock(\Elementary\Template\Cigg\AST\Node::class);
        $this->lexer->method('tokenize')
                   ->with($componentContent)
                   ->willReturn(['component_tokens']);
        
        $this->parser->method('parse')
                    ->with(['component_tokens'])
                    ->willReturn($componentAst);
        
        $this->compiler->method('compile')
                      ->with('component_ast')
                      ->willReturn($compiledContent);
        
        $result = $this->engine->renderComponent('legacy-component', ['value' => 'test']);
        
        $this->assertEquals('Legacy: test', $result);
    }

    public function test_render_component_throws_exception_for_missing_template()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Component template not found: nonexistent');
        
        $this->engine->renderComponent('nonexistent', []);
    }

    public function test_render_uses_cache_when_not_expired()
    {
        $templatePath = $this->tempDir . '/views/cached.cigg';
        $templateContent = 'Original: {{ $value }}';
        
        file_put_contents($templatePath, $templateContent);
        
        // Pre-create cache file
        $cacheHash = md5($templatePath);
        $cachePath = $this->tempDir . '/cache/' . $cacheHash . '.php';
        $cachedContent = '<?php echo "Cached: " . htmlspecialchars($value ?? "", ENT_QUOTES, \'UTF-8\'); ?>';
        file_put_contents($cachePath, $cachedContent);
        
        // Setup layout manager mock
        $this->layoutManager->method('isRenderingLayout')->willReturn(false);
        $this->layoutManager->method('getLayout')->willReturn(null);
        
        // Configure for production environment (cache should be used)
        $config = $this->createMock(ConfigBag::class);
        $config->method('get')
            ->willReturnMap([
                ['template.paths.views', null, $this->tempDir . '/views'],
                ['template.paths.cache', null, $this->tempDir . '/cache'],
                ['app.env', 'dev', 'production']
            ]);
        
        $engine = new Engine(
            $config,
            $this->lexer,
            $this->parser,
            $this->compiler,
            $this->layoutManager
        );
        
        // Make cache newer than template
        touch($cachePath, time() + 100);
        
        // Compilation methods should NOT be called since cache is used
        $this->lexer->expects($this->never())->method('tokenize');
        $this->parser->expects($this->never())->method('parse');
        $this->compiler->expects($this->never())->method('compile');
        
        $result = $engine->render('cached', ['value' => 'test']);
        
        $this->assertEquals('Cached: test', $result);
    }

    public function test_render_recompiles_when_expired()
    {
        $templatePath = $this->tempDir . '/views/recompile.cigg';
        $templateContent = 'Fresh: {{ $value }}';
        
        file_put_contents($templatePath, $templateContent);
        
        // Pre-create old cache file
        $cacheHash = md5($templatePath);
        $cachePath = $this->tempDir . '/cache/' . $cacheHash . '.php';
        file_put_contents($cachePath, '<?php echo "Old cache"; ?>');
        
        // Setup layout manager mock
        $this->layoutManager->method('isRenderingLayout')->willReturn(false);
        $this->layoutManager->method('getLayout')->willReturn(null);
        
        $compiledContent = '<?php echo "Fresh: " . htmlspecialchars($value ?? "", ENT_QUOTES, \'UTF-8\'); ?>';
        
        // Setup compilation mocks - should be called since cache is expired
        $ast = $this->createMock(\Elementary\Template\Cigg\AST\Node::class);
        $this->lexer->expects($this->once())
                   ->method('tokenize')
                   ->with($templateContent)
                   ->willReturn(['tokens']);
        
        $this->parser->expects($this->once())
                    ->method('parse')
                    ->with(['tokens'])
                    ->willReturn($ast);
        
        $this->compiler->expects($this->once())
                      ->method('compile')
                      ->with($ast)
                      ->willReturn($compiledContent);
        
        $result = $this->engine->render('recompile', ['value' => 'new']);
        
        $this->assertEquals('Fresh: new', $result);
    }

    public function test_engine_handles_complex_template_compilation_flow()
    {
        $templatePath = $this->tempDir . '/views/complex.cigg';
        $templateContent = '@if($show) Hello {{ $name }}! @endif';
        
        file_put_contents($templatePath, $templateContent);
        
        // Setup layout manager mock
        $this->layoutManager->method('isRenderingLayout')->willReturn(false);
        $this->layoutManager->method('getLayout')->willReturn(null);
        
        $compiledContent = '<?php if($show ?? false): ?>Hello <?php echo htmlspecialchars($name ?? "", ENT_QUOTES, \'UTF-8\'); ?>!<?php endif; ?>';
        
        // Setup compilation mocks
        $complexAst = $this->createMock(\Elementary\Template\Cigg\AST\Node::class);
        $this->lexer->method('tokenize')
                   ->with($templateContent)
                   ->willReturn(['directive_tokens', 'text_tokens', 'echo_tokens']);
        
        $this->parser->method('parse')
                    ->with(['directive_tokens', 'text_tokens', 'echo_tokens'])
                    ->willReturn($complexAst);
        
        $this->compiler->method('compile')
                      ->with($complexAst)
                      ->willReturn($compiledContent);
        
        $result = $this->engine->render('complex', ['show' => true, 'name' => 'World']);
        
        $this->assertEquals('Hello World!', $result);
    }
}