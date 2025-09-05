<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Lexer;

use Elementary\Template\Cigg\Token\Token;
use Elementary\Template\Cigg\Token\TokenType;

/**
 * Lexer for Cigg templates
 */
class Lexer
{
private string $input;
    private int $position = 0;
    private int $line = 1;
    private int $column = 1;
    private int $length;
    
    // Context tracking
    private bool $inStyle = false;
    private bool $inScript = false;
    private bool $inAttribute = false;
    private int $attributeQuoteChar = 0; // 0 = none, 34 = ", 39 = '

    public function tokenize(string $input): array
    {
        $this->input = $input;
        $this->position = 0;
        $this->line = 1;
        $this->column = 1;
        $this->length = strlen($input);

        // Reset context
        $this->inStyle = false;
        $this->inScript = false;
        $this->inAttribute = false;
        $this->attributeQuoteChar = 0;

        $tokens = [];
        $textBuffer = '';

        while ($this->position < $this->length) {
            $char = $this->current();

            // Update context based on current position
            $this->updateContext();

            // Only process template syntax if we're not in a special context
            if (!$this->inSpecialContext()) {
                if ($char === '{') {
                    // Flush any accumulated text
                    if ($textBuffer) {
                        $tokens[] = new Token(TokenType::T_TEXT, $textBuffer, $this->line, $this->column - strlen($textBuffer));
                        $textBuffer = '';
                    }

                    $specialTokens = $this->scanTemplateSequence();
                    if ($specialTokens) {
                        $tokens = array_merge($tokens, $specialTokens);
                        continue;
                    } else {
                        $textBuffer .= $char;
                        $this->advance();
                    }
                } elseif ($char === '@') {
                    // Flush any accumulated text
                    if ($textBuffer) {
                        $tokens[] = new Token(TokenType::T_TEXT, $textBuffer, $this->line, $this->column - strlen($textBuffer));
                        $textBuffer = '';
                    }

                    $directiveTokens = $this->scanDirective();
                    if ($directiveTokens) {
                        $tokens = array_merge($tokens, $directiveTokens);
                        continue;
                    } else {
                        $textBuffer .= $char;
                        $this->advance();
                    }
                } else {
                    $textBuffer .= $char;
                    $this->advance();
                }
            } else {
                // In special context, treat everything as text
                $textBuffer .= $char;
                $this->advance();
            }
        }

        // Flush any remaining text
        if ($textBuffer) {
            $tokens[] = new Token(TokenType::T_TEXT, $textBuffer, $this->line, $this->column - strlen($textBuffer));
        }

        $tokens[] = new Token(TokenType::T_EOF, '', $this->line, $this->column);
        return $tokens;
    }

    private function updateContext(): void
    {
        $remaining = substr($this->input, $this->position);
        
        // Check for <style> tags
        if (!$this->inStyle && preg_match('/^<style\b[^>]*>/i', $remaining)) {
            $this->inStyle = true;
        } elseif ($this->inStyle && preg_match('/^<\/style>/i', $remaining)) {
            $this->inStyle = false;
        }
        
        // Check for <script> tags
        if (!$this->inScript && preg_match('/^<script\b[^>]*>/i', $remaining)) {
            $this->inScript = true;
        } elseif ($this->inScript && preg_match('/^<\/script>/i', $remaining)) {
            $this->inScript = false;
        }
        
        // Check for HTML attributes
        if (!$this->inAttribute) {
            // Look for attribute patterns like class="..." or data-index="..."
            if (preg_match('/^(\w+)\s*=\s*(["\'])/', $remaining, $matches)) {
                $this->inAttribute = true;
                $this->attributeQuoteChar = ord($matches[2]);
            }
        } elseif ($this->inAttribute) {
            // Check if we're at the closing quote
            if ($this->current() === chr($this->attributeQuoteChar)) {
                $this->inAttribute = false;
                $this->attributeQuoteChar = 0;
            }
        }
    }

    private function inSpecialContext(): bool
    {
        return $this->inStyle || $this->inScript || $this->inAttribute;
    }

    private function scanTemplateSequence(): ?array
    {
        $startLine = $this->line;
        $startColumn = $this->column;

        // Check for {{
        if ($this->peek(2) === '{{') {
            return $this->scanEcho($startLine, $startColumn);
        }

        // Check for {!!
        if ($this->peek(3) === '{!!') {
            return $this->scanRawEcho($startLine, $startColumn);
        }

        return null;
    }

