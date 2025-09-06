<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Directives\BuiltIn;

use Elementary\Template\Cigg\Directives\AbstractDirective;
use Elementary\Template\Cigg\AST\DirectiveNode;
use Elementary\Template\Cigg\Engine;

/**
 * Include directive to render sub-templates.
 * Compiles to: <?php echo $__engine->render(expression, get_defined_vars()); ?>
 */
class IncludeDirective extends AbstractDirective
{

    public function getName(): string
    {
        return 'include';
    }

    public function isBlock(): bool
    {
        return false; // This is a single-line directive
    }

    public function compile(DirectiveNode $node): string
    {
        $expression = trim($node->expression, '\'');


        $viewPath = $this->engine?->resolveView($expression);
        $cachePath = $this->engine?->getCachePath() . '/' . md5($expression) . '.php';
        $template = $this->engine?->compileTemplate($viewPath, $cachePath);

        return "<?php require '{$cachePath}';  ?>";
    }
}
