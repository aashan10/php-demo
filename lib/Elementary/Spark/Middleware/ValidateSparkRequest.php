<?php

declare(strict_types=1);

namespace Elementary\Spark\Middleware;

use Elementary\Http\Middleware\MiddlewareInterface;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Psr\Log\LoggerInterface;

class ValidateSparkRequest implements MiddlewareInterface
{
    public function __construct(
        private ?LoggerInterface $logger = null
    ) {}

    public function process(Request $request, callable $next): Response
    {
        // Only validate Spark-specific fields for Spark requests
        if (!$this->isSparkRequest($request)) {
            return $next($request);
        }

        $this->log('debug', 'Validating Spark request structure');

        $payload = $request->attributes->get('spark_payload');
        
        if (!$payload) {
            $this->log('error', 'Spark payload not found in request attributes');
            return new Response(500, json_encode([
                'error' => 'Internal error: Payload not processed by previous middleware'
            ]), ['Content-Type' => 'application/json']);
        }

        // Validate required Spark fields
        $errors = $this->validateRequiredFields($payload);
        if (!empty($errors)) {
            $this->log('error', 'Spark request validation failed', ['errors' => $errors]);
            return new Response(400, json_encode([
                'error' => 'Invalid Spark request',
                'validation_errors' => $errors
            ]), ['Content-Type' => 'application/json']);
        }

        // Validate component class exists
        $componentName = $payload['componentName'];
        if (!class_exists($componentName)) {
            $this->log('error', 'Component class does not exist', ['class' => $componentName]);
            return new Response(400, json_encode([
                'error' => 'Component class does not exist',
                'componentName' => $componentName
            ]), ['Content-Type' => 'application/json']);
        }

        // Validate component extends SparkComponent
        if (!is_subclass_of($componentName, \Elementary\Spark\SparkComponent::class)) {
            $this->log('error', 'Component class does not extend SparkComponent', ['class' => $componentName]);
            return new Response(400, json_encode([
                'error' => 'Component class must extend SparkComponent',
                'componentName' => $componentName
            ]), ['Content-Type' => 'application/json']);
        }

        $this->log('debug', 'Spark request validation successful', [
            'componentName' => $componentName,
            'componentId' => $payload['componentId'],
            'hasUpdates' => isset($payload['updates']),
            'hasMethod' => isset($payload['method'])
        ]);

        return $next($request);
    }

    private function validateRequiredFields(array $payload): array
    {
        $errors = [];

        if (!isset($payload['componentName']) || empty($payload['componentName'])) {
            $errors[] = 'Component name is required';
        }

        if (!isset($payload['componentId']) || empty($payload['componentId'])) {
            $errors[] = 'Component ID is required';
        }

        if (!isset($payload['serverMemo']) || !is_array($payload['serverMemo'])) {
            $errors[] = 'Server memo is required and must be an object';
        }

        return $errors;
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
            $this->logger->log($level, "[ValidateSparkRequest] {$message}", $context);
        }
    }
}