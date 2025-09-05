<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Directives\BuiltIn;

use Elementary\Template\Cigg\Directives\AbstractDirective;
use Elementary\Template\Cigg\AST\DirectiveNode;

/**
 * JSON encoding directive
 */
class JsonDirective extends AbstractDirective
{
    public function getName(): string
    {
        return 'json';
    }
    
    public function compile(DirectiveNode $node): string
    {
        return "<?php echo json_encode({$node->expression}, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>";
    }
}
