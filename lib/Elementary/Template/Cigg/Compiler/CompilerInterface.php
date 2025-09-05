<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Compiler;

use Elementary\Template\Cigg\AST\Node;

interface CompilerInterface
{
    public function compileNode(Node $node): string;
}

