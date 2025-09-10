<?php

declare(strict_types=1);

namespace Elementary\Http\Middleware;

use Elementary\Http\Middleware\MiddlewareInterface;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Utils\EncryptionService;
use RuntimeException;

class EncryptCookies implements MiddlewareInterface
{
    private array $except = [];

    public function __construct(private EncryptionService $encrypter)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $this->decryptCookies($request);

        $response = $next($request);

        $this->encryptCookies($response);

        return $response;
    }

    private function decryptCookies(Request $request): void
    {
        foreach ($request->cookies->all() as $name => $value) {
            if ($this->isDisabled($name)) {
                continue;
            }

            try {
                $decrypted = $this->encrypter->decrypt($value);
                $request->cookies->set($name, $decrypted);
            } catch (RuntimeException $e) {
                $request->cookies->set($name, null);
            }
        }
    }

    private function encryptCookies(Response $response): void
    {
        foreach ($response->cookies->all() as $name => $value) {
            if ($this->isDisabled($name)) {
                continue;
            }
            
            $encrypted = $this->encrypter->encrypt($value);
            $response->cookies->set($name, $encrypted);
        }
    }

    private function isDisabled(string $name): bool
    {
        return in_array($name, $this->except, true);
    }
}
