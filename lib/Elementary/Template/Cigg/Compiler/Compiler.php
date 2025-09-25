<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Compiler;

use Elementary\Template\Cigg\AST\ComponentNode;
use Elementary\Template\Cigg\AST\DirectiveNode;
use Elementary\Template\Cigg\AST\DocumentNode;
use Elementary\Template\Cigg\AST\EchoNode;
use Elementary\Template\Cigg\AST\Node;
use Elementary\Template\Cigg\AST\NodeVisitor;
use Elementary\Template\Cigg\AST\TextNode;
use Elementary\Template\Cigg\Directives\DirectiveInterface;
use Elementary\Template\Cigg\Directives\DirectiveRegistry;

/**
 * Complete Cigg Compiler with full directive support and alternative parser compatibility
 */
class Compiler implements CompilerInterface, NodeVisitor
{
    public function __construct(
        private DirectiveRegistry $directiveRegistry
    ) {
        $this->injectCompilerIntoDirectives();
    }

    /**
     * Register a custom directive class
     */
    public function directive(DirectiveInterface $directive): void
    {
        $directive->setCompiler($this);
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
     * Get the directive registry
     */
    public function getDirectiveRegistry(): DirectiveRegistry
    {
        return $this->directiveRegistry;
    }

    /**
     * Compile AST to PHP code (main entry point)
     */
    public function compile(Node $ast): string
    {
        return $ast->accept($this);
    }

    /**
     * Compile any AST node (used by directives for child compilation)
     */
    public function compileNode(Node $node): string
    {
        return $node->accept($this);
    }

    // ========================================================================
    // Visitor Pattern Implementation
    // ========================================================================

    public function visitDocument(DocumentNode $node)
    {
        $php = '';
        foreach ($node->children as $child) {
            $php .= $child->accept($this);
        }
        return $php;
    }

    public function visitText(TextNode $node)
    {
        return $node->content;
    }

    public function visitEcho(EchoNode $node)
    {
        if ($node->raw) {
            return "<?php echo {$node->expression}; ?>";
        }
        return "<?php echo htmlspecialchars({$node->expression}, ENT_QUOTES, 'UTF-8'); ?>";
    }

    public function visitDirective(DirectiveNode $node)
    {
        return $this->compileDirective($node);
    }

    public function visitComponentNode(ComponentNode $node)
    {
        return $this->compileComponent($node);
    }


    // ========================================================================
    // Directive Compilation Logic
    // ========================================================================

    private function compileDirective(DirectiveNode $node): string
    {
        // Try custom directive class first
        if ($directive = $this->directiveRegistry->get($node->name)) {
            return $directive->compile($node);
        }

        // Try callable directive
        if ($callable = $this->directiveRegistry->getCallable($node->name)) {
            return $callable($node->expression, "@{$node->name}({$node->expression})");
        }

        // Handle built-in directives with proper alternative parser support
        return $this->compileBuiltInDirective($node);
    }

    private function compileBuiltInDirective(DirectiveNode $node): string
    {
        // Handle block opening directives
        if ($this->isBlockOpeningDirective($node->name)) {
            return $this->compileBlockDirective($node);
        }

        // Handle ending and standalone directives
        return match($node->name) {
            // Ending directives
            'endif' => "<?php endif; ?>",
            'endforeach' => "<?php endforeach; ?>",
            'endfor' => "<?php endfor; ?>",
            'endwhile' => "<?php endwhile; ?>",
            'endswitch' => "<?php endswitch; ?>",
            'endunless' => "<?php endif; ?>",
            'endsection' => "<?php \$__layoutManager->endSection(); ?>",
            'endcomponent' => "<?php echo \$this->renderComponent(); ?>",
            'endcard' => '</div></div>',
            'endloop' => "<?php endfor; ?>",
            'endproduction' => "<?php endif; ?>",

            // Conditional directives
            'elseif' => "<?php elseif({$node->expression}): ?>",
            'else' => "<?php else: ?>",

            // Control flow directives
            'break' => "<?php break; ?>",
            'continue' => "<?php continue; ?>",

            // Built-in utility directives
            'csrf' => '<input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">',
            'json' => "<?php echo json_encode({$node->expression}, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>",
            'dump' => "<?php var_dump({$node->expression}); ?>",
            'dd' => "<?php var_dump({$node->expression}); die(); ?>",

            default => "<?php /* Unknown directive: {$node->name} */ ?>"
        };
    }

    private function compileBlockDirective(DirectiveNode $node): string
    {
        $php = $this->getOpeningTag($node->name, $node->expression);
        
        // Compile all children (including the ending directive)
        foreach ($node->children as $child) {
            $php .= $child->accept($this);
        }
        
        return $php;
    }

    private function getOpeningTag(string $directive, string $expression): string
    {
        return match($directive) {
            'if' => "<?php if({$expression}): ?>",
            'unless' => "<?php if(!({$expression})): ?>",
            'foreach' => "<?php foreach({$expression}): ?>",
            'for' => "<?php for({$expression}): ?>",
            'while' => "<?php while({$expression}): ?>",
            'switch' => "<?php switch({$expression}): ?>",
            'section' => "<?php \$__layoutManager->startSection({$expression}); ?>",
            'component' => "<?php \$this->startComponent('{$expression}'); ?>",
            'production' => '<?php if(app()->environment("production")): ?>',
            'card' => "<div class=\"card\"><div class=\"card-body\">",
            'loop' => $this->compileLoopDirective($expression),
            default => "<?php /* Unknown block directive: {$directive} */ ?>"
        };
    }

    private function compileLoopDirective(string $expression): string
    {
        // Handle "5 times" syntax
        if (preg_match('/(\d+)\s+times?/i', $expression, $matches)) {
            $count = $matches[1];
            return "<?php for(\$_i = 0; \$_i < {$count}; \$_i++): ?>" .
                   "<?php \$loop = ['index' => \$_i, 'iteration' => \$_i + 1, 'remaining' => {$count} - \$_i - 1, 'count' => {$count}, 'first' => \$_i === 0, 'last' => \$_i === {$count} - 1]; ?>";
        }
        
        // Fallback to regular foreach
        return "<?php foreach({$expression}): ?>";
    }

    private function isBlockOpeningDirective(string $directive): bool
    {
        return in_array($directive, [
            'if', 'unless', 'foreach', 'for', 'while', 'switch', 
            'section', 'component', 'card', 'loop', 'production'
        ]);
    }

    /**
     * Inject compiler instance into all registered directive classes
     */
    private function injectCompilerIntoDirectives(): void
    {
        foreach ($this->directiveRegistry->getAllDirectives() as $directive) {
            $directive->setCompiler($this);
        }
    }

    // ========================================================================
    // Component Compilation Logic
    // ========================================================================

    private function compileComponent(ComponentNode $node): string
    {
        $componentName = $node->tagName;
        $attributes = $node->attributes;
        $slot = $node->slot;

        
        // Build attributes array
        $attributesPhp = '$__attributes = [';
        foreach ($attributes as $name => $attr) {
            $value = $attr['value'];
            $isDynamic = $attr['dynamic'];
            
            if ($isDynamic) {
                // Dynamic attribute (starts with :) - output as PHP variable
                $attributesPhp .= "\n    '{$name}' => {$value},";
            } else {
                // Static attribute - output as string
                $attributesPhp .= "\n    '{$name}' => " . var_export($value, true) . ",";
            }
        }
        $attributesPhp .= "\n];";

        // Handle slot content
        $slotPhp = '';
        if ($slot) {
            $slotPhp = '$__slot = function() { ob_start(); ?>' . $this->compileNode($slot) . '<?php return ob_get_clean(); };';
        } else {
            $slotPhp = '$__slot = function() { return ""; };';
        }

        // Generate the component render call
        $renderPhp = "<?php\n";
        $renderPhp .= "// Component: {$componentName}\n";
        $renderPhp .= "{$attributesPhp}\n";
        $renderPhp .= "{$slotPhp}\n";
        $renderPhp .= "\$__componentPath = '{$componentName}';\n";
        $renderPhp .= "echo \$this->renderComponent(\$__componentPath, \$__attributes, \$__slot());\n";
        $renderPhp .= "?>";

        return $renderPhp;
    }

    // ========================================================================
    // Compilation Utilities
    // ========================================================================

    /**
     * Check if a directive is a block directive (for validation)
     */
    public function isBlockDirective(string $directive): bool
    {
        return $this->isBlockOpeningDirective($directive) || $this->isEndingDirective($directive);
    }

    /**
     * Check if a directive is an ending directive
     */
    public function isEndingDirective(string $directive): bool
    {
        return str_starts_with($directive, 'end') || in_array($directive, ['else', 'elseif']);
    }

    /**
     * Get directive precedence (for potential optimization)
     */
    public function getDirectivePrecedence(string $directive): int
    {
        return match($directive) {
            'extends', 'section' => 100,
            'if', 'unless', 'foreach', 'for', 'while' => 50,
            'echo', 'json', 'csrf' => 10,
            default => 1
        };
    }

    /**
     * Validate directive nesting (optional validation method)
     */
    public function validateDirectiveNesting(DirectiveNode $node): array
    {
        $errors = [];
        
        // Example validation: check for proper nesting
        foreach ($node->children as $child) {
            if ($child instanceof DirectiveNode) {
                if ($this->isEndingDirective($child->name)) {
                    $expectedEnd = 'end' . $node->name;
                    if ($child->name !== $expectedEnd) {
                        $errors[] = "Expected @{$expectedEnd} but found @{$child->name}";
                    }
                }
            }
        }
        
        return $errors;
    }
}

