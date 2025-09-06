<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Directives\BuiltIn;

use Elementary\Template\Cigg\AST\DirectiveNode;
use Elementary\Template\Cigg\Directives\AbstractDirective;

class ExtendsDirective extends AbstractDirective
{
    public function getName(): string
    {
        return 'extends';
    }

    public function compile(DirectiveNode $node): string
    {
        return "<?php \$__layoutManager->extend({$node->expression}); ?>";
    }
}
