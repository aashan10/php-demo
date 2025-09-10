<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Directives\BuiltIn;

use Elementary\Template\Cigg\AST\DirectiveNode;
use Elementary\Template\Cigg\Directives\AbstractDirective;
use Elementary\Utils\FlashBag;

final class FlashDirective extends AbstractDirective
{
    public function __construct(
        private FlashBag $bag
    ) {}

    public function getName(): string
    {
        return 'flash';
    }

    public function compile(DirectiveNode $node): string
    {
        $name = trim($node->expression, '\'');
        $classname = FlashBag::class;

        return "<?= \$__container->get('$classname')->get('$name', '') ?>";
    }

}
