<?php

declare(strict_types=1);

namespace Elementary\Spark\Middleware;

use Elementary\Http\Middleware\MiddlewareInterface;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Psr\Log\LoggerInterface;

class ValidateJsonPayload implements MiddlewareInterface
{
    public function __construct(
        private ?LoggerInterface $logger = null
    ) {}

    public function process(Request $request, callable $next): Response
    {
        // Only validate JSON for Spark requests
        if (!$this->isSparkRequest($request)) {
            return $next($request);
        }

        $this->log('debug', 'Validating JSON payload for Spark request');

        try {
            $payload = json_decode($request->body(), true);
        } catch (\Throwable $e) {
            $this->log('error', 'Failed to decode request JSON', [
                'error' => $e->getMessage(),
                'body' => $request->body()
            ]);
            return new Response(400, json_encode([
                'error' => 'Invalid JSON payload',
                'message' => $e->getMessage()
            ]), ['Content-Type' => 'application/json']);
        }

        if (!$payload) {
            $this->log('error', 'Empty or invalid payload received');
            return new Response(400, json_encode([
                'error' => 'Empty or invalid payload'
            ]), ['Content-Type' => 'application/json']);
        }

        // Store parsed payload in request attributes for next middleware
        $request->attributes->set('spark_payload', $payload);
        
        $this->log('debug', 'JSON payload validation successful', [
            'payload_keys' => array_keys($payload)
        ]);

        return $next($request);
    }

    private function isSparkRequest(Request $request): bool
    {
        return $request->isMethod('POST') && 
               str_contains($request->uri(), '/spark/message') &&
               $request->headers->get('Content-Type') === 'application/json';
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->log($level, "[ValidateJsonPayload] {$message}", $context);
        }
    }
}