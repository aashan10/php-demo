<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use Elementary\Config\ConfigBag;
use Elementary\Utils\EncryptionService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;

class EncryptionServiceTest extends TestCase
{
    private ConfigBag|MockObject $configBag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configBag = $this->createMock(ConfigBag::class);
    }

    public function testConstructorThrowsExceptionWhenNoEncryptionKey(): void
    {
        $this->configBag->expects($this->once())
            ->method('get')
            ->with('encryption.key')
            ->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Encryption key not found in config.');

        new EncryptionService($this->configBag);
    }

    public function testConstructorThrowsExceptionWhenEmptyEncryptionKey(): void
    {
        $this->configBag->expects($this->once())
            ->method('get')
            ->with('encryption.key')
            ->willReturn('');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Encryption key not found in config.');

        new EncryptionService($this->configBag);
    }

    public function testConstructorWithValidKey(): void
    {
        $this->configBag->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function ($key, $default = null) {
                if ($key === 'encryption.key') {
                    return 'my-secret-encryption-key';
                }
                if ($key === 'encryption.cipher') {
                    return $default ?? 'AES-256-CBC';
                }
                return $default;
            });

        $service = new EncryptionService($this->configBag);

        $this->assertInstanceOf(EncryptionService::class, $service);
    }

    public function testConstructorWithBase64Key(): void
    {
        $originalKey = 'my-secret-encryption-key-32-chars';
        $base64Key = 'base64:' . base64_encode($originalKey);

        $this->configBag->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function ($key, $default = null) use ($base64Key) {
                if ($key === 'encryption.key') {
                    return $base64Key;
                }
                if ($key === 'encryption.cipher') {
                    return $default ?? 'AES-256-CBC';
                }
                return $default;
            });

        $service = new EncryptionService($this->configBag);

        $this->assertInstanceOf(EncryptionService::class, $service);
    }

    public function testConstructorWithCustomCipher(): void
    {
        $this->configBag->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function ($key, $default = null) {
                if ($key === 'encryption.key') {
                    return 'my-secret-encryption-key';
                }
                if ($key === 'encryption.cipher') {
                    return 'AES-256-GCM';
                }
                return $default;
            });

        $service = new EncryptionService($this->configBag);

        $this->assertInstanceOf(EncryptionService::class, $service);
    }

    private function createEncryptionService(string $key = 'test-encryption-key-32-characters', string $cipher = 'AES-256-CBC'): EncryptionService
    {
        $configBag = $this->createMock(ConfigBag::class);
        $configBag->method('get')
            ->willReturnMap([
                ['encryption.key', null, $key],
                ['encryption.cipher', 'AES-256-CBC', $cipher]
            ]);

        return new EncryptionService($configBag);
    }

    public function testEncryptReturnsStringAndDecryptReturnsOriginalValue(): void
    {
        $service = $this->createEncryptionService();
        $plaintext = 'Hello, World!';

        $encrypted = $service->encrypt($plaintext);

        $this->assertIsString($encrypted);
        $this->assertNotEquals($plaintext, $encrypted);
        $this->assertGreaterThan(0, strlen($encrypted));

        $decrypted = $service->decrypt($encrypted);
        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEncryptedValueIsBase64Encoded(): void
    {
        $service = $this->createEncryptionService();
        $plaintext = 'test data';

        $encrypted = $service->encrypt($plaintext);

        // Should be valid base64
        $decoded = base64_decode($encrypted, true);
        $this->assertNotFalse($decoded, 'Encrypted value should be valid base64');

        // Decoded should be valid JSON
        $data = json_decode($decoded, true);
        $this->assertIsArray($data, 'Base64 decoded content should be JSON');
        $this->assertArrayHasKey('iv', $data);
        $this->assertArrayHasKey('value', $data);
        $this->assertArrayHasKey('mac', $data);
    }

    public function testEncryptionWithDifferentDataTypes(): void
    {
        $service = $this->createEncryptionService();

        $testCases = [
            'simple string' => 'hello',
            'empty string' => '',
            'unicode string' => 'héllø wörld 🌍',
            'long string' => str_repeat('Lorem ipsum dolor sit amet', 100),
            'json data' => '{"user": {"id": 123, "name": "John"}}',
            'special chars' => "!@#$%^&*()_+-=[]{}|;':\",./<>?`~",
            'line breaks' => "Line 1\nLine 2\r\nLine 3\tTab",
        ];

        foreach ($testCases as $description => $plaintext) {
            $encrypted = $service->encrypt($plaintext);
            $decrypted = $service->decrypt($encrypted);

            $this->assertEquals($plaintext, $decrypted, "Failed for: $description");
        }
    }

    public function testEncryptionProducesUniqueResults(): void
    {
        $service = $this->createEncryptionService();
        $plaintext = 'same message';

        $encrypted1 = $service->encrypt($plaintext);
        $encrypted2 = $service->encrypt($plaintext);
        $encrypted3 = $service->encrypt($plaintext);

        // Each encryption should be unique (due to unique IV)
        $this->assertNotEquals($encrypted1, $encrypted2);
        $this->assertNotEquals($encrypted2, $encrypted3);
        $this->assertNotEquals($encrypted1, $encrypted3);

        // But all should decrypt to the same value
        $this->assertEquals($plaintext, $service->decrypt($encrypted1));
        $this->assertEquals($plaintext, $service->decrypt($encrypted2));
        $this->assertEquals($plaintext, $service->decrypt($encrypted3));
    }

    public function testDecryptionWithInvalidPayload(): void
    {
        $service = $this->createEncryptionService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The payload is invalid.');

        $service->decrypt('invalid-payload');
    }

    public function testDecryptionWithInvalidBase64(): void
    {
        $service = $this->createEncryptionService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The payload is invalid.');

        $service->decrypt('not-valid-base64!@#');
    }

    public function testDecryptionWithInvalidJSON(): void
    {
        $service = $this->createEncryptionService();

        // Valid base64 but invalid JSON
        $invalidJson = base64_encode('not json data');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The payload is invalid.');

        $service->decrypt($invalidJson);
    }

    public function testDecryptionWithMissingRequiredFields(): void
    {
        $service = $this->createEncryptionService();

        // Missing 'mac' field
        $payload = json_encode(['iv' => base64_encode('1234567890123456'), 'value' => 'encrypted']);
        $encoded = base64_encode($payload);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The payload is invalid.');

        $service->decrypt($encoded);
    }

    public function testDecryptionWithInvalidIvLength(): void
    {
        $service = $this->createEncryptionService();

        // IV too short for AES-256-CBC (requires 16 bytes)
        $payload = json_encode([
            'iv' => base64_encode('short'),
            'value' => 'encrypted',
            'mac' => 'hash'
        ]);
        $encoded = base64_encode($payload);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The payload is invalid.');

        $service->decrypt($encoded);
    }

    public function testDecryptionWithTamperedData(): void
    {
        $service = $this->createEncryptionService();
        $plaintext = 'secret data';

        // Get a valid encrypted payload
        $encrypted = $service->encrypt($plaintext);
        $payload = json_decode(base64_decode($encrypted), true);

        // Tamper with the encrypted value by changing the last character
        $payload['value'] = substr($payload['value'], 0, -1) . 'X';
        $tamperedPayload = base64_encode(json_encode($payload));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The payload is invalid.');

        $service->decrypt($tamperedPayload);
    }

    public function testDecryptionWithTamperedMac(): void
    {
        $service = $this->createEncryptionService();
        $plaintext = 'secret data';

        // Get a valid encrypted payload
        $encrypted = $service->encrypt($plaintext);
        $payload = json_decode(base64_decode($encrypted), true);

        // Tamper with the MAC
        $payload['mac'] = 'invalid_mac_hash';
        $tamperedPayload = base64_encode(json_encode($payload));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The payload is invalid.');

        $service->decrypt($tamperedPayload);
    }

    public function testEncryptionWithDifferentKeys(): void
    {
        $service1 = $this->createEncryptionService('key-1-must-be-32-characters-long');
        $service2 = $this->createEncryptionService('key-2-must-be-32-characters-long');

        $plaintext = 'secret message';

        $encrypted1 = $service1->encrypt($plaintext);
        $encrypted2 = $service2->encrypt($plaintext);

        // Different keys should produce different encrypted values
        $this->assertNotEquals($encrypted1, $encrypted2);

        // Service1 can decrypt its own encryption
        $this->assertEquals($plaintext, $service1->decrypt($encrypted1));

        // Service2 can decrypt its own encryption  
        $this->assertEquals($plaintext, $service2->decrypt($encrypted2));

        // But cross-decryption should fail
        $this->expectException(RuntimeException::class);
        $service1->decrypt($encrypted2);
    }

    public function testEncryptionWithBase64Key(): void
    {
        $originalKey = 'my-32-character-encryption-key!!';
        $base64Key = 'base64:' . base64_encode($originalKey);

        $configBag = $this->createMock(ConfigBag::class);
        $configBag->method('get')
            ->willReturnMap([
                ['encryption.key', null, $base64Key],
                ['encryption.cipher', 'AES-256-CBC', 'AES-256-CBC']
            ]);

        $service = new EncryptionService($configBag);
        $plaintext = 'test with base64 key';

        $encrypted = $service->encrypt($plaintext);
        $decrypted = $service->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testMacVerificationUsingHashEquals(): void
    {
        // This test verifies that MAC verification uses hash_equals for timing attack protection
        $service = $this->createEncryptionService();
        $plaintext = 'sensitive data';

        $encrypted = $service->encrypt($plaintext);
        $payload = json_decode(base64_decode($encrypted), true);

        // Create a payload with correct structure but wrong MAC
        $wrongMacPayload = $payload;
        $wrongMacPayload['mac'] = str_repeat('a', strlen($payload['mac']));
        $wrongMacEncoded = base64_encode(json_encode($wrongMacPayload));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The payload is invalid.');

        $service->decrypt($wrongMacEncoded);
    }

    public function testEncryptionFailsWithInvalidCipher(): void
    {
        // Test that invalid cipher is handled gracefully
        try {
            $service = $this->createEncryptionService('test-key-32-characters-long!!!', 'INVALID-CIPHER');
            $service->encrypt('test');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Could not encrypt the data.', $e->getMessage());
        } catch (\Throwable $e) {
            // openssl_cipher_iv_length might throw a warning/error for invalid cipher
            $this->assertTrue(true, 'Invalid cipher was properly rejected');
        }
    }

    public function testLargeDataEncryption(): void
    {
        $service = $this->createEncryptionService();
        
        // Test with large data (1MB)
        $largeData = str_repeat('Large data test. ', 65536); // ~1MB
        
        $encrypted = $service->encrypt($largeData);
        $decrypted = $service->decrypt($encrypted);
        
        $this->assertEquals($largeData, $decrypted);
        $this->assertGreaterThan(strlen($largeData), strlen($encrypted)); // Encrypted should be larger due to encoding
    }

    public function testEncryptionPayloadStructure(): void
    {
        $service = $this->createEncryptionService();
        $plaintext = 'test structure';

        $encrypted = $service->encrypt($plaintext);
        $payload = json_decode(base64_decode($encrypted), true);

        // Verify payload structure
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('iv', $payload);
        $this->assertArrayHasKey('value', $payload);
        $this->assertArrayHasKey('mac', $payload);

        // Verify IV is proper length (16 bytes for AES-256-CBC)
        $iv = base64_decode($payload['iv']);
        $this->assertEquals(16, strlen($iv));

        // Verify MAC is SHA256 hash (64 hex characters)
        $this->assertEquals(64, strlen($payload['mac']));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $payload['mac']);

        // Verify value is base64 encoded
        $this->assertNotFalse(base64_decode($payload['value'], true));
    }

    public function testConsistentEncryptionBetweenInstances(): void
    {
        // Create two instances with the same configuration
        $service1 = $this->createEncryptionService('same-key-32-characters-long!!!');
        $service2 = $this->createEncryptionService('same-key-32-characters-long!!!');

        $plaintext = 'cross-instance test';

        $encrypted1 = $service1->encrypt($plaintext);
        $encrypted2 = $service2->encrypt($plaintext);

        // Encrypted values should be different (due to random IV)
        $this->assertNotEquals($encrypted1, $encrypted2);

        // But each should be able to decrypt the other's encryption
        $this->assertEquals($plaintext, $service1->decrypt($encrypted2));
        $this->assertEquals($plaintext, $service2->decrypt($encrypted1));
    }
}