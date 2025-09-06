<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Directives\BuiltIn;

use Elementary\Template\Cigg\AST\TextNode;
use Elementary\Template\Cigg\Directives\AbstractDirective;
use Elementary\Template\Cigg\AST\DirectiveNode;

/**
 * php directive
 */
class PhpDirective extends AbstractDirective
{
    public function getName(): string
    {
        return 'php';
    }
    
    public function isBlock(): bool
    {
        return true;
    }
    
    public function compile(DirectiveNode $node): string
    {

        $code = $node->children[0] ?? null;

        if ($code instanceof TextNode) {
            return "<?php {$code->content} ?>";
        }
        return '';

    }
}

