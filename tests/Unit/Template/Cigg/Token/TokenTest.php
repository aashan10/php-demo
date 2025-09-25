<?php

declare(strict_types=1);

namespace Tests\Unit\Template\Cigg\Token;

use Elementary\Template\Cigg\Token\Token;
use Elementary\Template\Cigg\Token\TokenType;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    public function test_constructor_sets_properties_correctly()
    {
        $token = new Token(TokenType::T_TEXT, 'Hello World', 5, 10);

        $this->assertEquals(TokenType::T_TEXT, $token->type);
        $this->assertEquals('Hello World', $token->value);
        $this->assertEquals(5, $token->line);
        $this->assertEquals(10, $token->column);
    }

    public function test_constructor_with_default_line_and_column()
    {
        $token = new Token(TokenType::T_ECHO_START, '{{');

        $this->assertEquals(TokenType::T_ECHO_START, $token->type);
        $this->assertEquals('{{', $token->value);
        $this->assertEquals(1, $token->line);
        $this->assertEquals(1, $token->column);
    }

    public function test_toString_formats_token_correctly()
    {
        $token = new Token(TokenType::T_IDENTIFIER, 'foreach', 3, 7);

        $expected = "[IDENTIFIER] 'foreach' at 3:7";
        $this->assertEquals($expected, (string) $token);
    }

    public function test_toString_with_empty_value()
    {
        $token = new Token(TokenType::T_EOF, '', 10, 15);

        $expected = "[EOF] '' at 10:15";
        $this->assertEquals($expected, (string) $token);
    }

    public function test_toString_with_special_characters()
    {
        $token = new Token(TokenType::T_EXPRESSION, '$user->name', 2, 8);

        $expected = "[EXPRESSION] '\$user->name' at 2:8";
        $this->assertEquals($expected, (string) $token);
    }

    public function test_token_properties_are_public()
    {
        $token = new Token(TokenType::T_DIRECTIVE_START, '@', 1, 1);

        // Test that properties can be accessed directly
        $this->assertEquals('@', $token->value);
        
        // Test that properties can be modified (since they're public)
        $token->value = '@@';
        $this->assertEquals('@@', $token->value);
        
        $token->line = 5;
        $this->assertEquals(5, $token->line);
        
        $token->column = 10;
        $this->assertEquals(10, $token->column);
    }

    public function test_token_with_multiline_content()
    {
        $content = "Line 1\nLine 2\nLine 3";
        $token = new Token(TokenType::T_TEXT, $content, 1, 1);

        $this->assertEquals($content, $token->value);
        $this->assertStringContainsString('Line 1', (string) $token);
        $this->assertStringContainsString('Line 2', (string) $token);
    }

    public function test_token_with_various_token_types()
    {
        $testCases = [
            [TokenType::T_TEXT, 'Plain text'],
            [TokenType::T_ECHO_START, '{{'],
            [TokenType::T_ECHO_END, '}}'],
            [TokenType::T_RAW_ECHO_START, '{!!'],
            [TokenType::T_RAW_ECHO_END, '!!}'],
            [TokenType::T_DIRECTIVE_START, '@'],
            [TokenType::T_IDENTIFIER, 'if'],
            [TokenType::T_LPAREN, '('],
            [TokenType::T_RPAREN, ')'],
            [TokenType::T_EXPRESSION, '$variable'],
            [TokenType::T_WHITESPACE, '   '],
            [TokenType::T_COMPONENT_TAG, '<ui-button>'],
            [TokenType::T_EOF, ''],
        ];

        foreach ($testCases as [$type, $value]) {
            $token = new Token($type, $value, 1, 1);
            
            $this->assertEquals($type, $token->type);
            $this->assertEquals($value, $token->value);
            $this->assertStringContainsString($type, (string) $token);
            $this->assertStringContainsString($value, (string) $token);
        }
    }

    public function test_token_immutability_concerns()
    {
        // Since properties are public, test that we can change them
        // This documents the current behavior
        $token = new Token(TokenType::T_TEXT, 'original', 1, 1);
        
        $originalToString = (string) $token;
        
        $token->type = TokenType::T_IDENTIFIER;
        $token->value = 'modified';
        $token->line = 99;
        $token->column = 99;
        
        $modifiedToString = (string) $token;
        
        $this->assertNotEquals($originalToString, $modifiedToString);
        $this->assertEquals("[IDENTIFIER] 'modified' at 99:99", $modifiedToString);
    }

    public function test_token_edge_cases()
    {
        // Test with zero line/column (should be valid)
        $token = new Token(TokenType::T_TEXT, 'test', 0, 0);
        $this->assertEquals(0, $token->line);
        $this->assertEquals(0, $token->column);
        
        // Test with very long values
        $longValue = str_repeat('a', 1000);
        $token = new Token(TokenType::T_TEXT, $longValue, 1, 1);
        $this->assertEquals($longValue, $token->value);
        $this->assertStringContainsString($longValue, (string) $token);
    }
}