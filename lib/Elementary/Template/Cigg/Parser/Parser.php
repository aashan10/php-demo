<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Parser;

use Elementary\Template\Cigg\AST\ComponentNode;
use Elementary\Template\Cigg\Directives\DirectiveRegistry;
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

    public function __construct(
        private DirectiveRegistry $registry
    ) {}

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
            TokenType::T_COMPONENT_TAG => $this->parseComponent(),
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

            if (str_starts_with($expressionToken->value, '(') && str_ends_with($expressionToken->value, ')')) {
                $expression = substr($expressionToken->value, 1, -1);
            } else {
                $expression = $expressionToken->value;
            }
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

    private function parseComponent(): ?Node
    {
        $token = $this->peek();
        $tagContent = $token->value;

        // Is it a closing tag? If so, it should be handled by the parent block parser.
        if (str_starts_with($tagContent, '</ui-')) {
            $this->advance(); // Consume the closing tag to prevent infinite loop
            return null;
        }

        // Regex to extract tag name, attributes, and self-closing flag
        if (!preg_match('/<ui-([\w.-]+)((?:\s+(?:[\w-]+|:[\w-]+)\s*=\s*(?:\"[^\"]*\"|\'[^\']*\'))*)\s*(\/?)>/s', $tagContent, $matches)) {
            // Invalid component tag, treat as text
            $this->advance();
            return new TextNode($tagContent);
        }

        $tagName = $matches[1];
        $attributesString = $matches[2] ?? '';
        $isSelfClosing = !empty($matches[3]);

        $attributes = $this->parseAttributes($attributesString);
        $this->advance(); // Consume the component tag token

        if ($isSelfClosing) {
            return new ComponentNode($tagName, $attributes, null);
        }

        // It's a block component, so parse the slot content
        $children = [];
        while (!$this->isAtEnd()) {
            $next_token = $this->peek();
            if ($next_token->type === TokenType::T_COMPONENT_TAG && $next_token->value === '</ui-' . $tagName . '>') {
                $this->advance(); // Consume the closing tag
                break;
            }

            $node = $this->parseStatement();
            if ($node) {
                $children[] = $node;
            }
        }

        $slot = new DocumentNode($children);

        return new ComponentNode($tagName, $attributes, $slot);
    }

    private function parseAttributes(string $attributesString): array
    {
        $attributes = [];
        $pattern = '/\s+(:?)([\w-]+)\s*=\s*(?:\"([^\"]*)\"|\'([^\']*)\')/';
        if (preg_match_all($pattern, $attributesString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $isDynamic = !empty($match[1]); // Check if it starts with ':'
                $attributeName = $match[2];
                $attributeValue = $match[3] ?? $match[4]; // Get value from either double or single quotes
                
                $attributes[$attributeName] = [
                    'value' => $attributeValue,
                    'dynamic' => $isDynamic
                ];
            }
        }
        
        return $attributes;
    }

    private function parseBlockWithEnding(string $directiveName): array
    {
        $children = [];
        $endingNode = null;
        $endingDirectiveName = 'end' . $directiveName;

        while (!$this->isAtEnd()) {
            $token = $this->peek();
            
            if ($token->type === TokenType::T_DIRECTIVE_START) {
                // Look ahead to see if this is our ending directive
                $nextToken = $this->peekNext();
                if ($nextToken && $nextToken->value === $endingDirectiveName) {
                    $endingNode = $this->parseStatement();
                    break;
                }
            }
            
            $node = $this->parseStatement();
            if ($node) {
                $children[] = $node;
            }
        }

        return [
            'children' => $children,
            'endingNode' => $endingNode
        ];
    }

    private function isBlockDirective(string $directive): bool
    {
        foreach ($this->registry->getAllDirectives() as $dir) {
            if ($dir->getName() === $directive && $dir->isBlock()) {
                return true;
            }
        }
        return false;
    }

    private function isEndingDirective(string $directive): bool
    {
        if (str_starts_with($directive, 'end')) {
            return true;
        }
        return false;
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
