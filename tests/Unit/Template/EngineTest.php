<?php

declare(strict_types=1);

namespace Tests\Unit\Template;

use Elementary\Config\ConfigBag;
use Elementary\Template\Cigg\Engine;
use Elementary\Template\Cigg\LayoutManager;
use Elementary\Template\Cigg\Lexer\Lexer;
use Elementary\Template\Cigg\Parser\Parser;
use Elementary\Template\Cigg\Compiler\Compiler;
use Elementary\Template\Cigg\Directives\DirectiveRegistry;
use Tests\Support\TestCase;

class EngineTest extends TestCase
{
    private Engine $engine;
    private string $tempDir;
    private string $viewsDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tempDir = sys_get_temp_dir() . '/cigg_test_' . uniqid();
        $this->viewsDir = $this->tempDir . '/views';
        
        mkdir($this->tempDir);
        mkdir($this->viewsDir);
        mkdir($this->tempDir . '/cache');
        mkdir($this->tempDir . '/config');

        // Create config files
        file_put_contents($this->tempDir . '/config/template.php', "<?php\nreturn " . var_export([
            'paths' => [
                'views' => $this->viewsDir,
                'cache' => $this->tempDir . '/cache'
            ]
        ], true) . ';');

        file_put_contents($this->tempDir . '/config/app.php', "<?php\nreturn " . var_export([
            'env' => 'dev'
        ], true) . ';');

        $config = new ConfigBag($this->tempDir . '/config');
        
        $directiveRegistry = new DirectiveRegistry();

        $this->engine = new Engine(
            $config,
            new Lexer(),
            new Parser($directiveRegistry),
            new Compiler($directiveRegistry),
            new LayoutManager()
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testGetCachePath(): void
    {
        $expectedPath = $this->tempDir . '/cache';
        $this->assertEquals($expectedPath, $this->engine->getCachePath());
    }

    public function testGetViewsPath(): void
    {
        $this->assertEquals($this->viewsDir, $this->engine->getViewsPath());
    }

    public function testResolveViewThrowsExceptionForNonexistentTemplate(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Template not found');
        
        $this->engine->resolveView('nonexistent');
    }

    public function testResolveViewReturnsCorrectPath(): void
    {
        $templateContent = '<h1>Test</h1>';
        file_put_contents($this->viewsDir . '/test.cigg', $templateContent);
        clearstatcache();
        
        $resolvedPath = $this->engine->resolveView('test');
        
        $this->assertEquals($this->viewsDir . '/test.cigg', $resolvedPath);
    }

    public function testResolveViewHandlesDottedNotation(): void
    {
        mkdir($this->viewsDir . '/auth');
        $templateContent = '<form>Login</form>';
        file_put_contents($this->viewsDir . '/auth/login.cigg', $templateContent);
        clearstatcache();
        
        $resolvedPath = $this->engine->resolveView('auth.login');
        
        $this->assertEquals($this->viewsDir . '/auth/login.cigg', $resolvedPath);
    }

    public function testAddGlobal(): void
    {
        $templateContent = '{{ $globalVar }}';
        file_put_contents($this->viewsDir . '/test.cigg', $templateContent);
        clearstatcache();
        
        $this->engine->addGlobal('globalVar', 'global value');
        
        $result = $this->engine->render('test');
        $this->assertStringContainsString('global value', $result);
    }

    public function testRenderWithData(): void
    {
        $templateContent = 'Hello {{ $name }}!';
        file_put_contents($this->viewsDir . '/greeting.cigg', $templateContent);
        clearstatcache();
        
        $result = $this->engine->render('greeting', ['name' => 'World']);
        
        $this->assertStringContainsString('Hello World!', $result);
    }

    public function testCompileTemplate(): void
    {
        $templateContent = '<p>{{ $message }}</p>';
        $templatePath = $this->viewsDir . '/simple.cigg';
        $cachePath = $this->tempDir . '/cache/test.php';
        
        file_put_contents($templatePath, $templateContent);
        clearstatcache();
        
        $this->engine->compileTemplate($templatePath, $cachePath);
        
        $this->assertFileExists($cachePath);
        $compiledContent = file_get_contents($cachePath);
        $this->assertStringContainsString('<?php', $compiledContent);
    }

    public function testRenderCompiledTemplate(): void
    {
        $compiledContent = '<?php echo htmlspecialchars($message ?? \'\', ENT_QUOTES, \'UTF-8\'); ?>';
        $cachePath = $this->tempDir . '/cache/compiled.php';
        
        file_put_contents($cachePath, $compiledContent);
        clearstatcache();
        
        $result = $this->engine->renderCompiledTemplate($cachePath, ['message' => 'Hello']);
        
        $this->assertEquals('Hello', $result);
    }

    public function testRenderComponentThrowsExceptionForNonexistentComponent(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Component template not found');
        
        $this->engine->renderComponent('nonexistent');
    }

    public function testRenderComponent(): void
    {
        mkdir($this->viewsDir . '/components');
        $componentContent = '<button class="{{ $class }}">{{ $__slot }}</button>';
        file_put_contents($this->viewsDir . '/components/button.cigg', $componentContent);
        clearstatcache();
        
        $result = $this->engine->renderComponent('button', ['class' => 'btn'], 'Click me');
        
        $this->assertStringContainsString('btn', $result);
        $this->assertStringContainsString('Click me', $result);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
}
