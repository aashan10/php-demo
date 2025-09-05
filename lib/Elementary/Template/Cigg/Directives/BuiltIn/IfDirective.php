<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Directives\BuiltIn;

use Elementary\Template\Cigg\Directives\AbstractDirective;
use Elementary\Template\Cigg\AST\DirectiveNode;

/**
 * If conditional directive
 */
class IfDirective extends AbstractDirective
{
    public function getName(): string
    {
        return 'if';
    }
    
    public function isBlock(): bool
    {
        return true;
    }
    
    public function compile(DirectiveNode $node): string
    {
        $php = "<?php if({$node->expression}): ?>";
        $php .= $this->compileChildren($node);
        return $php;
    }
}