    private function scanEcho(int $startLine, int $startColumn): array
    {
        $tokens = [];
        
        // Consume {{
        $this->advance(); // {
        $this->advance(); // {
        $tokens[] = new Token(TokenType::T_ECHO_START, '{{', $startLine, $startColumn);

        // Skip whitespace
        $this->skipWhitespace();

        // Scan expression until }}
        $expression = $this->scanUntil('}}');
        $expression = trim($expression);
        
        if ($expression) {
            $tokens[] = new Token(TokenType::T_EXPRESSION, $expression, $this->line, $this->column);
        }

        // Consume }}
        if ($this->peek(2) === '}}') {
            $this->advance(); // }
            $this->advance(); // }
            $tokens[] = new Token(TokenType::T_ECHO_END, '}}', $this->line, $this->column - 2);
        }

        return $tokens;
    }

    private function scanRawEcho(int $startLine, int $startColumn): array
    {
        $tokens = [];
        
        // Consume {!!
        $this->advance(); // {
        $this->advance(); // !
        $this->advance(); // !
        $tokens[] = new Token(TokenType::T_RAW_ECHO_START, '{!!', $startLine, $startColumn);

        // Skip whitespace
        $this->skipWhitespace();

        // Scan expression until !!}
        $expression = $this->scanUntil('!!}');
        $expression = trim($expression);
        
        if ($expression) {
            $tokens[] = new Token(TokenType::T_EXPRESSION, $expression, $this->line, $this->column);
        }

        // Consume !!}
        if ($this->peek(3) === '!!}') {
            $this->advance(); // !
            $this->advance(); // !
            $this->advance(); // }
            $tokens[] = new Token(TokenType::T_RAW_ECHO_END, '!!}', $this->line, $this->column - 3);
        }

        return $tokens;
    }

    private function scanDirective(): ?array
    {
        $startLine = $this->line;
        $startColumn = $this->column;

        // Check for escaped @@
        if ($this->peek(2) === '@@') {
            $this->advance(); // @
            $this->advance(); // @
            return [new Token(TokenType::T_TEXT, '@@', $startLine, $startColumn)];
        }

        $tokens = [];
        
        // Consume @
        $this->advance();
        $tokens[] = new Token(TokenType::T_DIRECTIVE_START, '@', $startLine, $startColumn);

        // Scan identifier
        $identifier = $this->scanIdentifier();
        if (!$identifier) {
            return null; // Not a valid directive
        }

        $tokens[] = new Token(TokenType::T_IDENTIFIER, $identifier, $this->line, $this->column - strlen($identifier));

        // Skip whitespace
        $this->skipWhitespace();

        // Check for expression in parentheses
        if ($this->current() === '(') {
            $expression = $this->scanParenthesizedExpression();
            if ($expression) {
                $tokens[] = new Token(TokenType::T_EXPRESSION, $expression, $this->line, $this->column - strlen($expression));
            }
        }

        return $tokens;
    }

    private function scanIdentifier(): string
    {
        $identifier = '';
        
        while (!$this->isAtEnd() && (ctype_alnum($this->current()) || $this->current() === '_')) {
            $identifier .= $this->current();
            $this->advance();
        }

        return $identifier;
    }

    private function scanParenthesizedExpression(): string
    {
        if ($this->current() !== '(') {
            return '';
        }

        $expression = '';
        $parenCount = 0;

        while (!$this->isAtEnd()) {
            $char = $this->current();
            $expression .= $char;
            
            if ($char === '(') {
                $parenCount++;
            } elseif ($char === ')') {
                $parenCount--;
                $this->advance();
                
                if ($parenCount === 0) {
                    break;
                }
                continue;
            }
            
            $this->advance();
        }

        return $expression;
    }

    private function scanUntil(string $delimiter): string
    {
        $content = '';
        
        while (!$this->isAtEnd() && !$this->peekMatches($delimiter)) {
            $content .= $this->current();
            $this->advance();
        }

        return $content;
    }

    private function skipWhitespace(): void
    {
        while (!$this->isAtEnd() && ctype_space($this->current())) {
            $this->advance();
        }
    }

    private function current(): string
    {
        if ($this->isAtEnd()) return "\0";
        return $this->input[$this->position];
    }

    private function peek(int $offset = 1): string
    {
        $pos = $this->position + $offset - 1;
        if ($pos >= $this->length) return "\0";
        return substr($this->input, $this->position, $offset);
    }

    private function peekMatches(string $str): bool
    {
        return substr($this->input, $this->position, strlen($str)) === $str;
    }

    private function advance(): void
    {
        if (!$this->isAtEnd()) {
            if ($this->input[$this->position] === "\n") {
                $this->line++;
                $this->column = 1;
            } else {
                $this->column++;
            }
            $this->position++;
        }
    }

    private function isAtEnd(): bool
    {
        return $this->position >= $this->length;
    }
}
