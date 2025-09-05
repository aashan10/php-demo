<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Parser;

use Elementary\Template\Cigg\Token\Token;
use Elementary\Template\Cigg\Token\TokenType;
use Elementary\Template\Cigg\AST\Node;
use Elementary\Template\Cigg\AST\DocumentNode;
use Elementary\Template\Cigg\AST\TextNode;
use Elementary\Template\Cigg\AST\EchoNode;
use Elementary\Template\Cigg\AST\DirectiveNode;

/**
 * Parser for Cigg templates
 */
class Parser
{
    private array $tokens;
    private int $current = 0;

    public function parse(array $tokens): Node
    {
        $this->tokens = $tokens;
        $this->current = 0;

        $children = [];
        
        while (!$this->isAtEnd()) {
            $node = $this->parseStatement();
            if ($node) {
                $children[] = $node;
            }
        }

        return new DocumentNode($children);
    }

    private function parseStatement(): ?Node
    {
        $token = $this->peek();

        return match($token->type) {
            TokenType::T_TEXT => $this->parseText(),
            TokenType::T_ECHO_START => $this->parseEcho(),
            TokenType::T_RAW_ECHO_START => $this->parseRawEcho(),
            TokenType::T_DIRECTIVE_START => $this->parseDirective(),
            TokenType::T_EOF => null,
            default => $this->advance() ? $this->parseStatement() : null
        };
    }

    private function parseText(): TextNode
    {
        $token = $this->advance();
        return new TextNode($token->value);
    }

    private function parseEcho(): EchoNode
    {
        $this->advance(); // consume {{
        $expression = $this->advance(); // get expression
        $this->advance(); // consume }}
        
        return new EchoNode($expression->value);
    }

    private function parseRawEcho(): EchoNode
    {
        $this->advance(); // consume {!!
        $expression = $this->advance(); // get expression
        $this->advance(); // consume !!}
        
        return new EchoNode($expression->value, true);
    }

    private function parseDirective(): DirectiveNode
    {
        $this->advance(); // consume @
        $name = $this->advance(); // get directive name
        
        $expression = '';
        if ($this->peek()->type === TokenType::T_EXPRESSION) {
            $expressionToken = $this->advance();
            $expression = trim($expressionToken->value, '()');
        }

        // Check if this is an ending directive
        if ($this->isEndingDirective($name->value)) {
            return new DirectiveNode($name->value, '', []);
        }

        // Handle block directives
        $children = [];
        if ($this->isBlockDirective($name->value)) {
            $blockResult = $this->parseBlockWithEnding($name->value);
            $children = $blockResult['children'];
            
            // Add the ending directive as the last child if found
            if ($blockResult['endingNode']) {
                $children[] = $blockResult['endingNode'];
            }
        }

        return new DirectiveNode($name->value, $expression, $children);
    }

    private function parseBlockWithEnding(string $directiveName): array
    {
        $children = [];
        $endDirective = 'end' . $directiveName;
        $endingNode = null;

        while (!$this->isAtEnd()) {
            $token = $this->peek();
            
            // Check for end directive
            if ($token->type === TokenType::T_DIRECTIVE_START) {
                $nextToken = $this->peekNext();
                if ($nextToken && $nextToken->type === TokenType::T_IDENTIFIER && $nextToken->value === $endDirective) {
                    // Consume the end directive
                    $this->advance(); // @
                    $endNameToken = $this->advance(); // end directive name
                    
                    // Create ending node
                    $endingNode = new DirectiveNode($endNameToken->value, '', []);
                    break;
                }
            }

            $child = $this->parseStatement();
            if ($child) {
                $children[] = $child;
            }
        }

        return [
            'children' => $children,
            'endingNode' => $endingNode
        ];
    }

    private function isBlockDirective(string $directive): bool
    {
        return in_array($directive, ['if', 'foreach', 'for', 'while', 'switch', 'unless', 'section', 'component', 'card']);
    }

    private function isEndingDirective(string $directive): bool
    {
        return str_starts_with($directive, 'end') || in_array($directive, ['else', 'elseif']);
    }

    private function peek(): Token
    {
        return $this->tokens[$this->current] ?? new Token(TokenType::T_EOF, '');
    }

    private function peekNext(): ?Token
    {
        return $this->tokens[$this->current + 1] ?? null;
    }

    private function advance(): Token
    {
        if (!$this->isAtEnd()) {
            $this->current++;
        }
        return $this->tokens[$this->current - 1];
    }

    private function isAtEnd(): bool
    {
        return $this->current >= count($this->tokens) || $this->peek()->type === TokenType::T_EOF;
    }
}
