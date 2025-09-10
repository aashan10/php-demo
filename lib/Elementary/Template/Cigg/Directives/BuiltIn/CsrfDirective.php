<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Directives\BuiltIn;

use Elementary\Template\Cigg\Directives\AbstractDirective;
use Elementary\Template\Cigg\AST\DirectiveNode;
use Elementary\Utils\Csrf;

/**
 * CSRF token directive
 */
class CsrfDirective extends AbstractDirective
{
    public function getName(): string
    {
        return 'csrf';
    }
    
    public function compile(DirectiveNode $node): string
    {
        $csrfClass = Csrf::class;
        return "<input type=\"hidden\" name=\"_token\" value=\"<?php echo \$__container->get('{$csrfClass}')->getToken(); ?>\">";
    }
}
