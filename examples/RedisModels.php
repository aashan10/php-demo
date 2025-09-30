<?php

/**
 * Redis Model Examples
 * 
 * Demonstrates how to use Redis driver for session storage, caching,
 * and other key-value operations in Elementary framework.
 */

namespace App\Models;

use Elementary\Database\Model;

/**
 * Session Model - Redis-based session storage
 * 
 * Fast session storage with automatic expiration
 */
class Session extends Model 
{
    protected static string $connection = 'redis';
    protected static string $table = 'sessions';
    protected static array $fillable = ['user_id', 'data', 'expires_at', '_ttl'];
    
    /**
     * Create session with TTL
     */
    public static function createSession(int $userId, array $data, int $ttl = 3600): static
    {
        return static::create([
            'user_id' => $userId,
            'data' => serialize($data),
            'expires_at' => time() + $ttl,
            '_ttl' => $ttl, // Redis TTL
        ]);
    }
    
    /**
     * Get active session for user
     */
    public static function getActiveSession(int $userId): ?static
    {
        return static::where('user_id', '=', $userId)
            ->where('expires_at', '>', time())
            ->first();
    }
    
    /**
     * Cleanup expired sessions
     */
    public static function cleanup(): int
    {
        return static::where('expires_at', '<', time())->delete();
    }
    
    /**
     * Check if session is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at < time();
    }
    
    /**
     * Get session data
     */
    public function getData(): array
    {
        return unserialize($this->data) ?: [];
    }
    
    /**
     * Update session data
     */
    public function updateData(array $data): bool
    {
        return $this->update([
            'data' => serialize($data),
            'expires_at' => time() + 3600, // Extend TTL
        ]);
    }
}

/**
 * Cache Model - Redis-based application cache
 * 
 * High-performance caching with namespacing
 */
class Cache extends Model 
{
    protected static string $connection = 'redis';
    protected static string $table = 'cache';
    protected static array $fillable = ['key', 'value', 'expires_at', '_ttl'];
    
    /**
     * Remember a value with TTL
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = static::find($key);
        
        if ($cached && !$cached->isExpired()) {
            return unserialize($cached->value);
        }
        
        $value = $callback();
        
        static::create([
            'key' => $key,
            'value' => serialize($value),
            'expires_at' => time() + $ttl,
            '_ttl' => $ttl,
        ]);
        
        return $value;
    }
    
    /**
     * Set cache value
     */
    public static function put(string $key, $value, int $ttl = 3600): bool
    {
        // Delete existing
        static::forget($key);
        
        return static::create([
            'key' => $key,
            'value' => serialize($value),
            'expires_at' => time() + $ttl,
            '_ttl' => $ttl,
        ]) !== null;
    }
    
    /**
     * Get cache value
     */
    public static function getValue(string $key): mixed
    {
        $cached = static::find($key);
        
        if ($cached && !$cached->isExpired()) {
            return unserialize($cached->value);
        }
        
        return null;
    }
    
    /**
     * Forget (delete) cache key
     */
    public static function forget(string $key): bool
    {
        return static::where('key', '=', $key)->delete() > 0;
    }
    
    /**
     * Flush all cache
     */
    public static function flush(): int
    {
        return static::query()->delete();
    }
    
    /**
     * Check if cached value is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at < time();
    }
}

/**
 * Counter Model - Redis-based counters
 * 
 * Atomic counters for analytics, rate limiting, etc.
 */
class Counter extends Model 
{
    protected static string $connection = 'redis';
    protected static string $table = 'counters';
    protected static array $fillable = ['name', 'value', 'updated_at'];
    
    /**
     * Increment counter
     */
    public static function increment(string $name, int $by = 1): int
    {
        $counter = static::find($name);
        
        if ($counter) {
            $newValue = $counter->value + $by;
            $counter->update([
                'value' => $newValue,
                'updated_at' => time(),
            ]);
            return $newValue;
        } else {
            static::create([
                'name' => $name,
                'value' => $by,
                'updated_at' => time(),
            ]);
            return $by;
        }
    }
    
    /**
     * Decrement counter
     */
    public static function decrement(string $name, int $by = 1): int
    {
        return static::increment($name, -$by);
    }
    
    /**
     * Get counter value
     */
    public static function getValue(string $name): int
    {
        $counter = static::find($name);
        return $counter ? $counter->value : 0;
    }
    
    /**
     * Reset counter
     */
    public static function reset(string $name): bool
    {
        return static::where('name', '=', $name)->delete() > 0;
    }
}

/**
 * Queue Model - Redis-based simple queue
 * 
 * FIFO queue for background jobs
 */
class Queue extends Model 
{
    protected static string $connection = 'redis';
    protected static string $table = 'queue';
    protected static array $fillable = ['job_id', 'payload', 'priority', 'created_at'];
    
    /**
     * Push job to queue
     */
    public static function push(array $payload, int $priority = 0): static
    {
        return static::create([
            'job_id' => uniqid('job_', true),
            'payload' => serialize($payload),
            'priority' => $priority,
            'created_at' => time(),
        ]);
    }
    
    /**
     * Pop job from queue (highest priority first)
     */
    public static function pop(): ?static
    {
        $job = static::query()
            ->orderBy('priority', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->first();
            
        if ($job) {
            $job->delete();
            return $job;
        }
        
        return null;
    }
    
    /**
     * Get queue size
     */
    public static function size(): int
    {
        return static::count();
    }
    
    /**
     * Get job payload
     */
    public function getPayload(): array
    {
        return unserialize($this->payload) ?: [];
    }
}

// ===================================
// Usage Examples
// ===================================

/*

// Session Management
$session = Session::createSession(1, ['cart' => [1, 2, 3]], 3600);
$activeSession = Session::getActiveSession(1);
$sessionData = $activeSession?->getData();

// Caching
$expensiveData = Cache::remember('user_stats_1', 3600, function() {
    return ['total_orders' => 42, 'last_login' => time()];
});

Cache::put('user_preferences_1', ['theme' => 'dark'], 7200);
$preferences = Cache::getValue('user_preferences_1');

// Counters
Counter::increment('page_views');
Counter::increment('api_calls', 5);
$totalViews = Counter::getValue('page_views');

// Queue
Queue::push(['type' => 'send_email', 'user_id' => 1, 'subject' => 'Welcome!']);
Queue::push(['type' => 'resize_image', 'path' => '/uploads/avatar.jpg'], 10); // High priority

$job = Queue::pop();
if ($job) {
    $payload = $job->getPayload();
    // Process job...
}

// Cross-driver operations (Redis + MySQL)
$user = User::find(1); // MySQL
$userSession = Session::getActiveSession($user->id); // Redis
$userStats = Cache::getValue("stats_{$user->id}"); // Redis

*/