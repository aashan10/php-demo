<?php

declare(strict_types=1);

namespace Tests\Unit\Template\Cigg\Token;

use Elementary\Template\Cigg\Token\TokenType;
use PHPUnit\Framework\TestCase;

class TokenTypeTest extends TestCase
{
    public function test_all_token_types_are_defined()
    {
        $expectedTokenTypes = [
            'T_TEXT',
            'T_ECHO_START',
            'T_ECHO_END', 
            'T_RAW_ECHO_START',
            'T_RAW_ECHO_END',
            'T_DIRECTIVE_START',
            'T_IDENTIFIER',
            'T_LPAREN',
            'T_RPAREN',
            'T_EXPRESSION',
            'T_WHITESPACE',
            'T_COMPONENT_TAG',
            'T_EOF'
        ];

        foreach ($expectedTokenTypes as $tokenType) {
            $this->assertTrue(defined(TokenType::class . '::' . $tokenType), 
                "Token type {$tokenType} should be defined");
        }
    }

    public function test_token_type_values()
    {
        $expectedValues = [
            TokenType::T_TEXT => 'TEXT',
            TokenType::T_ECHO_START => 'ECHO_START',
            TokenType::T_ECHO_END => 'ECHO_END',
            TokenType::T_RAW_ECHO_START => 'RAW_ECHO_START',
            TokenType::T_RAW_ECHO_END => 'RAW_ECHO_END',
            TokenType::T_DIRECTIVE_START => 'DIRECTIVE_START',
            TokenType::T_IDENTIFIER => 'IDENTIFIER',
            TokenType::T_LPAREN => 'LPAREN',
            TokenType::T_RPAREN => 'RPAREN',
            TokenType::T_EXPRESSION => 'EXPRESSION',
            TokenType::T_WHITESPACE => 'WHITESPACE',
            TokenType::T_COMPONENT_TAG => 'COMPONENT_TAG',
            TokenType::T_EOF => 'EOF'
        ];

        foreach ($expectedValues as $constant => $expectedValue) {
            $this->assertEquals($expectedValue, $constant, 
                "Token type constant should have correct string value");
        }
    }

    public function test_token_types_are_unique()
    {
        $tokenTypes = [
            TokenType::T_TEXT,
            TokenType::T_ECHO_START,
            TokenType::T_ECHO_END,
            TokenType::T_RAW_ECHO_START,
            TokenType::T_RAW_ECHO_END,
            TokenType::T_DIRECTIVE_START,
            TokenType::T_IDENTIFIER,
            TokenType::T_LPAREN,
            TokenType::T_RPAREN,
            TokenType::T_EXPRESSION,
            TokenType::T_WHITESPACE,
            TokenType::T_COMPONENT_TAG,
            TokenType::T_EOF
        ];

        $uniqueTypes = array_unique($tokenTypes);
        
        $this->assertEquals(count($tokenTypes), count($uniqueTypes), 
            'All token types should be unique');
    }

    public function test_token_type_constants_are_strings()
    {
        $tokenTypes = [
            TokenType::T_TEXT,
            TokenType::T_ECHO_START,
            TokenType::T_ECHO_END,
            TokenType::T_RAW_ECHO_START,
            TokenType::T_RAW_ECHO_END,
            TokenType::T_DIRECTIVE_START,
            TokenType::T_IDENTIFIER,
            TokenType::T_LPAREN,
            TokenType::T_RPAREN,
            TokenType::T_EXPRESSION,
            TokenType::T_WHITESPACE,
            TokenType::T_COMPONENT_TAG,
            TokenType::T_EOF
        ];

        foreach ($tokenTypes as $tokenType) {
            $this->assertIsString($tokenType, 'Token type should be a string');
            $this->assertNotEmpty($tokenType, 'Token type should not be empty');
        }
    }

    public function test_echo_token_pairs()
    {
        // Test that echo start/end pairs are properly defined
        $this->assertEquals('ECHO_START', TokenType::T_ECHO_START);
        $this->assertEquals('ECHO_END', TokenType::T_ECHO_END);
        $this->assertEquals('RAW_ECHO_START', TokenType::T_RAW_ECHO_START);
        $this->assertEquals('RAW_ECHO_END', TokenType::T_RAW_ECHO_END);
    }

    public function test_parentheses_token_pairs()
    {
        // Test that parentheses are properly defined
        $this->assertEquals('LPAREN', TokenType::T_LPAREN);
        $this->assertEquals('RPAREN', TokenType::T_RPAREN);
    }

    public function test_special_tokens()
    {
        // Test special purpose tokens
        $this->assertEquals('EOF', TokenType::T_EOF);
        $this->assertEquals('WHITESPACE', TokenType::T_WHITESPACE);
        $this->assertEquals('COMPONENT_TAG', TokenType::T_COMPONENT_TAG);
        $this->assertEquals('DIRECTIVE_START', TokenType::T_DIRECTIVE_START);
    }

    public function test_content_tokens()
    {
        // Test content-bearing tokens
        $this->assertEquals('TEXT', TokenType::T_TEXT);
        $this->assertEquals('IDENTIFIER', TokenType::T_IDENTIFIER);
        $this->assertEquals('EXPRESSION', TokenType::T_EXPRESSION);
    }

    public function test_reflection_access_to_constants()
    {
        $reflection = new \ReflectionClass(TokenType::class);
        $constants = $reflection->getConstants();

        $this->assertGreaterThan(10, count($constants), 
            'TokenType should have multiple constants defined');

        foreach ($constants as $name => $value) {
            $this->assertStringStartsWith('T_', $name, 
                'All TokenType constants should start with T_');
            $this->assertIsString($value, 
                'All TokenType constant values should be strings');
        }
    }

    public function test_token_type_naming_convention()
    {
        $reflection = new \ReflectionClass(TokenType::class);
        $constants = $reflection->getConstants();

        foreach ($constants as $name => $value) {
            // Check that constant names are properly formatted
            $this->assertMatchesRegularExpression('/^T_[A-Z_]+$/', $name, 
                "Token type constant '{$name}' should follow naming convention T_UPPERCASE_WITH_UNDERSCORES");
            
            // Check that values are uppercase
            $this->assertEquals(strtoupper($value), $value, 
                "Token type value '{$value}' should be uppercase");
            
            // Check that values don't contain T_ prefix
            $this->assertFalse(str_starts_with($value, 'T_'), 
                "Token type value '{$value}' should not start with T_ prefix");
        }
    }

    public function test_can_use_token_types_in_arrays()
    {
        $tokenMap = [
            TokenType::T_TEXT => 'text content',
            TokenType::T_ECHO_START => 'echo opening',
            TokenType::T_IDENTIFIER => 'directive name',
            TokenType::T_EOF => 'end of file'
        ];

        $this->assertEquals('text content', $tokenMap[TokenType::T_TEXT]);
        $this->assertEquals('echo opening', $tokenMap[TokenType::T_ECHO_START]);
        $this->assertEquals('directive name', $tokenMap[TokenType::T_IDENTIFIER]);
        $this->assertEquals('end of file', $tokenMap[TokenType::T_EOF]);
    }

    public function test_can_use_token_types_in_switch_statements()
    {
        $tokenType = TokenType::T_ECHO_START;
        $result = '';

        switch ($tokenType) {
            case TokenType::T_TEXT:
                $result = 'text';
                break;
            case TokenType::T_ECHO_START:
                $result = 'echo_start';
                break;
            case TokenType::T_DIRECTIVE_START:
                $result = 'directive';
                break;
            default:
                $result = 'unknown';
        }

        $this->assertEquals('echo_start', $result);
    }
}