<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Directives\BuiltIn;

use Elementary\Template\Cigg\AST\DirectiveNode;
use Elementary\Template\Cigg\Directives\AbstractDirective;
use Elementary\Vite\ViteService;

class AssetDirective extends AbstractDirective
{
    public function __construct(private ViteService $vite)
    {
    }

    public function getName(): string
    {
        return 'asset';
    }

    public function compile(DirectiveNode $node): string
    {
        $entrypoint = trim($node->expression, " '\"");
        $viteServiceClass = ViteService::class;

        return "<?php echo \$__container->get('{$viteServiceClass}')->getAssetPaths('{$entrypoint}'); ?>";
    }
}
