<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Directives\BuiltIn;

use Elementary\Template\Cigg\AST\DirectiveNode;
use Elementary\Template\Cigg\Directives\AbstractDirective;

class YieldDirective extends AbstractDirective
{
    public function getName(): string
    {
        return 'yield';
    }

    public function compile(DirectiveNode $node): string
    {
        return "<?php echo \$__layoutManager->yield({$node->expression}); ?>";
    }
}
