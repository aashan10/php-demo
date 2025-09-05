<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Directives;

use Elementary\Template\Cigg\AST\DirectiveNode;
use Elementary\Template\Cigg\AST\Node;
use Elementary\Template\Cigg\Compiler\CompilerInterface;

abstract class AbstractDirective implements DirectiveInterface
{
    protected ?CompilerInterface $compiler = null;

    public function isBlock(): bool
    {
        return false;
    }
    
    public function setCompiler(CompilerInterface $compiler): void
    {
        $this->compiler = $compiler;
    }
    
    protected function compileChildren(DirectiveNode $node): string
    {
        if (!$this->compiler) {
            throw new \RuntimeException('Compiler not set for directive. Call setCompiler() first.');
        }
        
        $output = '';
        foreach ($node->children as $child) {
            $output .= $this->compileChild($child);
        }
        return $output;
    }
    
    protected function compileChild(Node $child): string
    {
        if (!$this->compiler) {
            throw new \RuntimeException('Compiler not set for directive. Call setCompiler() first.');
        }
        
        // Delegate to the main compiler to handle any type of node
        return $this->compiler->compileNode($child);
    }
    
    /**
     * Helper method to compile a specific child by index
     */
    protected function compileChildAt(DirectiveNode $node, int $index): string
    {
        if (!isset($node->children[$index])) {
            return '';
        }
        
        return $this->compileChild($node->children[$index]);
    }
    
    /**
     * Helper method to compile children within a range
     */
    protected function compileChildrenRange(DirectiveNode $node, int $start, ?int $end = null): string
    {
        $end = $end ?? count($node->children);
        $output = '';
        
        for ($i = $start; $i < $end && $i < count($node->children); $i++) {
            $output .= $this->compileChild($node->children[$i]);
        }
        
        return $output;
    }
    
    /**
     * Helper method to check if node has children
     */
    protected function hasChildren(DirectiveNode $node): bool
    {
        return !empty($node->children);
    }
    
    /**
     * Helper method to get child count
     */
    protected function getChildCount(DirectiveNode $node): int
    {
        return count($node->children);
    }
}
