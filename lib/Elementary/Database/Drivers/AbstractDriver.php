<?php

declare(strict_types=1);

namespace Elementary\Database\Drivers;

use Elementary\Database\Contracts\DatabaseDriverInterface;
use Elementary\Config\ConfigBag;

/**
 * Abstract Database Driver
 * 
 * Provides common functionality that can be shared across different
 * database drivers while requiring specific implementations for
 * driver-specific operations.
 */
abstract class AbstractDriver implements DatabaseDriverInterface
{
    protected array $config;
    protected array $stats = [
        'queries_executed' => 0,
        'connections_created' => 0,
        'transactions_started' => 0,
        'last_query_time' => null,
    ];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Execute a transaction with automatic rollback on failure
     */
    public function transaction(callable $callback): mixed
    {
        if (!$this->supportsTransactions()) {
            // For non-transactional drivers, just execute the callback
            return $callback();
        }

        $this->beginTransaction();
        $this->stats['transactions_started']++;

        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Get driver statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Increment query counter and update last query time
     */
    protected function recordQuery(): void
    {
        $this->stats['queries_executed']++;
        $this->stats['last_query_time'] = time();
    }

    /**
     * Increment connection counter
     */
    protected function recordConnection(): void
    {
        $this->stats['connections_created']++;
    }

    /**
     * Get configuration value
     */
    protected function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Check if connection pooling is enabled for this driver
     */
    public function supportsPooling(): bool
    {
        return $this->getConfig('pool.enabled', false);
    }

    /**
     * Default implementation - most drivers support transactions
     */
    public function supportsTransactions(): bool
    {
        return true;
    }
}