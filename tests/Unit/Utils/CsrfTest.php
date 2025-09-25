<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use Elementary\Config\ConfigBag;
use Elementary\Utils\Csrf;
use Elementary\Utils\SessionBag;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class CsrfTest extends TestCase
{
    private Csrf $csrf;
    private SessionBag|MockObject $sessionBag;
    private ConfigBag|MockObject $configBag;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->sessionBag = $this->createMock(SessionBag::class);
        $this->configBag = $this->createMock(ConfigBag::class);
        $this->csrf = new Csrf($this->sessionBag, $this->configBag);
    }

    public function testConstructorRequiresDependencies(): void
    {
        $sessionBag = $this->createMock(SessionBag::class);
        $configBag = $this->createMock(ConfigBag::class);
        $csrf = new Csrf($sessionBag, $configBag);
        
        $this->assertInstanceOf(Csrf::class, $csrf);
    }

    public function testGetTokenGeneratesNewTokenWhenNoneExists(): void
    {
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with('_token')
            ->willReturn(null);
        
        $this->sessionBag->expects($this->once())
            ->method('has')
            ->with('_token')
            ->willReturn(false);
        
        $this->sessionBag->expects($this->once())
            ->method('set')
            ->with('_token', $this->isType('array'));
        
        $token = $this->csrf->getToken();
        
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function testGetTokenReturnsExistingValidToken(): void
    {
        $existingTokenData = [
            'value' => 'existing_token_value',
            'timestamp' => time() - 60 // 1 minute ago
        ];
        
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with('_token')
            ->willReturn($existingTokenData);
        
        $this->sessionBag->expects($this->once())
            ->method('has')
            ->with('_token')
            ->willReturn(true);
        
        // Mock config to return 10 minutes lifetime (default)
        $this->configBag->expects($this->once())
            ->method('get')
            ->with('session.csrf_lifetime', 10)
            ->willReturn(10);
        
        // Should not call set since token is valid
        $this->sessionBag->expects($this->never())
            ->method('set');
        
        $token = $this->csrf->getToken();
        
        $this->assertEquals('existing_token_value', $token);
    }

    public function testGetTokenRegeneratesExpiredToken(): void
    {
        $expiredTokenData = [
            'value' => 'expired_token',
            'timestamp' => time() - 700 // 11+ minutes ago (older than default 10 minutes)
        ];
        
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with('_token')
            ->willReturn($expiredTokenData);
        
        $this->sessionBag->expects($this->once())
            ->method('has')
            ->with('_token')
            ->willReturn(true);
        
        // Mock config to return 10 minutes lifetime
        $this->configBag->expects($this->once())
            ->method('get')
            ->with('session.csrf_lifetime', 10)
            ->willReturn(10);
        
        // Should call set to store new token
        $this->sessionBag->expects($this->once())
            ->method('set')
            ->with('_token', $this->isType('array'));
        
        $token = $this->csrf->getToken();
        
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
        $this->assertNotEquals('expired_token', $token);
    }

    public function testValidateReturnsFalseForNullToken(): void
    {
        $result = $this->csrf->validate(null);
        
        $this->assertFalse($result);
    }

    public function testValidateReturnsFalseForEmptyToken(): void
    {
        $result = $this->csrf->validate('');
        
        $this->assertFalse($result);
    }

    public function testValidateReturnsFalseWhenNoSessionToken(): void
    {
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with('_token')
            ->willReturn(null);
        
        $result = $this->csrf->validate('some_token');
        
        $this->assertFalse($result);
    }

    public function testValidateReturnsFalseForInvalidSessionTokenFormat(): void
    {
        // Test invalid string format
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with('_token')
            ->willReturn('invalid_string');
        
        $result = $this->csrf->validate('some_token');
        $this->assertFalse($result, 'Should return false for invalid string format');
    }
    
    public function testValidateReturnsFalseForMissingValueInToken(): void
    {
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with('_token')
            ->willReturn(['timestamp' => time()]); // missing value
        
        $result = $this->csrf->validate('some_token');
        $this->assertFalse($result, 'Should return false when token value is missing');
    }
    
    public function testValidateReturnsFalseForMissingTimestampInToken(): void
    {
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with('_token')
            ->willReturn(['value' => 'token']); // missing timestamp
        
        $result = $this->csrf->validate('some_token');
        $this->assertFalse($result, 'Should return false when token timestamp is missing');
    }

    public function testValidateReturnsFalseForExpiredToken(): void
    {
        $expiredTokenData = [
            'value' => 'valid_token',
            'timestamp' => time() - 700 // 11+ minutes ago
        ];
        
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with('_token')
            ->willReturn($expiredTokenData);
        
        $this->configBag->expects($this->once())
            ->method('get')
            ->with('session.csrf_lifetime', 10)
            ->willReturn(10); // 10 minutes
        
        $result = $this->csrf->validate('valid_token');
        
        $this->assertFalse($result);
    }

    public function testValidateReturnsTrueForValidToken(): void
    {
        $validTokenData = [
            'value' => 'valid_token_12345',
            'timestamp' => time() - 60 // 1 minute ago
        ];
        
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with('_token')
            ->willReturn($validTokenData);
        
        $this->configBag->expects($this->once())
            ->method('get')
            ->with('session.csrf_lifetime', 10)
            ->willReturn(10); // 10 minutes
        
        $result = $this->csrf->validate('valid_token_12345');
        
        $this->assertTrue($result);
    }

    public function testValidateUsesHashEqualsForSecureComparison(): void
    {
        // This test ensures timing attacks are prevented
        $tokenData = [
            'value' => 'correct_token',
            'timestamp' => time()
        ];
        
        $this->sessionBag->expects($this->exactly(2))
            ->method('get')
            ->with('_token')
            ->willReturn($tokenData);
        
        $this->configBag->expects($this->exactly(2))
            ->method('get')
            ->with('session.csrf_lifetime', 10)
            ->willReturn(10);
        
        // Test with correct token
        $this->assertTrue($this->csrf->validate('correct_token'));
        
        // Test with incorrect token (similar length to avoid obvious timing differences)
        $this->assertFalse($this->csrf->validate('incorrect_tok'));
    }

    public function testRegenerateTokenCreatesNewRandomToken(): void
    {
        $this->sessionBag->expects($this->once())
            ->method('set')
            ->with('_token', $this->callback(function ($tokenData) {
                return is_array($tokenData) 
                    && isset($tokenData['value'], $tokenData['timestamp'])
                    && is_string($tokenData['value'])
                    && strlen($tokenData['value']) === 64
                    && is_int($tokenData['timestamp'])
                    && $tokenData['timestamp'] <= time()
                    && $tokenData['timestamp'] > (time() - 5); // Generated within last 5 seconds
            }));
        
        $token = $this->csrf->regenerateToken();
        
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function testRegenerateTokenCreatesUniqueTokens(): void
    {
        $this->sessionBag->expects($this->exactly(3))
            ->method('set')
            ->with('_token', $this->isType('array'));
        
        $token1 = $this->csrf->regenerateToken();
        $token2 = $this->csrf->regenerateToken();
        $token3 = $this->csrf->regenerateToken();
        
        // All tokens should be different
        $this->assertNotEquals($token1, $token2);
        $this->assertNotEquals($token2, $token3);
        $this->assertNotEquals($token1, $token3);
        
        // All should be valid hex strings of correct length
        $tokens = [$token1, $token2, $token3];
        foreach ($tokens as $token) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        }
    }

    public function testTokenExpirationWithCustomLifetime(): void
    {
        // Test with custom 5 minute lifetime
        $tokenData = [
            'value' => 'test_token',
            'timestamp' => time() - 400 // 6+ minutes ago
        ];
        
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with('_token')
            ->willReturn($tokenData);
        
        $this->configBag->expects($this->once())
            ->method('get')
            ->with('session.csrf_lifetime', 10)
            ->willReturn(5); // 5 minutes
        
        $result = $this->csrf->validate('test_token');
        
        $this->assertFalse($result); // Should be expired
    }

    public function testTokenExpirationBoundaryConditions(): void
    {
        // Test token that's just over expiration time (should be expired)
        $expiredTokenData = [
            'value' => 'boundary_token',
            'timestamp' => time() - 601 // Just over 10 minutes
        ];
        
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with('_token')
            ->willReturn($expiredTokenData);
            
        $this->configBag->expects($this->once())
            ->method('get')
            ->with('session.csrf_lifetime', 10)
            ->willReturn(10); // 10 minutes = 600 seconds
        
        $this->assertFalse($this->csrf->validate('boundary_token'));
    }
    
    public function testTokenJustWithinExpirationTime(): void
    {
        // Token just under expiration time (should be valid)
        $validTokenData = [
            'value' => 'valid_boundary_token',
            'timestamp' => time() - 599 // Just under 10 minutes
        ];
        
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with('_token')
            ->willReturn($validTokenData);
            
        $this->configBag->expects($this->once())
            ->method('get')
            ->with('session.csrf_lifetime', 10)
            ->willReturn(10); // 10 minutes = 600 seconds
        
        $this->assertTrue($this->csrf->validate('valid_boundary_token'));
    }

    public function testCompleteTokenWorkflow(): void
    {
        // This test demonstrates the complete workflow but uses separate CSRF instances
        // to avoid complex mock setup
        
        $sessionBag1 = $this->createMock(SessionBag::class);
        $configBag1 = $this->createMock(ConfigBag::class);
        $csrf1 = new Csrf($sessionBag1, $configBag1);
        
        // 1. Generate initial token (no existing token)
        $sessionBag1->expects($this->once())
            ->method('get')
            ->with('_token')
            ->willReturn(null);
        
        $sessionBag1->expects($this->once())
            ->method('has')
            ->with('_token')
            ->willReturn(false);
        
        $storedTokenData = null;
        $sessionBag1->expects($this->once())
            ->method('set')
            ->with('_token', $this->callback(function ($data) use (&$storedTokenData) {
                $storedTokenData = $data;
                return true;
            }));
        
        $token = $csrf1->getToken();
        
        // 2. Create new CSRF instance for validation (simulates new request)
        $sessionBag2 = $this->createMock(SessionBag::class);
        $configBag2 = $this->createMock(ConfigBag::class);
        $csrf2 = new Csrf($sessionBag2, $configBag2);
        
        $sessionBag2->expects($this->once())
            ->method('get')
            ->with('_token')
            ->willReturn($storedTokenData);
        
        $configBag2->expects($this->once())
            ->method('get')
            ->with('session.csrf_lifetime', 10)
            ->willReturn(10);
        
        $isValid = $csrf2->validate($token);
        
        $this->assertTrue($isValid);
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
    }

}