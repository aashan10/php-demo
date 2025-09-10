<?php

declare(strict_types=1);

namespace Elementary\Utils;

use Elementary\Config\ConfigBag;
use RuntimeException;

class EncryptionService
{
    private string $key;
    private string $cipher;

    public function __construct(ConfigBag $config)
    {
        $key = $config->get('encryption.key');
        if (!$key) {
            throw new RuntimeException('Encryption key not found in config.');
        }
        $this->key = $this->parseKey($key);
        $this->cipher = $config->get('encryption.cipher', 'AES-256-CBC');
    }

    private function parseKey(string $key): string
    {
        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }
        return $key;
    }

    public function encrypt(string $value): string
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));
        $encryptedValue = openssl_encrypt($value, $this->cipher, $this->key, 0, $iv);

        if ($encryptedValue === false) {
            throw new RuntimeException('Could not encrypt the data.');
        }

        $iv_base64 = base64_encode($iv);
        $mac = $this->hash($iv_base64, $encryptedValue);

        $json = json_encode(['iv' => $iv_base64, 'value' => $encryptedValue, 'mac' => $mac], JSON_UNESCAPED_SLASHES);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Could not serialize encrypted data.');
        }

        return base64_encode($json);
    }

    public function decrypt(string $payload): string
    {
        $payload = json_decode(base64_decode($payload), true);

        if (!$this->isValidPayload($payload)) {
            throw new RuntimeException('The payload is invalid.');
        }

        $iv = base64_decode($payload['iv'], true);

        $decrypted = openssl_decrypt($payload['value'], $this->cipher, $this->key, 0, $iv);

        if ($decrypted === false) {
            throw new RuntimeException('Could not decrypt the data.');
        }

        return $decrypted;
    }

    private function hash(string $iv_base64, string $value): string
    {
        return hash_hmac('sha256', $iv_base64 . $value, $this->key);
    }

    private function isValidPayload($payload): bool
    {
        if (!is_array($payload) || !isset($payload['iv'], $payload['value'], $payload['mac'])) {
            return false;
        }

        if (strlen(base64_decode($payload['iv'], true)) !== openssl_cipher_iv_length($this->cipher)) {
            return false;
        }

        return hash_equals($this->hash($payload['iv'], $payload['value']), $payload['mac']);
    }
}
