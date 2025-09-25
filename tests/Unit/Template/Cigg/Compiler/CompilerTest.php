<?php

declare(strict_types=1);

namespace Tests\Unit\Template\Cigg\Compiler;

use Elementary\Template\Cigg\Compiler\Compiler;
use Elementary\Template\Cigg\Directives\DirectiveRegistry;
use Elementary\Template\Cigg\Directives\DirectiveInterface;
use Elementary\Template\Cigg\AST\DocumentNode;
use Elementary\Template\Cigg\AST\TextNode;
use Elementary\Template\Cigg\AST\EchoNode;
use Elementary\Template\Cigg\AST\DirectiveNode;
use Elementary\Template\Cigg\AST\ComponentNode;
use PHPUnit\Framework\TestCase;

class CompilerTest extends TestCase
{
    private DirectiveRegistry $registry;
    private Compiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new DirectiveRegistry();
        $this->compiler = new Compiler($this->registry);
    }

    public function test_compile_empty_document()
    {
        $ast = new DocumentNode([]);
        
        $result = $this->compiler->compile($ast);
        
        $this->assertEquals('', $result);
    }

    public function test_compile_text_node()
    {
        $ast = new DocumentNode([
            new TextNode('Hello, World!')
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $this->assertEquals('Hello, World!', $result);
    }

    public function test_compile_multiple_text_nodes()
    {
        $ast = new DocumentNode([
            new TextNode('First '),
            new TextNode('Second '),
            new TextNode('Third')
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $this->assertEquals('First Second Third', $result);
    }

    public function test_compile_echo_node_escaped()
    {
        $ast = new DocumentNode([
            new EchoNode('$name')
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $this->assertEquals("<?php echo htmlspecialchars(\$name, ENT_QUOTES, 'UTF-8'); ?>", $result);
    }

    public function test_compile_echo_node_raw()
    {
        $ast = new DocumentNode([
            new EchoNode('$htmlContent', true)
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $this->assertEquals("<?php echo \$htmlContent; ?>", $result);
    }

    public function test_compile_mixed_content()
    {
        $ast = new DocumentNode([
            new TextNode('Hello '),
            new EchoNode('$name'),
            new TextNode(', welcome!')
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $expected = "Hello <?php echo htmlspecialchars(\$name, ENT_QUOTES, 'UTF-8'); ?>, welcome!";
        $this->assertEquals($expected, $result);
    }

    public function test_compile_simple_directive()
    {
        $ast = new DocumentNode([
            new DirectiveNode('csrf')
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $this->assertEquals('<input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">', $result);
    }

    public function test_compile_json_directive()
    {
        $ast = new DocumentNode([
            new DirectiveNode('json', '$data')
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $this->assertEquals('<?php echo json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>', $result);
    }

    public function test_compile_if_directive_block()
    {
        $ast = new DocumentNode([
            new DirectiveNode('if', '$condition', [
                new TextNode('Inside if block'),
                new DirectiveNode('endif')
            ])
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $expected = '<?php if($condition): ?>Inside if block<?php endif; ?>';
        $this->assertEquals($expected, $result);
    }

    public function test_compile_unless_directive_block()
    {
        $ast = new DocumentNode([
            new DirectiveNode('unless', '$condition', [
                new TextNode('Inside unless block'),
                new DirectiveNode('endunless')
            ])
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $expected = '<?php if(!($condition)): ?>Inside unless block<?php endif; ?>';
        $this->assertEquals($expected, $result);
    }

    public function test_compile_foreach_directive_block()
    {
        $ast = new DocumentNode([
            new DirectiveNode('foreach', '$items as $item', [
                new TextNode('Item: '),
                new EchoNode('$item'),
                new DirectiveNode('endforeach')
            ])
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $expected = '<?php foreach($items as $item): ?>Item: <?php echo htmlspecialchars($item, ENT_QUOTES, \'UTF-8\'); ?><?php endforeach; ?>';
        $this->assertEquals($expected, $result);
    }

    public function test_compile_section_directive()
    {
        $ast = new DocumentNode([
            new DirectiveNode('section', '"content"', [
                new TextNode('Section content'),
                new DirectiveNode('endsection')
            ])
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $expected = '<?php $__layoutManager->startSection("content"); ?>Section content<?php $__layoutManager->endSection(); ?>';
        $this->assertEquals($expected, $result);
    }

    public function test_compile_nested_directives()
    {
        $ast = new DocumentNode([
            new DirectiveNode('if', '$showContent', [
                new TextNode('Outer content'),
                new DirectiveNode('foreach', '$items as $item', [
                    new TextNode('Inner: '),
                    new EchoNode('$item'),
                    new DirectiveNode('endforeach')
                ]),
                new DirectiveNode('endif')
            ])
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $expected = '<?php if($showContent): ?>Outer content<?php foreach($items as $item): ?>Inner: <?php echo htmlspecialchars($item, ENT_QUOTES, \'UTF-8\'); ?><?php endforeach; ?><?php endif; ?>';
        $this->assertEquals($expected, $result);
    }

    public function test_compile_loop_directive_with_times()
    {
        $ast = new DocumentNode([
            new DirectiveNode('loop', '5 times', [
                new TextNode('Loop iteration'),
                new DirectiveNode('endloop')
            ])
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $this->assertStringContainsString('<?php for($_i = 0; $_i < 5; $_i++): ?>', $result);
        $this->assertStringContainsString('$loop = [', $result);
        $this->assertStringContainsString('Loop iteration', $result);
        $this->assertStringContainsString('<?php endfor; ?>', $result);
    }

    public function test_compile_elseif_and_else_directives()
    {
        $ast = new DocumentNode([
            new DirectiveNode('if', '$condition1', [
                new TextNode('First condition'),
                new DirectiveNode('elseif', '$condition2'),
                new TextNode('Second condition'),
                new DirectiveNode('else'),
                new TextNode('Default case'),
                new DirectiveNode('endif')
            ])
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $this->assertStringContainsString('<?php if($condition1): ?>', $result);
        $this->assertStringContainsString('<?php elseif($condition2): ?>', $result);
        $this->assertStringContainsString('<?php else: ?>', $result);
        $this->assertStringContainsString('<?php endif; ?>', $result);
    }

    public function test_compile_self_closing_component()
    {
        $attributes = [
            'type' => ['value' => 'submit', 'dynamic' => false],
            'class' => ['value' => 'btn btn-primary', 'dynamic' => false]
        ];
        
        $ast = new DocumentNode([
            new ComponentNode('button', $attributes)
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $this->assertStringContainsString('// Component: button', $result);
        $this->assertStringContainsString("'type' => 'submit'", $result);
        $this->assertStringContainsString("'class' => 'btn btn-primary'", $result);
        $this->assertStringContainsString('$__componentPath = \'button\'', $result);
        $this->assertStringContainsString('echo $this->renderComponent', $result);
    }

    public function test_compile_component_with_dynamic_attributes()
    {
        $attributes = [
            'id' => ['value' => 'static-id', 'dynamic' => false],
            'class' => ['value' => '$dynamicClass', 'dynamic' => true],
            'disabled' => ['value' => '$isDisabled', 'dynamic' => true]
        ];
        
        $ast = new DocumentNode([
            new ComponentNode('input', $attributes)
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $this->assertStringContainsString("'id' => 'static-id'", $result);
        $this->assertStringContainsString("'class' => \$dynamicClass", $result);
        $this->assertStringContainsString("'disabled' => \$isDisabled", $result);
    }

    public function test_compile_component_with_slot()
    {
        $slot = new DocumentNode([
            new TextNode('Slot content: '),
            new EchoNode('$variable')
        ]);
        
        $ast = new DocumentNode([
            new ComponentNode('card', [], $slot)
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $this->assertStringContainsString('$__slot = function() { ob_start(); ?>', $result);
        $this->assertStringContainsString('Slot content: ', $result);
        $this->assertStringContainsString('<?php echo htmlspecialchars($variable', $result);
        $this->assertStringContainsString('<?php return ob_get_clean(); };', $result);
    }

    public function test_compile_custom_directive_class()
    {
        $customDirective = $this->createMock(DirectiveInterface::class);
        $customDirective->method('getName')->willReturn('custom');
        $customDirective->method('compile')
                       ->with($this->isInstanceOf(DirectiveNode::class))
                       ->willReturn('<?php /* custom directive */ ?>');
        
        $this->registry->register($customDirective);
        
        $ast = new DocumentNode([
            new DirectiveNode('custom', 'expression')
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $this->assertEquals('<?php /* custom directive */ ?>', $result);
    }

    public function test_compile_custom_callable_directive()
    {
        $callable = function($expression, $raw) {
            return "<?php custom_function({$expression}); ?>";
        };
        
        $this->registry->registerCallable('customCallable', $callable);
        
        $ast = new DocumentNode([
            new DirectiveNode('customCallable', '$data')
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $this->assertEquals('<?php custom_function($data); ?>', $result);
    }

    public function test_compile_unknown_directive()
    {
        $ast = new DocumentNode([
            new DirectiveNode('unknownDirective', '$expression')
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $this->assertEquals('<?php /* Unknown directive: unknownDirective */ ?>', $result);
    }

    public function test_compile_production_directive()
    {
        $ast = new DocumentNode([
            new DirectiveNode('production', '', [
                new TextNode('Production only content'),
                new DirectiveNode('endproduction')
            ])
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $expected = '<?php if(app()->environment("production")): ?>Production only content<?php endif; ?>';
        $this->assertEquals($expected, $result);
    }

    public function test_compile_dump_and_dd_directives()
    {
        $dumpAst = new DocumentNode([
            new DirectiveNode('dump', '$variable')
        ]);
        
        $ddAst = new DocumentNode([
            new DirectiveNode('dd', '$variable')
        ]);
        
        $dumpResult = $this->compiler->compile($dumpAst);
        $ddResult = $this->compiler->compile($ddAst);
        
        $this->assertEquals('<?php var_dump($variable); ?>', $dumpResult);
        $this->assertEquals('<?php var_dump($variable); die(); ?>', $ddResult);
    }

    public function test_compile_break_and_continue_directives()
    {
        $breakAst = new DocumentNode([
            new DirectiveNode('break')
        ]);
        
        $continueAst = new DocumentNode([
            new DirectiveNode('continue')
        ]);
        
        $breakResult = $this->compiler->compile($breakAst);
        $continueResult = $this->compiler->compile($continueAst);
        
        $this->assertEquals('<?php break; ?>', $breakResult);
        $this->assertEquals('<?php continue; ?>', $continueResult);
    }

    public function test_directive_registration_via_compiler()
    {
        $customDirective = $this->createMock(DirectiveInterface::class);
        $customDirective->method('getName')->willReturn('test');
        $customDirective->method('setCompiler')
                       ->with($this->compiler)
                       ->willReturnSelf();
        
        $this->compiler->directive($customDirective);
        
        $this->assertTrue($this->compiler->getDirectiveRegistry()->has('test'));
    }

    public function test_callable_directive_registration_via_compiler()
    {
        $callable = fn($expr) => "test: {$expr}";
        
        $this->compiler->directiveCallable('testCallable', $callable);
        
        $this->assertTrue($this->compiler->getDirectiveRegistry()->has('testCallable'));
        $this->assertSame($callable, $this->compiler->getDirectiveRegistry()->getCallable('testCallable'));
    }

    public function test_is_block_directive_method()
    {
        $this->assertTrue($this->compiler->isBlockDirective('if'));
        $this->assertTrue($this->compiler->isBlockDirective('foreach'));
        $this->assertTrue($this->compiler->isBlockDirective('section'));
        $this->assertTrue($this->compiler->isBlockDirective('endif'));
        $this->assertTrue($this->compiler->isBlockDirective('else'));
        
        $this->assertFalse($this->compiler->isBlockDirective('csrf'));
        $this->assertFalse($this->compiler->isBlockDirective('json'));
        $this->assertFalse($this->compiler->isBlockDirective('dump'));
    }

    public function test_is_ending_directive_method()
    {
        $this->assertTrue($this->compiler->isEndingDirective('endif'));
        $this->assertTrue($this->compiler->isEndingDirective('endforeach'));
        $this->assertTrue($this->compiler->isEndingDirective('endsection'));
        $this->assertTrue($this->compiler->isEndingDirective('else'));
        $this->assertTrue($this->compiler->isEndingDirective('elseif'));
        
        $this->assertFalse($this->compiler->isEndingDirective('if'));
        $this->assertFalse($this->compiler->isEndingDirective('foreach'));
        $this->assertFalse($this->compiler->isEndingDirective('csrf'));
    }

    public function test_get_directive_precedence()
    {
        $this->assertEquals(100, $this->compiler->getDirectivePrecedence('extends'));
        $this->assertEquals(100, $this->compiler->getDirectivePrecedence('section'));
        $this->assertEquals(50, $this->compiler->getDirectivePrecedence('if'));
        $this->assertEquals(50, $this->compiler->getDirectivePrecedence('foreach'));
        $this->assertEquals(10, $this->compiler->getDirectivePrecedence('echo'));
        $this->assertEquals(10, $this->compiler->getDirectivePrecedence('json'));
        $this->assertEquals(1, $this->compiler->getDirectivePrecedence('unknown'));
    }

    public function test_validate_directive_nesting()
    {
        $validDirective = new DirectiveNode('if', '$condition', [
            new TextNode('content'),
            new DirectiveNode('endif')
        ]);
        
        $errors = $this->compiler->validateDirectiveNesting($validDirective);
        $this->assertEmpty($errors);
        
        $invalidDirective = new DirectiveNode('if', '$condition', [
            new TextNode('content'),
            new DirectiveNode('endforeach') // Wrong ending
        ]);
        
        $errors = $this->compiler->validateDirectiveNesting($invalidDirective);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Expected @endif but found @endforeach', $errors[0]);
    }

    public function test_compile_node_method()
    {
        $textNode = new TextNode('Test content');
        
        $result = $this->compiler->compileNode($textNode);
        
        $this->assertEquals('Test content', $result);
    }

    public function test_complex_nested_compilation()
    {
        $ast = new DocumentNode([
            new TextNode('Header'),
            new DirectiveNode('if', '$user', [
                new TextNode('Welcome '),
                new EchoNode('$user->name'),
                new ComponentNode('alert', [
                    'type' => ['value' => 'success', 'dynamic' => false]
                ], new DocumentNode([
                    new TextNode('Login successful!')
                ])),
                new DirectiveNode('endif')
            ]),
            new TextNode('Footer')
        ]);
        
        $result = $this->compiler->compile($ast);
        
        $this->assertStringContainsString('Header', $result);
        $this->assertStringContainsString('<?php if($user): ?>', $result);
        $this->assertStringContainsString('Welcome ', $result);
        $this->assertStringContainsString('htmlspecialchars($user->name', $result);
        $this->assertStringContainsString('Component: alert', $result);
        $this->assertStringContainsString('Login successful!', $result);
        $this->assertStringContainsString('<?php endif; ?>', $result);
        $this->assertStringContainsString('Footer', $result);
    }
}