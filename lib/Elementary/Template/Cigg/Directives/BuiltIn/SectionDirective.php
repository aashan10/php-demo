<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Directives\BuiltIn;

use Elementary\Template\Cigg\AST\DirectiveNode;
use Elementary\Template\Cigg\Directives\AbstractDirective;

class SectionDirective extends AbstractDirective
{
    public function getName(): string
    {
        return 'section';
    }

    public function isBlock(): bool
    {
        return true;
    }

    public function compile(DirectiveNode $node): string
    {
        $php = "<?php \$__layoutManager->startSection({$node->expression}); ?>";
        $php .= $this->compileChildren($node);
        return $php;
    }
}
