<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Directives;

use Elementary\Template\Cigg\AST\DirectiveNode;
use Elementary\Template\Cigg\Compiler\CompilerInterface;

interface DirectiveInterface
{
    /**
     * Get the directive name (without @)
     */
    public function getName(): string;
    
    /**
     * Check if this is a block directive (has opening/closing tags)
     */
    public function isBlock(): bool;
    
    /**
     * Compile the directive to PHP code
     */
    public function compile(DirectiveNode $node): string;
    
    /**
     * Set the compiler instance for child compilation
     */
    public function setCompiler(CompilerInterface $compiler): void;
}
