<?php

declare(strict_types=1);

namespace Elementary\Spark\Middleware;

use Elementary\Http\Middleware\MiddlewareInterface;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Spark\SparkManager;
use Psr\Log\LoggerInterface;

class ValidateSparkChecksum implements MiddlewareInterface
{
    public function __construct(
        private SparkManager $manager,
        private ?LoggerInterface $logger = null
    ) {}

    public function process(Request $request, callable $next): Response
    {
        // Only validate checksum for Spark requests
        if (!$this->isSparkRequest($request)) {
            return $next($request);
        }

        $this->log('debug', 'Validating Spark checksum');

        $payload = $request->attributes->get('spark_payload');
        
        if (!$payload) {
            $this->log('error', 'Spark payload not found in request attributes');
            return new Response(500, json_encode([
                'error' => 'Internal error: Payload not processed by previous middleware'
            ]), ['Content-Type' => 'application/json']);
        }

        // Skip checksum validation if not provided
        if (!isset($payload['checksum'])) {
            $this->log('debug', 'No checksum provided, skipping validation');
            return $next($request);
        }

        try {
            // Create component instance to validate checksum
            $component = $this->manager->createComponent($payload['componentName']);
            
            // Restore component state to match client
            $component->setId($payload['componentId']);
            if (isset($payload['serverMemo']['data'])) {
                $component->syncInput($payload['serverMemo']['data']);
            }
            if (isset($payload['updates'])) {
                $component->syncInput($payload['updates']);
            }

            // Verify checksum matches current component state
            if (!$component->verifyChecksum($payload['checksum'])) {
                $this->log('error', 'Checksum verification failed', [
                    'provided' => $payload['checksum'],
                    'generated' => $component->generateChecksum(),
                    'componentData' => $component->getPublicProperties(),
                    'componentId' => $payload['componentId']
                ]);
                
                return new Response(403, json_encode([
                    'error' => 'Invalid checksum',
                    'message' => 'Request integrity verification failed'
                ]), ['Content-Type' => 'application/json']);
            }

            $this->log('debug', 'Checksum validation successful', [
                'componentId' => $payload['componentId'],
                'checksum' => $payload['checksum']
            ]);

        } catch (\Throwable $e) {
            $this->log('error', 'Checksum validation error', [
                'error' => $e->getMessage(),
                'componentName' => $payload['componentName'],
                'componentId' => $payload['componentId']
            ]);
            
            return new Response(500, json_encode([
                'error' => 'Checksum validation failed',
                'message' => $e->getMessage()
            ]), ['Content-Type' => 'application/json']);
        }

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
            $this->logger->log($level, "[ValidateSparkChecksum] {$message}", $context);
        }
    }
}