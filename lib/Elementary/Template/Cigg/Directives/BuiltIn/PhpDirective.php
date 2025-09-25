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

        $start = "<?php\n";
        foreach ($node->children as $child) {
            if ($child instanceof TextNode) {
                $start .= $child->content . "\n";
            }
        }
        $start .= "?>";

        return $start;

    }
}

